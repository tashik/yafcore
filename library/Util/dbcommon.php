<?php

define('OUTPUT_DATE_PATTERN', 'dd.MM.YYYY H:m:s');
define('SQL_DATE_PATTERN', 'yyyy-MM-dd HH:mm:ss');
if (!defined('TIME_FORMAT_SQL')) {
  define('TIME_FORMAT_SQL', SQL_DATE_PATTERN);
}
if (!defined('TIME_FORMAT_DISPLAY')) {
  define('TIME_FORMAT_DISPLAY', 'dd.MM.YYYY HH:mm');
}

function db_escape_string($str)
{
  return pg_escape_string($str);
  //return mysql_escape_string($str);
}

function db_escape_value($val, $quote_string = true) {
  if (is_null($val)) {
    return 'NULL';
  } elseif (is_bool($val)) {
    return $val?'TRUE':'FALSE';
  } elseif (is_int($val)||  is_float($val)) {
    return str_replace(',', '.', "$val");
  } else {
    $val = db_escape_string("$val");
    return $quote_string?"'$val'":$val;
  }
}

if (!function_exists('db_date_to_timestamp')) {
function db_date_to_timestamp($date)
{
  if ( !is_string($date) )
  {
    return false;
  }
  if  (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}[.0-9]*([+-][0-9]+)?$/', $date))
  {
    preg_match('/^([\d]{4})-([\d]{2})-([\d]{2}) ([\d]{2}):([\d]{2}):([\d]{2})[.0-9]*([+-][0-9]+)?$/', $date, $date);
    //$date=strptime($date, '%Y-%m-%d %H:%M:%S');
    $time=mktime(intval($date[4]), intval($date[5]), intval($date[6]), intval($date[2]), intval($date[3]), intval($date[1]));
    if (isset($data[7])) {
      $time += ONE_HOUR*intval($data[7]);
    }
  }
  else
  {
    $time=strtotime($date);
  }
  return $time;
}
}

if (!function_exists('db_timestamp_to_date')) {
function db_timestamp_to_date($ts)
{
  if (empty($ts))
  {
    return null;
  }
  return date('Y-m-d H:i:s', $ts);
}
}

function db_string_to_date($str)
{
  $s = new Zend_Date($str, 'ru_RU');
  return $s->toString(SQL_DATE_PATTERN);
}

function db_get_vocab($table, $id)
{
  $db=Zend_Registry::get('db');
  $result=$db->fetchRow('SELECT name FROM '.$table.' WHERE id = ?', array($id), Zend_Db::FETCH_ASSOC);
  if (count($result))
  {
    return $result['name'];
  }
  return null;
}

class DbTransaction
{
  static $in_transaction=0;
  static $db=null;
  static $transactions_trace = array();
  static public function start()
  {
    if (self::$in_transaction)
    {
      self::$in_transaction++;
    }
    else
    {
      self::$db=Zend_Registry::get('db');
      self::$db->beginTransaction();
      self::$in_transaction=1;
    }
    if (defined('LOUD_DEBUG')) {
      self::$transactions_trace[self::$in_transaction-1] = getCurrentTrace(self::$in_transaction);
    }
  }

  static public function begin()
  {
    self::start();
  }

  static public function rollback()
  {
    if (self::$in_transaction)
    {
      self::$db->rollback();
      logStr('Transaction rollback!');
      self::$in_transaction=0;
      self::$transactions_trace = array();
    }
  }

  static public function commit()
  {
    if (1==self::$in_transaction)
    {
      self::$db->commit();
    }
    self::$in_transaction--;
    if (defined('LOUD_DEBUG')) {
      self::$transactions_trace[self::$in_transaction]['committed'] = true;
    }
    if (self::$in_transaction < 0)
    {
      self::$in_transaction=0;
      self::$transactions_trace = array();
    }
  }
}


function prepareFTSKeywords($keywords) {
  $keywords = mb_trim($keywords);
  $keywords = preg_replace('@[-—.\\/+]@u', ' ', $keywords);
  $keywords = preg_replace('@\s*,\s*@u', '|', $keywords);
  $keywords = preg_replace('@!+\s+@u', '!', $keywords);
  $keywords = preg_replace('@\s+@u', '&', $keywords);
  $keywords = preg_replace('@\|{2,}@u', '|', $keywords);
  $keywords = preg_replace('@&{2,}@u', '&', $keywords);
  $keywords = preg_replace('@&+\|+@u', '&', $keywords);
  $keywords = preg_replace('@\|+\&+@u', '&', $keywords);
  $keywords = preg_replace('@[&!|]{2,}@u', '&', $keywords);
  $keywords = preg_replace('@\)\(@u', ')&(', $keywords);
  return $keywords;
}

function getSortParams($params) {
  if (is_array($params['sort'])) {
    return db_escape_string($params['sort'][0]['property']).' '.db_escape_string($params['sort'][0]['direction']);
  }
  return db_escape_string($params['sort']).' '.db_escape_string($params['dir']);
}

/**
 * Возвращает Zend_Db_Expr для селекта колонки в формате ISO 8601 Date
 * @param string $column имя колонки
 * @return Zend_Db_Expr конвертер
 */
function ISOTimeSelect($column) {
  //return new Zend_Db_Expr("to_char($column AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.USZ')");
  return new Zend_Db_Expr("(to_char($column, 'YYYY-MM-DD\"T\"HH24:MI:SS.MS')".
                          "|| to_char(extract('timezone_hour' from $column),'S00')".
                          "||':'".
                          "|| to_char(extract('timezone_minute' from $column),'FM00'))");
}

/**
 * Добавляет в селект параметры where
 *
 * @param Zend_Db_Select $select селект
 * @param array $params Список параметров, которые нужно добавить (данные из запроса)
 * @param array $objects Массив объектов, по которым следует смотреть возможность
 *   наличия параметра. Вид массива: array('таблица_в_бд'=>объект), у объекта должен
 *   быть определен метод hasProperty (в Core_Mapper и всех наследниках он есть),
 *   который определяет если ли нужная пропертя в объекте.
 * @return Zend_Db_Select $select
 */
function prepareWhereStatement($select, array $params, array $objects) {
  $db = Zend_Registry::get('db');
  foreach ($params as $name=>$value) {
    if (''===$value) {
      continue;
    }
    $sign = '=';
    if (preg_match('@^(.+)_from$@', $name, $matches)) {
      $sign = '>=';
      $name = $matches[1];
    }
    if (preg_match('@^(.+)_till$@', $name, $matches)) {
      $sign = '<=';
      $name = $matches[1];
    }
    if (!is_array($value)) {
      $value = array($value);
    }
    foreach ($objects as $on=>$ov) {
      if ($ov->hasProperty($name)) {
        $total_value = array();
        foreach ($value as $v) {
          $total_value[] = $db->quoteInto("$on.$name $sign ?", $v);
        }
        if (!empty($total_value)) {
          $total_value = join(' OR ', $total_value);
          $select->where($total_value);
        }
      }
    }
  }
  return $select;
}

/**
 * Получает данные и их количество из пагинатора. Оптимизирует получение количества:
 * не дергает базу, если она вернула меньше результатов чем просили.
 * Использование:
 *   list($rows, $count) = getPagerData($select, $params['start'], $params['limit']);
 *
 *
 * @param Zend_Paginator_Adapter_DbSelect|Zend_Db_Select $pager пагинатор или селект
 * @param int $start
 * @param int $limit
 * @param bool|int $truncate автоматически ограничивать значение $limit.
 *   * false — не ограничивать
 *   * true — ограничивать значением из конфига
 *   * число — ограничивать числом
 * @param bool|int $skip_count пропускать подсчет числа записей (вернет в
 *   качества числа 1000 или $skip_count, если оно int)
 * @return array($data, $count)
 */
function getPagerData($pager, $start=0, $limit=25, $truncate = true, $skip_count=false) {
  $distinct = false;
  if ($pager instanceof Zend_Db_Select) {
    $select = $pager;
    if ( $select->getPart(Zend_Db_Select::DISTINCT)
         || $select->getPart(Zend_Db_Select::UNION)
       )
    {
      $distinct = true;
    }
    $pager = new Zend_Paginator_Adapter_DbSelect($select);
  }
  $limit = intval($limit);
  if ($truncate) {
    $max_limit = is_int($truncate)?$truncate:intval(getConfigValue('interface->linesPerPage', 25));
    if (empty($limit) || $limit>$max_limit) {
      $limit = $max_limit;
    }
  }
  if ($skip_count) {
    $pager->setRowCount(is_int($skip_count)?$skip_count:1000);
  }
  if ($distinct) {
    $count = $pager->count();
    $data = $pager->getItems(intval($start), $limit);
    return array($data, $count);
  }
  //logVar($select->__toString());
  $data = $pager->getItems(intval($start), $limit);
  //logVar($select->__toString());
  $count = count($data);
  if ($count<$limit && (0==$start||$count>0)) {
    $count += $start;
  } else {
    //logVar($pager->getCountSelect()->__toString(), 'count select');
    $count = intval($pager->count());
  }
  return array($data, $count);
}

/**
 * @return Zend_Db_Adapter_Abstract
 */
function getDbInstance() {
  return Zend_Registry::get('db');
}

/**
 * Схлопывает массив вида [ключ1=>значение1, ключ2=>значение2] в массив вида ["ключ1=значение1", "ключ2=значение2"]
 * При этом ескейпля поля и значения
 * @param array $where массив полей
 * @param string $join если указано, то массив будет схлопнут в одну строку через указанный разделитель
 * @param Zend_Db_Adapter_Abstract $db Адаптер БД, через который ескейпить. Если NULL то будет использоваться системный
 * @return array|string
 */
function collapseWhere($where, $join=false, $db=null) {
  $ret = array();
  if (!$db) {
    $db = getDbInstance();
  }
  foreach ($where as $f => $v) {
    $ret[] = $db->quoteInto($db->quoteIdentifier($f).'=?', $v);
  }
  if ($join) {
    $ret = '('.join(") $join (", $ret).')';
  }
  return $ret;
}
