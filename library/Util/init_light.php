<?php
define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../../application'));
define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    realpath(APPLICATION_PATH . '/models'),
    //realpath(APPLICATION_PATH . '/controllers'),
    get_include_path(),
)));

require_once 'Util/Bootstrap.php';

bootstrapApplication();

/*
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->setDefaultAutoloader(create_function('$class',
    "include str_replace('_', '/', \$class) . '.php';"));
$autoloader->registerNamespace('Core');
$autoloader->registerNamespace('DbTable');
$modelLoader = new Zend_Application_Module_Autoloader(array(
                'namespace' => '',
                'basePath' => APPLICATION_PATH));

$config = new Zend_Config_Ini(APPLICATION_PATH.'/configs/application.ini', 'development');
Zend_Registry::set('config', $config);
//echo APPLICATION_PATH;
if(APPLICATION_ENV!='production') {
  require_once 'Zend/Log/Formatter/Firebug.php';
  require_once 'Zend/Wildfire/Plugin/FirePhp.php';
  require_once 'Zend/Log.php';
  require_once 'Zend/Log/Writer/Abstract.php';
  require_once 'Zend/Log/Writer/Firebug.php';
  $writer = new Zend_Log_Writer_Firebug();
  $logger = new Zend_Log($writer);
  Zend_Registry::set('logger', $logger);
}
$db = Zend_Db::factory($config->resources->db);
Zend_Db_Table_Abstract::setDefaultAdapter($db);
Zend_Registry::set('db', $db);

require_once 'Util/functions.php';

// Объект кэша
$frontendOptions = array(
  'automatic_serialization' => true,
  'lifetime' => null
);

$backendOptions  = array(
  'cache_dir' => realpath(APPLICATION_PATH . '/../cache')
);

$cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
Zend_Registry::set('cache', $cache);
// Кэшируем локаль чтоб память не жракала
initLocale($cache);
*/
