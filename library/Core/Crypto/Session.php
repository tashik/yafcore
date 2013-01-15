<?php

class Core_Crypto_Session
{
  private static $_userid = null;
  private static $_ip = '';
  private static $_accessed = 'NOW';
  private static $_session_id = false;
  private static $_disable_save = false;
  private static $_lock_ip = false;
  private static $_session_data = null;
  private static $_cookie_name = 'b2bsid';
  private static $_table_name = 'sessions';

  static protected function _regenerateSessionId()
  {
    $str = getConfigValue('general->cryptosecret', '1234567');
    $str .= microtime();
    if (isset($_SERVER)) {
      $str .= json_encode($_SERVER);
    }
    if (isset($_GET)) {
      $str .= json_encode($_GET);
    }
    //logStr($str);
    $str = base64_encode(sha1($str, true));
    $str = str_replace(array('+', '/', '='), array('-', '_', ''), $str);
    session_id($str);
  }

  static protected function _clearSessionId()
  {
    self::setCookie('', false, -ONE_DAY*30);
  }

  static protected function _getUserIP()
  {
    $t = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'local';
    if (isset($_SERVER['FORWARDED_FOR']))
    {
      $t .= ':'.$_SERVER['FORWARDED_FOR'];
    }
    return $t;
  }

  static public function sessionOpen($save_path, $session_name)
  {
    //logVar($session_name, 'Session OPEN');
    return true;
  }

  static public function sessionClose()
  {
    self::$_userid = null;
    self::$_ip = '';
    self::$_accessed = null;
    self::$_session_id = false;
    //logVar(null, 'Session CLOSE');
    return true;
  }

  static protected function _logError($msg, $uid) {
    logVar("Session log {$uid}: $msg");
    //if (!isset($_POST['login'])||''==$_POST['login']) {
    //  $_POST['login'] = "$uid";
    //}
    //Model_Syslog::addEventLog($msg, 'login', array('userid' => $uid));
  }

  static public function sessionRead($id)
  {
    $row = null;
    if (self::$_session_data && isset(self::$_session_data[$id])) {
      $row = self::$_session_data[$id];
    } else {
      $db = getDbInstance();
      $rows = $db->fetchAll("SELECT * FROM ".self::$_table_name." WHERE id=?", array($id), Zend_Db::FETCH_ASSOC);
      if (count($rows)) {
        $row = array_shift($rows);
      }
    }
    //logVar($rows, 'Session READ');
    //logCurrentTrace();
    if ($row) {
      $ip = self::_getUserIP();
      if ($row['userid'] && $row['lock_ip'] && $row['ip']!==$ip) {
        // Не совпадает IP
        self::_logError("Доступ к сессии запрещен, т.к. IP сессии ({$row['ip']}) не совпадает с IP пользователя ({$ip})", $row['userid']);
        self::_clearSessionId();
        self::$_disable_save = true;
        return '';
      }
      $expiration = getConfigValue('general->session->expire_time', ONE_DAY);
      if ($row['userid'] && $row['accessed'] && toTimestamp($row['accessed'])+$expiration<time() ) {
        // Превышено время сессии
        self::_logError("Время сессии истекло, сессия закрыта", $row['userid']);
        self::sessionDestroy($row['id']);
        self::_clearSessionId();
        return '';
      }
      self::$_userid = $row['userid'];
      self::$_ip = $row['ip'];
      self::$_accessed = $row['accessed'];
      self::$_lock_ip = $row['lock_ip'];
      self::$_session_id = $id;
      return $row['data'];
    }
    return '';
  }

  static public function sessionWrite($id, $sess_data)
  {
    if (self::$_disable_save) {
      return true;
    }
    if (null!==self::$_session_data
        && isset(self::$_session_data[$id]['data'])
        && self::$_session_data[$id]['data']===$sess_data
        && self::$_session_data[$id]['userid']===self::$_userid
        && self::$_session_data[$id]['ip']===self::$_ip
        && self::$_session_data[$id]['accessed']===self::$_accessed
        && self::$_session_data[$id]['lock_ip']===self::$_lock_ip
       )
    {
      return true;
    }
    $db = Zend_Registry::get('db');
    if (''==self::$_ip) {
      self::$_ip = self::_getUserIP();
    }
    $data = array('data'=>$sess_data,
                  'userid'=>intval(self::$_userid),
                  'ip'=>self::$_ip,
                  'lock_ip' => self::$_lock_ip?1:0,
                  'accessed'=>self::$_accessed,
                 );
    //logVar($data, 'session WRITE');
    //logVar($sess_data, "sessionWrite $id {$_SERVER['REQUEST_URI']}");
    $rows = $db->update(self::$_table_name, $data, $db->quoteInto("id = ?", $id));
    if (0==$rows) {
      $data['id'] = $id;
      $db->insert(self::$_table_name, $data);
    }
    return true;
  }

  /**
   * Установить куку
   * @param string $value значение куки
   * @param string $name имя куки (если false то будет использовано имя сессии)
   * @param int $lifetime _относительное_ время жизни куки (если false, то будет взято время жизни сессии из конфига)
   */
  static public function setCookie($value, $name=false, $lifetime=false) {
    if (false===$lifetime) {
      $lifetime = getConfigValue('resources->session->cookie_lifetime', ONE_DAY);
    }
    if (!$name) {
      $name = self::$_cookie_name;
    }
    setcookie($name, $value, time()+$lifetime);
  }

  static public function setUserInfo($userid, $userip=null, $accessed='NOW', $opts=array()) {
    $default_opts = array('lock_ip'=>false);
    $opts = array_merge($default_opts, $opts);
    //logVar($userid, 'SET userid');
    self::$_userid = $userid;
    self::$_ip = (null==$userip)?self::_getUserIP():$userip;
    self::$_accessed = $accessed;
    self::$_lock_ip = $opts['lock_ip'];
    if (!headers_sent() && 'NOW'==$accessed && self::$_session_id) {
      self::setCookie(self::$_session_id); // обновляем куку
    }
  }

  static public function getUserId() {
    return self::$_userid;
  }

  static public function getUserIp() {
    return self::$_ip;
  }

  static public function getUserAccessed() {
    return self::$_accessed;
  }

  static public function getSessionId() {
    return self::$_session_id;
  }

  static public function sessionDestroy($id)
  {
    if (self::$_disable_save) {
      return true;
    }
    $db = Zend_Registry::get('db');
    $db->delete(self::$_table_name, $db->quoteInto("id = ?", $id));
    return true;
  }

  static public function sessionGc($maxlifetime)
  {
    return true;
  }

  static public function sessionNull($arg1=null, $arg2=null)
  {
    //logVar($arg1, 'Session NULL');
    return true;
  }

  static public function sessionNullRead($arg1=null, $arg2=null)
  {
    return '';
  }

  static public function finalize() {
    //logStr('Session::finalize');
    session_write_close();
  }

  static public function initHandlers() {
    if (isset($_SERVER['REQUEST_URI']) && '/'==$_SERVER['REQUEST_URI']) {
      self::$_disable_save = true;
    }
    //$auth_data = Zend_Registry::get('auth_data');
    session_set_cookie_params(getConfigValue('resources->session->cookie_lifetime', ONE_DAY));
    self::$_cookie_name = getConfigValue('resources->session->name', ini_get('session.name'));
    self::$_table_name = getConfigValue('resources->session->saveHandler->options->name', 'sessions');
    session_name(self::$_cookie_name);
    $id = isset($_COOKIE[self::$_cookie_name])?$_COOKIE[self::$_cookie_name]:'';
    if (strlen($id)<16 || strlen($id)>32) {
      //logVar(session_id(), 'changing session id');
      self::_regenerateSessionId();
    } else {
      // Проверяем, существует ли в базе сессия, которую запросили
      $db = getDbInstance();
      $rows = $db->fetchAll("SELECT * FROM ".self::$_table_name." WHERE id=?", array($id), Zend_Db::FETCH_ASSOC);
      if (isset($rows[0])) {
        // Кешируем данные, чтобы не дергать базу второй раз при чтении сессии
        self::$_session_data = array($id=>$rows[0]);
      } else {
        // Если не существует — перегенериваем id сессии, чтобы он был новым
        // Цель — безопасность. Если каким-то образом сессию пользователя перехватили и прибили,
        // то юзеру подсунется новая, рандомная, сессия.
        self::_regenerateSessionId();
      }
    }
    //session_id($auth_data['id']);
    session_set_save_handler("Core_Crypto_Session_sessionOpen",
                             "Core_Crypto_Session_sessionClose",
                             "Core_Crypto_Session_sessionRead",
                             "Core_Crypto_Session_sessionWrite",
                             "Core_Crypto_Session_sessionDestroy",
                             "Core_Crypto_Session_sessionGc");
  }
}

function Core_Crypto_Session_sessionOpen($save_path, $session_name) {
  return Core_Crypto_Session::sessionOpen($save_path, $session_name);
}
function Core_Crypto_Session_sessionClose() {
  if (class_exists('Core_Crypto_Session', false)) {
    return Core_Crypto_Session::sessionClose();
  }
  return true;
}
function Core_Crypto_Session_sessionRead($id) {
  return Core_Crypto_Session::sessionRead($id);
}
function Core_Crypto_Session_sessionWrite($id, $sess_data) {
  if (class_exists('Core_Crypto_Session', false)) {
    return Core_Crypto_Session::sessionWrite($id, $sess_data);
  }
  return true;
}
function Core_Crypto_Session_sessionDestroy($id) {
  if (class_exists('Core_Crypto_Session', false)) {
    return Core_Crypto_Session::sessionDestroy($id);
  }
  return true;
}
function Core_Crypto_Session_sessionGc($maxlifetime) {
  if (class_exists('Core_Crypto_Session', false)) {
    return Core_Crypto_Session::sessionGc($maxlifetime);
  }
  return true;
}
