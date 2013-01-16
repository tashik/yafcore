<?php
require_once 'Util/functions.php';
require_once 'Core/Debug.php';

class ZF_Bootstrap extends Extended_Bootstrap_Abstract {

  protected $_main_module = 'com';

  protected function _initView()
  {
    //fputs(STDERR, "_initView\n");
    Core_Debug::getGenerateTime('initView start');
    $this->bootstrap('Config');
    $this->bootstrap('ErrorHandler');
    $this->bootstrap('RegistryItems');
    $this->bootstrap('DefaultLocale');

    // Определяем основной модуль
    $main_module = getConfigValue('general->main_module', 'com');
    $this->_main_module = $main_module;

    $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
    $viewRenderer->init();
    $view = $viewRenderer->view;
    $view->Doctype()->setDoctype('HTML5');
    $view->setEncoding('UTF-8');
    $view->main_module = $main_module;
    $view->addHelperPath(APPLICATION_PATH.'/../library/Core/View/Helper', 'Core_View_Helper');
    if ( !isset($_REQUEST['rpctype']) || $_REQUEST['rpctype']!='direct' ) {
      $view->headMeta()->setName('Content-Type', 'text/html; charset=UTF-8');
      $view->headMeta()->setHttpEquiv('Content-Type', 'text/html; charset=UTF-8');
      $view->headMeta()->setCharset('UTF-8');
      $view->headTitle($this->_config->general->site_title);
    }
    Core_Debug::getGenerateTime('initView end');
  }

  protected function _initAutoload()
  {
    //fputs(STDERR, "_initAutoload\n");
    if ( APPLICATION_ENV == 'testing' ) {
      return;
    }
    $modelLoader = new Zend_Application_Module_Autoloader(array(
                    'namespace' => '',
                    'basePath' => APPLICATION_PATH));

    $autoloader = Zend_Loader_Autoloader::getInstance();
    /*$autoloader->setDefaultAutoloader(create_function('$class',
        "include str_replace('_', '/', \$class) . '.php';"));* /
    $autoloader->setDefaultAutoloader(array("My_NameScheme_Autoload", "classAutoloader"));*/
    $autoloader->registerNamespace('Core');
    $autoloader->registerNamespace('Queue');
    $autoloader->registerNamespace('DbTable');
    $autoloader->registerNamespace('Cli');
    //$autoloader->registerNamespace('Model');
    return $modelLoader;
  }

  protected function _initConfig() {
    //fputs(STDERR, "_initConfig\n");
    $this->bootstrap('Cache');
    if ('testing'==APPLICATION_ENV) {
      $config = new Zend_Config($this->getOptions());
      setRegistryItem('config', $config);
    } else {
      $config = getRegistryItem('config');
    }
    //$this->bootstrap('Logger');
    $this->_config = $config;
    return $config;
  }

  protected function _initTimezone() {
    date_default_timezone_set(getConfigValue('general->timezone', 'Europe/Moscow'));
  }

  protected function _initDatabase() {
    //fputs(STDERR, "_initDatabase\n");
    $this->bootstrap('Config');
    $this->bootstrap('db');

    $db = $this->getResource('db');

    $db->setFetchMode(Zend_Db::FETCH_ASSOC);
    Zend_Db_Table_Abstract::setDefaultAdapter($db);
    Zend_Db_Table_Abstract::setDefaultMetadataCache($this->_cache);
    setRegistryItem('db', $db);
  }

  protected function _initSession() {
    if (!isset($_SERVER['REQUEST_METHOD'])) {
      return true;
    }
    $this->bootstrap('Database');
    if ('Core_Crypto_Session' == getConfigValue('resources->session->saveHandler->class')) {
      Core_Crypto_Session::initHandlers();
      $this->need_close_session = true;
    } else {
      $this->need_close_session = false;
      $cookie_name = getConfigValue('resources->session->name', ini_get('session.name'));
      if (isset($_COOKIE[$cookie_name]) && (strlen($_COOKIE[$cookie_name])<4||strlen($_COOKIE[$cookie_name])>32)) {
        unset($_COOKIE[$cookie_name]);
      }
      $session = $this->getPluginResource('session');
      $session->init();
    }
    Zend_Session::start();
    return true;
  }

  protected function _initLogger() {
    $this->bootstrap('Config');
    if (php_sapi_name() == 'cli' ||!isset($_SERVER['REMOTE_ADDR']) || empty($_SERVER['REMOTE_ADDR'])) {
      // т.е. это консольный запуск
      $this->unregisterPluginResource('log');
      $log_cfg = getConfigValue('resources->log');
      $logger = new Zend_Log();
      $logger->addWriter(array('writerName'=>'Stream', 'writerParams'=>array('stream'=>'php://stderr')));
      foreach ($log_cfg as $name=>$l) {
        try {
          $logger->addWriter($l);
        } catch (Exception $e) {
          error_log("Cannot initialize logger {$name}: ".$e->getMessage());
        }
      }
    } else {
      $this->bootstrap('log');
      $logger = $this->getResource('log');
    }
    $logger->addPriority('DUMP', 9);
    $logger->addFilter(new Zend_Log_Filter_Priority(intval(getConfigValue('interface->debug->level', 9))));
    setRegistryItem('logger', $logger);
  }

  protected function _initRegistryItems()
  {
    //fputs(STDERR, "_initRegistry\n");
    Core_Debug::getGenerateTime('initRegistry start');
    //try {
      $this->bootstrap('Cache');
      $this->bootstrap('Config');
      $this->bootstrap('EventsManager');
      $this->bootstrap('Database');
      $this->bootstrap('Session');


      /*if (!($config = $this->_cache->load('config'))) {
        $config = new Zend_Config($this->getOptions());
        $this->_cache->save($config, 'config');
      }
      setRegistryItem('config', $config);*/

      $db = getRegistryItem('db');

    //} catch (Exception $e) {
      //die("Fatal error: ".$e->getMessage());
    //}
    Core_Debug::getGenerateTime('initRegistry end');
  }

  protected function _initErrorHandler() {
    //fputs(STDERR, "_initErrorHandler\n");
    if ( APPLICATION_ENV == 'testing' ) {
      return;
    }
    if (!function_exists('exception_error_handler')) {
      function exception_error_handler($code, $message, $file, $line)
      {
        $app_path = realpath(APPLICATION_PATH.'/../').'/';
        $file = str_replace($app_path, '', $file);
        //logVar($message, 'Error handling hit');
        $recoverable_errors = array(E_WARNING, E_NOTICE, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED,
                                    E_USER_DEPRECATED, E_STRICT);
        $suppressed_errors = array(E_STRICT);
        if (in_array($code, $suppressed_errors)) {
          return false;
        }
        if (in_array($code, $recoverable_errors)) {
          if (0==ini_get('error_reporting')) {
            return false;
          }
          $warnings = false;
          if (isRegistered('warnings')) {
            $warnings = getRegistryItem('warnings');
          }
          if (!$warnings) {
            $warnings = array();
          }
          $warnings[] = array('code'=>$code, 'message'=>$message, 'location'=>"$file:$line");
          setRegistryItem('warnings', $warnings);
          return false;
        }
        throw new ErrorException($message, 0, $code, $file, $line);
      }
    }
    if (!function_exists('shutdown_error_handler')) {
      function shutdown_error_handler() {
        // Если у нас персистент коннекшн к базе, то необходимо убедиться,
        // что мы откатили все транзакции и локи, иначе следующая попытка
        // использования этого коннекшна встрянет в проблемы из-за этой
        if (class_exists('Model_SingletonInstance')) {
          Model_SingletonInstance::release();
        }
        if (class_exists('DbTransaction')) {
          if (DbTransaction::$in_transaction) {
            DbTransaction::rollback();
          }
        }
        $error = error_get_last();
        if (isset($error['file'])) {
          $app_path = realpath(APPLICATION_PATH.'/../').'/';
          $error['file'] = str_replace($app_path, '', $error['file']);
        }
        if ($error['type'] == 1) {
          $format = 'html';
          if (isset($_REQUEST['rpctype'])) {
            switch (strtolower($_REQUEST['rpctype'])) {
              case 'direct':
                $format = 'json';
                break;
              case 'soap':
                $format = 'soap';
                break;
              case 'xml':
                $format = 'xml';
                break;
            }
          }
          $response = array('exception'=>true, 'type'=>'exception', 'error'=>$error, 'success'=>false,
                            'message'=>"{$error['file']}:{$error['line']}:{$error['message']}");
          switch ($format) {
            case 'direct':
            case 'json':
              @header("HTTP/1.0 200 OK");
              @header('Content-Type: application/json; charset=utf-8');
              echo json_encode($response);
              break;
            case 'soap':
              @header("HTTP/1.0 200 OK");
              @header('Content-Type: application/soap+xml; charset=utf-8');
              $response = array('SOAP-ENV:Body'=>array('SOAP-ENV:Fault'=>array('faultcode'=>'Fatal error', 'faultstring'=>$response['message'])));
              echo toXML('Envelope', $response, array('xmltag'=>true,
                                                   'xmlns'=>array('SOAP-ENV'=>"http://schemas.xmlsoap.org/soap/envelope/"),
                                                   'ns'=>'SOAP-ENV',
                                                   'type'=>false));
              break;
            case 'xml':
              @header('Content-Type: application/xml; charset=utf-8');
              echo toXML('FatalError', $response['error'], array('xmltag'=>true, 'xmlns'=>false, 'type'=>false));
              break;
            default:
              echo "<html><body><h1>Fatal Error</h1>";
              echo "<p>".htmlspecialchars($response['message'])."</p>";
              echo "</body></html>";
              break;
          }
          exit;
        }
      }
    }
    ini_set('display_errors', false);
    set_error_handler('exception_error_handler');
    register_shutdown_function('shutdown_error_handler');
  }

  protected function _initEventsManager() {
    //fputs(STDERR, "_initEvents\n");
    $this->bootstrap('Autoload');
    $this->bootstrap('Cache');

    class_exists('Core_Observable');

    $disabled_plugins = getConfigValue('plugins->disabled', '');
    $disabled_plugins = explode(' ', $disabled_plugins);
    foreach ($disabled_plugins as $k=>$v) {
      $disabled_plugins[$k] = MyStrToLower($v);
    }

    $dir = @dir(APPLICATION_PATH.'/../library/Core/Plugins/');
    $this->_event_plugins = array();
    if ($dir) {
      while (false!==($entry=$dir->read())) {
        if (preg_match('@^(.+)\.php$@', $entry, $matches)) {
          //logStr("Registering event plugin $entry");
          $plugin = "Core_Plugins_{$matches[1]}";
          if ( !in_array(MyStrToLower($plugin), $disabled_plugins, true) && class_exists($plugin)) {
            $this->_event_plugins[] = new $plugin();
          }
        }
      }
      $dir->close();
    }
  }

  protected function _initCache()
  {
    //fputs(STDERR, "_initCache\n");
    $this->_cache = getRegistryItem('cache');
    return $this->_cache;
  }

  protected function _initMongo() {
    // Инициализация будет on-demand через класс Core_MongoDb, просто отключим
    // плагин, чтобы зенд не пытался инициализировать ресурс (т.к. он не умеет)
    $this->unregisterPluginResource('mongo');
  }

  protected function _initSharedCache() {
    try {
      // убираем ресурс мемкеша из списка, т.к. под него нет плагинов
      $this->unregisterPluginResource('memcached');
      if (isRegistered("shared_cache")) {
        return getRegistryItem("shared_cache");
      }
      if (extension_loaded('memcached')) {
        $backend = 'Libmemcached';
      } elseif (extension_loaded('memcache')) {
        $backend = 'Memcached';
      } else {
        return null;
      }
      $memcache = getConfigValue('resources->memcached->server');
      if (!$memcache || !is_array($memcache)) {
        return;
      }
      //$this->bootstrap('Logger');
      $frontendOptions = array(
        'automatic_serialization' => true,
        'automatic_cleaning_factor' => 0,
        'lifetime' => null,
        //'cache_id_prefix' => $prefix,
        //'logging' => true,
        //'logger' => getRegistryItem('logger'),
      );
      $backendOptions  = array(
        'servers' => array($memcache),
      );
      $cache = Zend_Cache::factory('Zend_Cache_Core', "Zend_Cache_Backend_{$backend}", $frontendOptions, $backendOptions, true, true, true);
      if (!$cache) {
        return null;
      }
      setRegistryItem("shared_cache", $cache);
      return $cache;
    } catch (Exception $e) {
      //logException($e);
    }
    return null;
  }

  protected function _initDefaultLocale()
  {
    $this->bootstrap('Cache');
    Zend_Locale_Data::setCache($this->_cache);
    Zend_Locale::setCache($this->_cache);
    //fputs(STDERR, "_initLocale\n");
    setlocale(LC_ALL, 'ru_RU.UTF-8');
    Zend_Locale::setDefault('ru_RU.UTF-8');
  }

  protected function _initFC()
  {
    //fputs(STDERR, "_initFC\n");
    Core_Debug::getGenerateTime('initFC start');
    Zend_Controller_Action_HelperBroker::addPrefix('Core_Controller_Action_Helper');

    if ( APPLICATION_ENV != 'testing' ) {
      $fc = Zend_Controller_Front::getInstance();
      $fc->setControllerDirectory(array(
          'default' => '../application/controllers',
          'B2bedo'    => '../modules/B2bedo/controllers',
          'B2bnsi'    => '../modules/B2bedo/controllers',
      ));
      $response = new Zend_Controller_Response_Http;
      $response->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
      $fc->setResponse($response);

      if ( isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='direct' ) {
        //define('RESPONSE_CONTEXT', 'json');
        Core_Debug::getGenerateTime('extRequest prepare start');
        $req = file_get_contents('php://input');
        // Костыль, подсовываем модуль
        if(isset($_REQUEST['module'])&& in_array($_REQUEST['module'], getConfigValue('resources->modules', array()))) {
          $req = json_decode($req, true);
          if (is_array($req) && !Core_Util_Array::isAssociative($req)) {
            foreach ($req as $k=>$v) {
              if (is_array($v)) {
                $req[$k]['module'] = $_REQUEST['module'];
              }
            }
          } else {
            $req['module']=$_REQUEST['module'];
          }
          $req = json_encode($req);
        }
        $extParameter = getConfigValue('general->ext->direct->request->parameter', 'rpc');
        $extParameter = preg_replace('@[^a-zA-Z0-9]+@', '', $extParameter);
        $extDirect = new Core_Controller_Plugin_ExtRequest(array(
             'extParameter'      => $extParameter,
             //'additionalHeaders' => array('Content-Type' => 'application/json'),
             'additionalParams'  => array('format' => 'direct'),
         ), $req);

        setRegistryItem(Core_Keys::EXT_REQUEST_OBJECT, $extDirect);
        $extDirect->registerPlugins();
        Core_Debug::getGenerateTime('extRequest prepare end');
      } elseif (isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='soap') {
        //define('RESPONSE_CONTEXT', 'soap');
        $req = file_get_contents('php://input');
        $soap_handler = new Core_Controller_Plugin_Soap(array(
          'additionalHeaders' => array('Content-Type' => 'application/soap+xml; charset=utf-8'),
          'additionalParams'  => array('format' => 'soap'),
          'rpctype' => 'soap'
        ), $req);
        $soap_handler->registerPlugins();
      } elseif (isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='as2xml') {
        $handler = new Core_Controller_Plugin_As2(array(
          'additionalHeaders' => array('Content-Type' => 'application/xml; charset=utf-8'),
          'additionalParams'  => array('format' => 'xml'),
          'rpctype' => 'as2xml'
        ));
        $handler->registerPlugins();
      }  else {

      }
      /*$auth = new Core_Controller_Plugin_Access(Zend_Auth::getInstance()->getIdentity());
      $fc->registerPlugin($auth, 0); */
      Core_Debug::getGenerateTime('initFC end (Before Dispatch)');
    }
  }

  public function finalize() {
    if ($this->need_close_session) {
      Core_Crypto_Session::finalize();
      $this->need_close_session = false;
    }
  }

  /*protected function _initIDS() {
    return;
    $this->bootstrap('Database');
    $input = file_get_contents('php://input');
    if ( isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='direct' && !empty($input)) {
      $input = @json_decode($input, true);
    }
    $check_data = array($_REQUEST);
    if (!empty($input)) {
      $check_data[] = $input;
    }
    if (isset($_SERVER['SCRIPT_URL'])) {
      $check_data[] = $_SERVER['SCRIPT_URL'];
    }
    if (isset($_SERVER['REQUEST_URI'])) {
      $check_data[] = $_SERVER['REQUEST_URI'];
    }
    $score = $this->_checkIDS($check_data);
    if ($score>=10) {
      $time = date('Y-m-d H:i:s');
      $body ="Possible SQL injection usage at $time ({$_SERVER['SERVER_ADDR']})!\n\n".
             "Threat score: $score\n".
             "IP: {$_SERVER['REMOTE_ADDR']}\n".
             "URL: {$_SERVER['SCRIPT_URL']} ({$_SERVER['REQUEST_URI']})\n".
             "Referer: {$_SERVER['HTTP_REFERER']}\n".
              ShowVar('Input data', $input).
              ShowVar('GET', $_GET).
              ShowVar('POST', $_POST).
              ShowVar('COOKIE', $_COOKIE);
      $cookie_name = getConfigValue('resources->session->name', ini_get('session.name'));
      if (isset($_COOKIE[$cookie_name])) {
        try {
          $db = getDbInstance();
          $table = getConfigValue('resources->session->saveHandler->options->name', 'sessions');
          if ('Core_Crypto_Session'==getConfigValue('resources->session->saveHandler->class')) {
            $column = 'id';
          } else {
            $column = getConfigValue('resources->session->saveHandler->options->primary', 'session_id');
          }
          $session = $db->fetchAll("SELECT * FROM $table WHERE $column=?", array($_COOKIE[$cookie_name]), Zend_Db::FETCH_ASSOC);
          $body .= ShowVar('Session', $session);
        } catch (Exception $e) {
          logException($e);
          //suppress
        }
      }
      Model_Mail::mail('grundik@ololo.cc', "Intrusion alert! Score: $score", $body);
      logStr($body, 'ids');
      if ($score>40) {
        $msg = "Произошла непредвиденная ошибка: ошибка базы данных. Если ошибка повторяется — сообщите о ней разработчикам.";
        if (isset($_SERVER["REQUEST_METHOD"]) && 'POST'==$_SERVER["REQUEST_METHOD"]) {
          if ( isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='direct' ) {
            $msg = array('exception'=>true, 'type'=>'exception', 'error'=>$msg, 'success'=>false,
                         'message'=>$msg);
            //$input = @json_decode($input, true);
            if ($input) {
              $fields = array('action', 'method', 'tid');
              foreach ($fields as $f) {
                if (isset($input[$f])) {
                  $msg[$f] = $input[$f];
                }
              }
            }
          } else {
            $msg = array('success'=>false, 'msg'=>$msg);
          }
          echo json_encode($msg);
        } else {
          echo $msg;
        }
        exit;
      }
    }
  }*/

  protected function _checkIDS($str) {
    $score = 0;
    if (is_array($str)) {
      foreach ($str as $s) {
        $score += $this->_checkIDS($s);
      }
      return $score;
    } elseif (!is_string($str)) {
      $str = json_encode($str);
    }
    if (strlen($str)>1024*1024) {
      return 0;
    }
    $m = array();
    $score += 10*preg_match_all('@((select)|(insert)|(update)|(union)|(coalesce))[^a-zA-Z0-9_]@i', $str, $m);
    $m = array();
    $score += 10*preg_match_all('@delete[^/a-zA-Z0-9_]@i', $str, $m);
    $m = array();
    $score += 4*preg_match_all('@((sessions)|(applications[^a-zA-Z0-9])|(auctions)|(suppliers))@i', $str, $m);
    return $score;
  }

}
