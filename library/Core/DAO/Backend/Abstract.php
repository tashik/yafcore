<?php

abstract class Core_DAO_Backend_Abstract {

  protected static $_cache = array();
  protected $_table_name;
  protected $_supported_caps = array();

  const CAP_AGGREGATE = 1; // бекенд способен аггегировать одни объекты внутри других
  const CAP_PARTIAL_UPDATE = 2; // бекенд способен обновлять только часть записей
  const CAP_SEARCH = 3; // бекенд способен делать выборки по значениям полей
  const CAP_SEARCH_ADVANCED = 4; // бекенд способен делать выборки по Zend_Db_Select

  public function __construct($config) {
    if (!isset($config['table'])) {
      throw new Exception('Database backend: table not specified');
    }
    $this->_table_name = $config['table'];
  }

  public static function factory($options) {
    $backend = isset($options['type'])?ucfirst($options['type']):'Db';

    /*$backend_aliases = array('Db'=>'Database');
    foreach ($backend_aliases as $alias=>$name) {
      if ($backend==$alias) {
        $backend = $name;
        break;
      }
    }*/

    $class = "Core_DAO_Backend_$backend";
    if (!class_exists($class)) {
      throw new Exception("Bad backend $backend");
    }
    return new $class($options);
  }

  abstract public function fetch($id);

  abstract public function save($data);

  public function update($id, $data) {
    // необходимо переопределить функцию в случае поддержки CAP_PARTIAL_UPDATE
    return false;
  }

  /**
   * Обратное приведение типов при заполнении экземпляра
   * @param $value
   * @param $type
   * @return bool|float|int|mixed|Model_Address|string
   * @throws Exception
   */
  public function convertRawToType($value, $type) {
    if (null === $value) {
      return null;
    }
    if (empty($type)) {
      return $value;
    }
    switch ($type)
    {
      case 'serialize':
        $value = unserialize($value);
        break;
      case 'price':
        $value=filterPrice($value);
        if (empty($value))
          $value = 0;
        break;
      case 'bool':
        $value = !!intval($value);
        break;
      case 'isodate':
        $value = toIsoDate($value);
        break;
      case 'address':
        $value=new Model_Address($value);
        break;
      case 'binary':
      case 'bindata':
      case 'id':
      case 'string':
        $value = "$value";
        break;
      case 'int':
      case 'int64':
      case 'bigint':
        $value = intval("$value");
        break;
      case 'array':
        $value = toArray($value);
        break;
      default:
        throw new Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  /**
   * Приведение типов перед сохранением в БД
   * @param $value
   * @param $type
   * @return bool|float|int|mixed|Model_Address|string
   * @throws Exception
   */
  public function convertTypeToRaw($value, $type) {
    if (null === $value) {
      return null;
    }
    switch ($type)
    {
      case 'serialize':
        $value=serialize($value);
        break;
      case 'price':
        $value = filterPrice($value);
        if (empty($value))
          $value=0;
        break;
      case 'bool':
        $value=!!$value;
        break;
      case 'isodate':
        $value = toIsoDate($value);
        break;
      case 'address':
        if (!is_a($value, 'Model_Address')) {
          $value = new Model_Address($value);
        }
        //unserialize($var_value);
        $value = $value->getRawData();
        break;
      case 'binary':
      case 'bindata':
      case 'id':
      case 'string':
        $value = "$value";
        break;
      case 'array':
        $value = toArray($value);
        break;
      default:
        throw new Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  /**
   * Получить селектор бекенда
   * @param array $fields перечень полей для селекта (null если все)
   * @return Zend_Db_Select
   */
  protected function select($fields=null) {
    // необходимо переопределить функцию в случае поддержки CAP_SEARCH_ADVANCED
    return null;
  }

  /**
   * Поиск данных по критерию
   * @param Core_Dao_Select|array $criteria Критерий поиска, объект или конфигурация для конструктора
   * @return array
   */
  public function findAll($criteria) {
    if (!is_object($criteria)) {
      $criteria = new Core_DAO_Select($criteria);
    }
    if (is_array($criteria->select)
        && !$this->getCapability(self::CAP_SEARCH)
        && $this->getCapability(self::CAP_SEARCH_ADVANCED)
       )
    {
      $criteria->convertToAdvanced($this->select($criteria->fields));
    }
    if (is_array($criteria->select) && $this->getCapability(self::CAP_SEARCH)) {
      return $this->_findSimple($criteria);
    } elseif ($criteria->select instanceof Zend_Db_Select && $this->getCapability(self::CAP_SEARCH_ADVANCED)) {
      return $this->_findAdvanced($criteria);
    }
    throw new Exception("Объект не поддерживает такие возможности поиска");
  }

  abstract public function remove($id);

  abstract public function removeAll();

  public function getProperties() {
    return false;
  }

  public function getCapability($cap) {
    return in_array($cap, $this->_supported_caps);
  }
}
