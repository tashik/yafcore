<?php
require_once 'Util/printurl.php';
require_once 'Util/string.php';
require_once 'Util/validate_email.php';
require_once 'Util/dbcommon.php';
require_once 'Util/filename.php';
require_once 'Util/exception.php';
//require_once 'Util/stem.rus.php';

define ('SYSTEM_USER', 1);

define('ONE_DAY', 86400);
define('ONE_HOUR', 3600);
define('ONE_MINUTE', 60);
define('TZ_BASE', 4);
define('COUNTRY_RUSSIA_ISO', 643);

define('TIMEZONE_USZ1', -1*ONE_HOUR);
define('TIMEZONE_MSK', 0);
define('TIMEZONE_YEKT', 2*ONE_HOUR);
define('TIMEZONE_OMST', 3*ONE_HOUR);
define('TIMEZONE_KRAT', 4*ONE_HOUR);
define('TIMEZONE_IRKT', 5*ONE_HOUR);
define('TIMEZONE_YAKT', 6*ONE_HOUR);
define('TIMEZONE_VLAT', 7*ONE_HOUR);
define('TIMEZONE_MAGT', 8*ONE_HOUR);

define('PURGEABLE_CACHE_TTL', 60*30);
define('PERSISTENT_CACHE_TTL', ONE_DAY*30);

define('NO_PICTURE', '/images/nopic.jpg');

function aGetStemmedWords($sQuery, $sFieldName) {
	$stemmer = new Stemmer();
	$words = explode(' ', $sQuery);
	$stemmed = array();
	foreach ($words as $word)
		if (!empty($word)) {
			$stemmed_word = $stemmer->stem($word);
			if (!empty($stemmed_word))
				$stemmed[] = $sFieldName." ILIKE '%".db_escape_string($stemmed_word)."%'";
		}
	return $stemmed;
}

function datePattern($format = 'sql')
{
	switch ($format) {
		case 'date': $res = 'dd.MM.YYYY'; break;
		case 'dt': $res = 'dd.MM.YYYY HH:mm'; break;
		default: $res = 'YYYY-MM-dd HH:mm:ss'; break;
	}
	return $res;
}

function getActiveRole()
{
  //return Zend_Registry::get('role');
  $auth = Zend_Auth::getInstance();
  if(!$auth->hasIdentity())
    $auth_data=$auth->getIdentity();
  else {
    return -1;
  }
  //logVar($auth_data, 'auth_data');
  return $auth_data->role;
}

function getActiveUser()
{
  $auth = Zend_Auth::getInstance();
  if($auth->hasIdentity()) {
    $auth_data=$auth->getIdentity();
    //logVar($auth_data, 'user identity');
    return $auth_data->id;
  } else {
    return -1;
  }

}

function getActiveUserType()
{
  $auth = Zend_Auth::getInstance();
  if(!$auth->hasIdentity()) {
    return -1;
  } else {
    $auth_data = $auth->getIdentity();
  }
  if($auth_data->is_admin) {
    return Model_User::TYPE_OPERATOR;
  }

  if($auth_data->is_accountant) {
    return Model_User::TYPE_ACCOUNTANT;
  }
  return Model_User::TYPE_USER;
}

function getActiveCompany()
{
  $auth = Zend_Auth::getInstance();
  if(!$auth->hasIdentity()) {
    return -1;
  } else {
    $auth_data = $auth->getIdentity();
  }
  //logVar($auth_data['type'], 'active user type');
  return $auth_data->business_id;
}

/**
 * Возвращает статус операторства пользователя
 * @return bool
 */
function isAdmin() {
  $active_user_type = getActiveUserType();
  return $active_user_type == Model_User::TYPE_OPERATOR;
  /*$roles = Model_Acl::getUserRoles(getActiveUser());
  return in_array(Model_User::USER_ROLE_ADMIN, $roles, true);*/
}

function logout()
{
  $identity = Zend_Auth::getInstance()->getIdentity();
  if ($identity) {
    $cache = Zend_Registry::get('cache');
    if ($cache) {
      @$cache->remove('user_mandates_'.$identity->userid);
      @$cache->remove('user_'.$identity->userid);
    }
    Zend_Auth::getInstance()->clearIdentity();
  }
  $cookie_name = getConfigValue('resources->session->name', ini_get('session.name'));
  if (!$cookie_name) {
    $cookie_name = 'b2bsid';
  }
  if (isset($_COOKIE[$cookie_name])) {
    Core_Crypto_Session::setCookie('', $cookie_name, -ONE_DAY*30); // чистим куку
  }
  @session_destroy();
}

function checkPrivileges($min_role=Model_User::USER_ROLE_ADMIN, $only_approved=true)
{
  if (Model_User::USER_ROLE_GUEST!=$min_role && $only_approved)
  {
    $auth_data=Zend_Registry::get('auth_data');
    if ( !isset($auth_data['status']) || Model_User::STATUS_AUTHORIZED!=$auth_data['status'] )
    {
      $id=getActiveUser();
      $role=getActiveRole();
      $cmp=getActiveCompany();
      logStr("Пользователь id: $id, роль $role, организация $cmp, статус {$auth_data['status']}, ".
                "требуемая роль: $min_role, требуемая авторизация: ".to_string($only_approved));
      //logVar($auth_data, 'Данные пользователя');
      throw new Exception('Недостаточно прав доступа — неавторизированная роль ('.$auth_data['status'].')');
    }
  }
  $r=getActiveRole();

  //Надо фиксить если надо, пока в комменты
  /*$role_levels=array(
    USER_ROLE_GUEST=>1,
    USER_ROLE_SUBUNSIGNED=>2, USER_ROLE_CUST_SUBUNSIGNED=>2,
    USER_ROLE_CONTRACTOR=>3, USER_ROLE_SUBSIGNED=>3, USER_ROLE_CUST_SUBSIGNED=>3,
    USER_ROLE_MAIN=>4, USER_ROLE_CUST_MAIN=>4,
    USER_ROLE_ADMIN=>5, USER_ROLE_SUPPORT=>6
  );

  if ( $role_levels[$min_role]>$role_levels[$r] )
  {
    throw new Exception('Недостаточно прав доступа ('.$r.', '.$min_role.')');
  }*/
  return true;
}


function initAuthentication($login=null, $password=null)
{
  require_once 'Core/Acl.php';
  $auth=Core_Auth::getInstance();
  /*$dbStore = new Core_AuthDbStorage(Zend_Registry::get('db'), 'etpsid', 'users', 'username', 21200);
  $auth->setStorage($dbStore);*/

  if (!$auth->hasIdentity())
  {
    if(!is_null($login) && !is_null($password)) {
        $username = htmlspecialchars($login);
        $password = Core_Crypto_Hash::String(htmlspecialchars($password));
        $authAdapter = new Zend_Auth_Adapter_DbTable(Zend_Db_Table::getDefaultAdapter());
        $authAdapter->setTableName('users')
                    ->setIdentityColumn('username')
                    ->setCredentialColumn('password');
        //echo $password;
        $authAdapter->setIdentity($username)
                    ->setCredential($password);
        $result = $auth->authenticate($authAdapter);

        if($result->isValid())
        {
           $identity = $authAdapter->getResultRowObject(array('id','status','company_id','role', 'type'));
          // Генерим и устанавливаем сессионную куку
          //$cryptS = new Core_Crypto_Session();
          $SessionId = Core_Crypto_Session::GenerateSessionId(serialize($identity));
          if (false===$SessionId)
          {
            throw new Exception('Не получилось начать сеанс, попробуйте снова',405);
          }
          $timeValid = time()+21200;
          $success = setCookie('b2bsid', $SessionId, $timeValid);
          if (false===$SessionId)
          {
            throw new Exception('Не получилось установить cookie, попробуйте снова',405);
          }

          $authStorage = $auth->getStorage();
          $authStorage->write(array('userid'=>$identity->id, 'id'=>$SessionId, 'status'=>$identity->status, 'role'=>$identity->role, 'companyId'=>$identity->company_id, 'type'=>$identity->type));
          $auth_data = $authStorage->read();

          Zend_Registry::set('auth_data', $auth_data);
          Zend_Registry::set('role', $identity->role);
        } else {
            Zend_Registry::set('role', Model_User::USER_ROLE_GUEST);
            Zend_Registry::set('auth_data', array());
        }
    }  else {
        Zend_Registry::set('role', Model_User::USER_ROLE_GUEST);
        Zend_Registry::set('auth_data', array());
    }
  }
  else
  {
    $auth_data=$auth->getIdentity();
    Zend_Registry::set('auth_data', $auth_data);
    Zend_Registry::set('role', $auth_data['role']);
  }
  return $auth;
}

function logStr($str, $dest=0, $debug_level=9)
{
  $f=null;
  if ($dest) // Лог в файл
  {
    $f=APPLICATION_PATH."/../logs/{$dest}.log";
    $dest=3;
    $str.="\n";
    $str = date('c').' '.$str;
  }
  $raddr = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'?';
  $saddr = isset($_SERVER['SERVER_ADDR'])?$_SERVER['SERVER_ADDR']:'?';
  $str = "{$raddr} {$saddr} $str";
  if ($dest || !class_exists('Zend_Registry') || !Zend_Registry::isRegistered('logger')) {
    error_log($str, $dest, $f);
  } else {
    $logger = Zend_Registry::get('logger');
    $logger->log($str, $debug_level);
  }
}

function logException($e, $debug_level=Zend_Log::ERR)
{
  if ('testing'==APPLICATION_ENV) {
    return;
  }
  //$msg='Exception '.$e->getCode().': "'.$e->getMessage().'" occured at '.$e->getFile().':'.$e->getLine()."\n";
  //$msg.="Trace:\n".$e->getTraceAsString();
  logStr($e, 0, $debug_level);
}

function logVar($var, $name='Variable', $debug_level=9)
{
  logStr(ShowVar($name, $var), 0, $debug_level);
}

function logCurrentTrace($data='', $debug_level=Zend_Log::DEBUG)
{
  try
  {
    throw new Exception('Trace check '.ShowVar('user data',$data));
  }
  catch (Exception $e)
  {
    logException($e, $debug_level);
  }
}

function getCurrentTrace($data='')
{
  try
  {
    throw new Exception('Trace check '.ShowVar('user data',$data));
  }
  catch (Exception $e)
  {
    return array('message'=>$e->getMessage(), 'trace'=>$e->getTrace());
  }
}

function makeSavePath($path)
{
  if (!file_exists($path))
  {
    if ( !@mkdir($path, 0777, true) )
    {
      throw new Exception('Не могу создать каталог для сохранения',405);
    }
  }
  if ( !is_dir($path) || !is_writable($path) )
  {
    throw new Exception('Не могу сохранять файлы',405);
  }
}

function initLocale($cache=null)
{
  setlocale(LC_ALL, 'ru_RU.UTF-8');
  Zend_Locale::setDefault('ru_RU.UTF-8');
  if(!empty($cache))
    Zend_Locale::setCache($cache);
  //error_log("Locale old".setlocale(LC_ALL, 'ru_RU.UTF-8'));
  //error_log("Locale new".setlocale(LC_ALL, 'ru_RU.UTF-8'));
}

function checkUploadedFile($file, $original_name=false)
{
  require_once('Util/virus_check.php');
  require_once('Util/filename.php');

  if (is_array($file['name']))
  {
    foreach ($file['name'] as $k=>$v)
    {
      if (!empty ($file['tmp_name'][$k])) {
        $arr=array('name'=>$v, 'tmp_name'=>$file['tmp_name'][$k]);
        checkUploadedFile($arr);
      }
    }
    return true;
  } elseif (is_string($file))
  {
    $file = array('name'=>basename($file), 'tmp_name'=>$file);
    if ($original_name)
    {
      $file['name']=$original_name;
    }
  }

  $max_upload_size = 1024*1024 * intval(getConfigValue('general->upload->file_size', 10));
  $extensions = getConfigValue('general->upload->file_types', ".doc, .docx, .xls, .xlsx, .txt, .rtf, .zip, .rar, .7z, .jpg, .gif, .png, .pdf, .jpeg, .pdf, .gif");
  $extensions = str_replace(array(',', '.', ' '), array('|', '', ''), $extensions);
  $virus_check = !!getConfigValue('general->upload->virus_check', true);

  if ( !preg_match("@\.($extensions)$@i", $file['name']) )
  {
    throw new ResponseException("Некорректный тип у файла {$file['name']}!");
  }
  if ( $virus_check && !virusCheck($file['tmp_name']) )
  {
    throw new ResponseException("Файл {$file['name']} возможно содержит вредоносный код!");
  }
  $size = filesize($file['tmp_name']);
  if ( 0 == $size)
  {
    throw new ResponseException("Файл {$file['name']} имеет нулевой размер. Если документ сейчас открыт в Word или другом редакторе — закройте редактор и попробуйте снова");
  }
  if ( $size > $max_upload_size )
  {
    $s=HumanizeSize($max_upload_size);
    throw new ResponseException("Файл {$file['name']} слишком большой, максимальный допустимый размер: $s");
  }
  return true;
}

function getStoragePath()
{
  $config = Zend_Registry::get('config');
  return isset($config->general->storage_path)
           ?$config->general->storage_path
           :APPLICATION_PATH."/../data";
}

function securityViolation($info)
{
  $data=array('Информация'=>$info,
              'Post data'=>$_POST,
              'Get data'=>$_GET,
              'Url'=>$_SERVER['REQUEST_URI'],
              'User'=>getActiveUser(),
             );
  logVar($data, 'SECURITY VIOLATION');
  throw new Exception('Внимание: подобные махинации недопустимы, информация о Ваших действиях запротоколирована и передана в контролирующий орган для решения вопроса о блокировке Вашей учетной записи');
}

function getServerAddress()
{
  $config = Zend_Registry::get('config');
  if ( isset($config->general->etp->url)&&$config->general->etp->url )
  {
    $addr = $config->general->etp->url;
    if (substr($addr, strlen($addr)-1, strlen($addr)) == '/')
      $addr = substr($addr, 0, strlen($addr)-1);
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT']!=80)
      $addr = $addr . ':' . $_SERVER['SERVER_PORT'];
    return $addr;
  }
  $addr='http://';
  if ($_SERVER['HTTPS'] || (isset($config->general->etp->https)&&$config->general->etp->https) )
  {
    $addr='https://';
  }
  if (isset($config->general->etp->address)&&$config->general->etp->address)
  {
    $addr.=$config->general->etp->address;
  }
  else
  {
    $addr.=$_SERVER['HTTP_HOST'];
  }
  return $addr;
}

function redirect_to($url)
{
  header("HTTP/1.0 302 Found");
  $config = Zend_Registry::get('config');
  if (false===strpos($url, '://'))
  {
    $host  = $_SERVER['HTTP_HOST'];
    $proto = 'http';
    if ($_SERVER['HTTPS'] || (isset($config->general->etp->https)&&$config->general->etp->https) )
    {
      $proto='https';
    }
    if (isset($_SERVER['X_FRONTEND_HTTPS']))
    {
      if ('on'==$_SERVER['X_FRONTEND_HTTPS'])
      {
        $proto='https';
      }
      else
      {
        $proto='http';
      }
    }
    $url= "$proto://".str_replace('//', '/', "{$host}/{$url}");
  }
  header("Location: $url");
  echo "Document moved <a href=\"$url\">here</a>";
  exit;
}

function createSortLimitFromPost($q=false) {
  if (false===$q) {
    $q = $_POST;
  }
  if (isset($q['start'])) {
    $offset = $q['start'];
  } else {
    $offset = 0;
  }
  if (isset($q['limit'])) {
    $limit = $q['limit'];
  } else {
    $limit = false;
  }
  if (isset($q['sort'])) {
    $sort = explode(' ', $q['sort']);
  }
  if (isset($q['dir'])) {
    $dir = $q['dir'];
  } else {
    $dir = 'asc';
  }
  $nulls = '';
  if (isset($q['nulls']) && 'last'==$q['nulls']) {
    $nulls = 'NULLS LAST';
  }
  $order = array();
  if (isset($sort) && !empty ($sort)) {
    foreach ($sort as $s) {
      $s = mb_trim($s);
      if (''==$s) {
        continue;
      }
      $s = str_replace('.', '"."', db_escape_string($s));
      $dir = db_escape_string($dir);
      $nulls = db_escape_string($nulls);
      $order[] = new Zend_Db_Expr("\"$s\" $dir $nulls");
    }
  }
  return array('limit'=>$limit, 'offset'=>$offset, 'order'=>$order);
}

function isFileReadable($file)
{
  return @file_exists($file) && @is_readable($file) && @is_file($file);
}

function  getConfigValue($path, $default=null)
{
  $path = explode('->', $path);
  $config = array();
  if (class_exists('Zend_Registry'))
  {
    $config = Zend_Registry::get('config');
    $config = $config->toArray();
  } else
  {
    global $CONFIG;
    $config = $CONFIG;
  }
  foreach($path as $p)
  {
    $p = trim($p);
    if (!isset($config[$p]))
    {
      return $default;
    }
    $config = $config[$p];
  }
  return $config;
}

function  getArrayValue($array, $key, $default=null)
{
  if(isset($array[$key])) {
    return $array[$key];
  }
  return $default;
}

function handleException($e, $json=true, $view=null)
{
  logException($e);
  $msg = 'Ошибка '.$e->getCode().': '.$e->getMessage();
  if ($json)
  {
    $res['success']=false;
    $res['msg']=$msg;
    echo json_encode($res);
    exit;
  }
  else
  {
    $view->error_msg = $msg;
  }
}

function alignTimestamp($ts, $align_to='day')
{
  if (empty($ts))
    return null;
  /*switch ($align_to)
  {
    case 'day':
      $align = 86400;
      break;
    default:
      throw new Exception('Unknown align type!');
  }*/
  $align=0;
  $date = db_timestamp_to_date($ts+$align);
  if  (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}[.0-9]*$/', $date))
  {
  	preg_match('/^([\d]{4})-([\d]{2})-([\d]{2}) ([\d]{2}):([\d]{2}):([\d]{2})[.0-9]*$/', $date, $date);
  	//$date=strptime($date, '%Y-%m-%d %H:%M:%S');
    switch ($align_to) {
      case 'day':
        $time=mktime(23, 59, 0, intval($date[2]), intval($date[3]), intval($date[1]));
        break;
      case 'zeroday':
        $time=mktime(0, 0, 0, intval($date[2]), intval($date[3]), intval($date[1]));
        break;
      case 'zeromonth':
        $time=mktime(0, 0, 0, intval($date[2]), 1, intval($date[1]));
        break;
      case 'quarter':
        $m = 1 + 3*(1+ intval((intval($date[2])-1)/3));
        $y=intval($date[1]);
        if ($m>12) {
          $m-=12;
          $y++;
        }
        $time=mktime(0, 0, 0, $m, 1, $y)-1;
        break;
      case 'zeroquarter':
        $m = 1+3*intval((intval($date[2])-1)/3);
        $time=mktime(0, 0, 0, $m, 1, intval($date[1]));
        break;
      default:
        throw new Exception("Внутренняя ошибка, некорректный аргумент $align_to");
    }
    return $time;
  }
  return false;
}

function alignDate($date, $align='day') {
  $stamp = alignTimestamp(db_date_to_timestamp($date), $align);
  if (empty($stamp))
    return null;
  $pattern = 'Y-m-d H:i:s';
  return date($pattern,$stamp);
}

$work_days_map=null;

function isWorkDay($ts)
{
  global $work_days_map;
  if (null===$work_days_map) {
    $db = Zend_Registry::get('db');
    $workdays = $db->fetchAll('SELECT date,is_workday FROM holidays', array(), Zend_Db::FETCH_ASSOC);
    $work_days_map=array();
    foreach ($workdays as $d) {
      $work_days_map[$d['date']]=$d['is_workday'];
    }
  }
  $date = date('Y-m-d', $ts);
  if (isset($work_days_map[$date])) {
    return $work_days_map[$date];
  }
  $day = date('D', $ts);
  if ('Sun'==$day || 'Sat'==$day) {
    return false;
  }
  return true;
}

function addWorkDays($date, $days, $is_timestamp=true)
{
  if (!$is_timestamp) {
    $date = toTimestamp($date);
  }
  while ($days>0) {
    do {
      $date+=86400;
    } while (!isWorkDay($date));
    $days--;
  }
  if (!$is_timestamp) {
    $date = db_timestamp_to_date($date);
  }
  return $date;
}

function addDays($date, $days, $is_timestamp=true)
{
  if (!$is_timestamp) {
    $date = toTimestamp($date);
  }
  $date+=86400*$days;
  while (!isWorkDay($date)) {
    $date+=86400;
  }
  if (!$is_timestamp) {
    $date = db_timestamp_to_date($date);
  }
  return $date;
}

function getServerUrl() {
  $url = getConfigValue('general->site_url', 'http://'.$_SERVER['HTTP_HOST']);
  if ('/'!=substr($url, -1, 1))
  {
    $url.='/';
  }
  return $url;
}

function getDirEntries($dir) {
  $h = @dir($dir);
  if (!$h) {
    return false;
  }
  $entries = array();
  while (false !== ($entry = $h->read())) {
    if ('.'!=$entry && '..'!=$entry) {
      $entries[]=$entry;
    }
  }
  $h->close();
  return $entries;
}

function getCmpStatuses() {
  return array(
    Model_Contragent::STATUS_NOT_APPROVED=>'Неавторизован',
    Model_Contragent::STATUS_APPROVED=>'Авторизован',
    Model_Contragent::STATUS_BLOCKED=>'Заблокирован',
  );
}

 function makeListString($list)
  {
    $ret='|';
    foreach($list as $v)
    {
      if (!empty($v))
        $ret.=$v.'|';
    }
    return $ret;
  }

function splitList($list)
  {
    $list=explode('|', $list);
    $ret=array();
    foreach($list as $v)
    {
      $v=trim($v);
      if (''!=$v && 0<(int)$v)
      {
        $ret[]=(int)$v;
      }
    }
    return $ret;
  }

function getActiveIpAddress() {
  return $_SERVER['REMOTE_ADDR'];
}

function updateResource($table, $data, $check_tables) {
  if (!isset($data[0])) {
    $data = array($data);
  }
  $updated = array();
  $db = Zend_Registry::get('db');
  $t = new Zend_Db_Table($table);
  $cols = $t->info('cols');
  $cols = array_flip($cols);
  foreach ($data as $r) {
    if (isset($r['id']) && intval($r['id'])>0) {
      $skip = false;
      foreach ($check_tables as $t) {
        if (isset($r[$t]) && ('' ===$r[$t]||null===$r[$t])) {
          $skip = true;
          break;
        }
      }
      if ($skip) {
        continue;
      }
      $rowdata = array();
      foreach ($r as $k=>$v) {
        if ('id'==$k || !isset($cols[$k])) {
          continue;
        }
        if (is_bool($v)) {
          $v = $v?1:0;
        }
        $rowdata[$k] = $v;
      }
      $db->update($table, $rowdata, $db->quoteInto("id=?", intval($r['id'])));
      $updated[] = array_merge($r, array('id'=>$r['id']));
    } elseif (!isset($r['id']) || 0==$r['id']) {
      $skip = false;
      foreach ($check_tables as $t) {
        if (!isset($r[$t]) || ('' ===$r[$t]||null===$r[$t])) {
          $skip = true;
          break;
        }
      }
      if ($skip) {
        continue;
      }
      $rowdata = array();
      foreach ($r as $k=>$v) {
        if ('id'==$k || !isset($cols[$k])) {
          continue;
        }
        if (is_bool($v)) {
          $v = $v?1:0;
        }
        $rowdata[$k] = $v;
      }
      $db->insert($table, $rowdata);
      $id = $db->lastInsertId($table.'_id');
      $updated[] = array_merge($r, array('id'=>$id));
    }
  }
  return $updated;
}

function deleteResources($table, $resources) {
  $db = Zend_Registry::get('db');
  if (!is_array($resources)) {
    $resources = array($resources);
  }
  foreach ($resources as $k=>$v) {
    $resources[$k] = intval($v);
  }
  $ids = join(',', $resources);
  $db->delete($table, "id in ($ids)");
  return $resources;
}

function linkToStatic($file, $prefix='') {
  $localfile = APPLICATION_PATH.'/../public/'.$file;
  if ('production'==APPLICATION_ENV) {
    $mtime = @filemtime($localfile);
    if ($mtime) {
      return "{$prefix}$file?_dc=$mtime";
    }
  }
  return "{$prefix}$file";
}

/**
 * Форматирует строку с датой/временем, использует форматтер date
 *
 * @param string $date Дата
 * @param string $format формат для даты, см. date()
 * @return string отформатированная строка с датой или null, если изначальная строка пуста
 */
function formatDate($date, $format="Y-m-d H:i:s") {
  if (empty($date)) {
    return null;
  }
  $date = toTimestamp($date);
  return date($format, $date);
}

function optStr($name, $value) {
  $value=mb_trim($value);
  if (!empty($value)) {
    if (false===$name) {
      return $value;
    } else {
      return "$name: $value\n";
    }
  }
  return '';
}

function reqStr($name, $value) {
  $value=mb_trim($value);
  if (!empty($value)) {
    if (false===$name) {
      return $value;
    } else {
      return "$name: $value\n";
    }
  }
  throw new ErrorException("Отсутствует обязательное поле '$name'!");
}

function headStr($name) {
  return "\n$name\n";
}

function getTimezoneNameByOffset($offset) {
  $tz_map = array(
    TIMEZONE_USZ1 => "Europe/Kaliningrad",
    TIMEZONE_MSK => false,
    TIMEZONE_YEKT => 'Asia/Yekaterinburg',
    TIMEZONE_OMST => 'Asia/Omsk',
    TIMEZONE_KRAT => 'Asia/Krasnoyarsk',
    TIMEZONE_IRKT => 'Asia/Irkutsk',
    TIMEZONE_YAKT => 'Asia/Yakutsk',
    TIMEZONE_VLAT => 'Asia/Vladivostok',
    TIMEZONE_MAGT => 'Asia/Magadan',
  );
  if ($offset && isset($tz_map[$offset])) {
    return $tz_map[$offset];
  }
  return false;
}
/**
 * Заглушка для метода с длинными именами. Убрать если длинные имена не нужны
 * @param <type> $offset
 * @return string
 */
function getTimezoneLongNameByOffset($offset) {
  $tz_map = array(
    TIMEZONE_USZ1 => "Europe/Kaliningrad",
    TIMEZONE_MSK => false,
    TIMEZONE_YEKT => 'Asia/Yekaterinburg',
    TIMEZONE_OMST => 'Asia/Omsk',
    TIMEZONE_KRAT => 'Asia/Krasnoyarsk',
    TIMEZONE_IRKT => 'Asia/Irkutsk',
    TIMEZONE_YAKT => 'Asia/Yakutsk',
    TIMEZONE_VLAT => 'Asia/Vladivostok',
    TIMEZONE_MAGT => 'Asia/Magadan',
  );
  if ($offset && isset($tz_map[$offset])) {
    return $tz_map[$offset];
  }
  return false;
}


/**
 * Читает текст объявления из файла и возвращает строку
 * либо false при ошибке.
 * @return String/Boolean Текст объявления или false.
 */
function getAnnouncementText()
{
  // TODO: Можно реализовать читку из кеша (если в памяти, будет быстрее),
  //       но тогда при записи кеш должен обновляться/сбрасываться.
  $announcement = null;
  $path = getConfigValue('announcement->file');
  if ($path && file_exists($path)) {
    $announcement = @file_get_contents($path);
  }
  return $announcement;
}

function array_merge_overwrite(array $Arr1, array $Arr2 = null) {
  foreach ($Arr2 as $key => $Value) {
    if (array_key_exists($key, $Arr1) && is_array($Value)) {
      $Arr1[$key] = array_merge_overwrite($Arr1[$key], $Arr2[$key]);
    } else {
      $Arr1[$key] = $Value;
    }
  }

  return $Arr1;
}

function putDownloadHeaders($filename, $content_type='text/plain; charset=windows-1251')
{
  header("Content-Type: $content_type");
  header("Pragma: public");
  header("Expires: 0");
  header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  header("Content-Disposition: attachment".($filename?"; filename=$filename":''));
  header('HTTP/1.0 200 OK');
}

function generateUUID($entropy_pool = false) {
  $entropy = func_get_args();
  $entropy[] = microtime();
  $entropy[] = uniqid(rand(), true);
  $charid = md5(json_encode($entropy));
  $uuid = substr($charid, 0, 8).'-'
         .substr($charid, 8, 4).'-'
         .substr($charid,12, 4).'-'
         .substr($charid,16, 4).'-'
         .substr($charid,20,12);
  return $uuid;
}

function generateAccount($id) {
  $mod1=11;
  $mod2=10;
  $n=sprintf('%07d', $id);
  $string = getConfigValue('general->etp_id', '10').$n;

  $sum=0;
  $weights=array(2,4,10,3,5,9,4,6,8,0);
  for($i=0; $i<strlen($string); $i++)
  {
    if (isset($weights[$i]))
    {
      $sum+=intval($string[$i])*$weights[$i];
    }
  }
  $sum%=$mod1;
  $sum%=$mod2;
  return $string.$sum;
}

function array_search_assoc($array, $key, $value) {
  foreach ($array as $k=>$v) {
    if (!is_array($key)) {
      if ($v[$key] === $value) {
        return $k;
      }
    } else {
      $found = true;
      foreach ($key as $ki=>$kk) {
        $vv = is_array($value)?$value[$ki]:$value;
        if ($v[$kk] !== $vv) {
          $found = false;
          break;
        }
      }
      if ($found) {
        return $k;
      }
    }
  }
  return false;
}

/**
 * Очистка всех глобальных данных и состояний привязанных к запросу, подготовка
 * к следующему запросу в рамках одного соединения
 */
function resetApplication() {
  if (DbTransaction::$in_transaction) {
    logVar(DbTransaction::$transactions_trace, 'Warning: transaction is not closed!');
    DbTransaction::rollback();
  }
  Zend_Registry::set('warnings', null);
}

/**
 * Преобразовывает объекты в массив. Примитивы превращает в массив с одним элементом. Действует рекурсивно
 * @param mixed $var
 * @return array
 */
function toArray($var) {
  if (is_object($var)) {
    $var = get_object_vars($var);
  }
  if (is_array($var)) {
    foreach ($var as $k=>$v) {
      if (is_object($v) || is_array($v)) {
        $var[$k] = toArray($v);
      }
    }
  } else {
    $var = array($var);
  }

  return $var;
}

function getContentType($filename) {
  if (function_exists('finfo_open')) {
    $r = finfo_open();
    return finfo_file($r, $filename, FILEINFO_MIME_TYPE);
  } else {
    return @mime_content_type($filename);
  }
}

function isDebug() {
  if (defined('DEBUG') || defined('LOUD_DEBUG')) {
    return true;
  }
  return APPLICATION_ENV!='production';
}

function simplexml2array($xml) {
   if (is_object($xml) && get_class($xml) == 'SimpleXMLElement') {
     $attributes = $xml->xpath('@*');
     //fputs(STDERR, ShowVar('xml attrs', xml2array($attributes->asXML())));
     //fputs(STDERR, ShowVar('xml attrs', $attributes->asXML()));
     $childs = $xml->xpath('*');
     foreach($attributes as $k=>$v) {
       $k = $v->getName();
       //fputs(STDERR, "$k => $v");
       if ($v) {
         $a[$k] = (string) $v;
       }
     }
     //$x = $xml;
     //$xml = get_object_vars($xml);
   }
   $keys = array();
   if (count($childs) == 0) {
     $r = (string) $xml; // for CDATA
   } else {
     foreach($childs as $child) {
       $key = $child->getName();
       //$keys[] = $key;
       $v = simplexml2array($child);
       if (isset($r[$key])) {
         if (!is_array($r[$key]) || !isset($r[$key][0])) {
           $r[$key] = array($r[$key]);
         }
         $r[$key][] = $v;
       } else {
         $r[$key] = $v;
       }
     }
   }
   if (isset($a)) {
     //fputs(STDERR, ShowVar('r', $r));
     if (is_array($r)) {
       $r['@'] = $a;    // Attributes
     }
     if (isset($a['type'])) {
       $type = $a['type'];
       $p = strpos($type, ':');
       if ( false!==$p) {
         $type = substr($type, $p+1);
       }
       switch (MyStrToLower($type)) {
         case 'array':
           $v = array();
           if (!is_array($r)) {
             $r = array($r);
             $keys = array(0);
           }
           foreach ($r as $key=>$val) {
             if ('@'==$key) {
               continue;
             }
             //fputs(STDERR, "$key ");
             if (is_array($val) && isNumericArray($val)) {
               $v = array_merge($v, $val);
             } else {
               $v[] = $r[$key];
             }
           }
           $r = $v;
           break;
         case 'int':
         case 'integer':
           $r = intval($r);
           break;
         case 'bool':
         case 'boolean':
           $r = ($r=='true');
           break;
         case 'decimal':
         case 'float':
           $r = floatval($r);
           break;
       }
     }
   }
   return $r;
}

/**
 * Проверяет, находится ли IP адрес среди указанных в списке адресов.
 * Адреса в списке могут быть как простыми IP адресами ('127.0.0.1'), так и
 * с маской отдельных элементов ('127.*.*.*'). IPv6 адреса [пока] не поддерживаются
 * @param string $ip IP который проверить
 * @param array|string $blocks список адресов для проверки, массивом или строкой через пробел
 * @return bool true если есть, false если нету
 */
function isIPInBlock($ip, $block=array()) {
  $in_block = false;
  $ip = mb_trim($ip);
  if (!is_array($block)) {
    $block = explode(' ', $block);
  }
  foreach ($block as $cip) {
    $cip = preg_replace('@[^0-9.*]+@', '', $cip);
    $cip = str_replace(array('.', '*'), array('\.', '[0-9]+'), $cip);
    if (preg_match("@^{$cip}$@", $ip)) {
      $in_block = true;
      break;
    }
  }
  return $in_block;
}

/**
 * Запрос данных по HTTP
 * @param string $url Урл для запроса (не забывайте ескейпить параметры через urlencode)
 * @param array $opts дополнительные опции для CURL вида array(опция=>значение)
 * @return string данные по указаному адресу
 */
function fetchUrl($url, $opts=array()) {
  if (!function_exists('curl_init')) {
    throw new Exception("Отсутствует необходимое расширение: CURL");
  }
  $handle = curl_init($url);
  if (!$handle) {
    throw new Exception("Ошибка инициализации ресурса CURL");
  }
  curl_setopt($handle, CURLOPT_USERAGENT, getConfigValue('general->user_agent', 'cm9zZWx0b3Jn01'));
  foreach ($opts as $opt=>$value) {
    curl_setopt($handle, $opt, $value);
  }
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($handle);
  if (false===$result) {
    throw new Exception(curl_errno($handle).": ".curl_error($handle));
  }
  $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
  if ($code>=400) {
    throw new Exception("Ошибка запроса: код возврата сервера $code", $code);
  }
  return $result;
}

/**
 * Получить объект кеша
 * @param bool $prefer_shared Попытаться получить шарящийся между нодами кеш
 * @return Zend_Cache_Core
 */
function getCacheInstance($prefer_shared = false) {
  if ($prefer_shared && Zend_Registry::isRegistered('shared_cache')) {
    return Zend_Registry::get('shared_cache');
  }
  return Zend_Registry::get('cache');
}

function convertArrayToModel(array $array) {
  $result = array();
  foreach ($array as $key => $value) {
    $result[] = array('id' => $key, 'name' => $value);
  }
  return $result;
}

/**
 * Получаем список систем (ЭДО, ЭТП и т.д)
 */
function getSystems() {
  return getReferences('systems');
}

/**
 * Возвращает список коротких словарей (системы, типы бизнесов и т.п.)
 *
 * @param string|null $name Имя словаря
 * @return array|null
 */
function getReferences($name = null) {
  $cache = Zend_Registry::get('cache');
  $key   = 'aReferences';
  $result = $cache->load($key);
  if (!$result) {
    require_once('Util/references.php');
    /** @var array $aReferences */
    $cache->save($aReferences, $key);
    $result = $aReferences;
  }
  if (null === $name) {
    return $result;
  }

  if (isSet($result[$name])) {
    return $result[$name];
  }
  return null;
}

/**
 *
 * считает сумму по колонке таблицы
 *
 * @param $column
 * @param $array
 * @return bool|float|int|mixed|string
 */
function columnSum($column, $array) {
  $sum = 0;
  foreach ($array as $row) {
    if (isset($row[$column])) {
      if (is_array($row[$column]) && isset($row[$column]['v'])) {
        $sum += filterPrice($row[$column]['v']);
      } else {
        $sum += filterPrice($row[$column]);
      }
    }
  }
  return $sum;
}

function guid() {
  if (function_exists('com_create_guid') === true) {
    return trim(com_create_guid(), '{}');
  }

  return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 65535),
    mt_rand(0, 65535),
    mt_rand(0, 65535),
    mt_rand(16384, 20479),
    mt_rand(32768, 49151),
    mt_rand(0, 65535),
    mt_rand(0, 65535),
    mt_rand(0, 65535));
}