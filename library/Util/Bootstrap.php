<?php

defined('APPLICATION_CLASS')
    || define('APPLICATION_CLASS', 'master');

function initCache() {
  Core_Debug::getGenerateTime('initCache start');
  $cache_prefix = '_';
  if (isset($_SERVER['SERVER_NAME'])) {
    $cache_prefix = preg_replace('@\W+@', '_', $_SERVER['SERVER_NAME']);
  } elseif (isset($_SERVER["HTTP_HOST"])) {
    $cache_prefix = preg_replace('@\W+@', '_', $_SERVER["HTTP_HOST"]);
  }
  // First, set up the Cache
  $frontendOptions = array(
    'automatic_serialization' => true,
    'lifetime' => null,
    'cache_id_prefix' => $cache_prefix,
  );

  $backendOptions  = array();

  if (extension_loaded('apc') && @apc_cache_info()) {
    $backend = 'Apc';
  } else {
    $backend = 'File';
    $backendOptions['cache_dir'] = realpath(APPLICATION_PATH . '/cache');
  }
  $cache = Zend_Cache::factory('Zend_Cache_Core', "Zend_Cache_Backend_{$backend}", $frontendOptions, $backendOptions, true, true, true);

  // Next, set the cache to be used with all table objects
  //Zend_Db_Table_Abstract::setDefaultMetadataCache($cache);
  setRegistryItem('cache', $cache);
  Core_Debug::getGenerateTime('initCache end');
}

function initConfig($file) {
  $cache = getRegistryItem('cache');
  $cache_key = "config_".APPLICATION_ENV.APPLICATION_CLASS;
  if (!($config = $cache->load($cache_key))) {
    $configObj = new Core_Config($file, APPLICATION_ENV);
    $config = $configObj->getConfig();
    if(getRegistryItem('frm')==YAF_FRM) {
      $config = $config->toArray();
    }
    $cache->save($config, $cache_key);
  }
  setRegistryItem('config', $config);
  //print_r($config->toArray());
  return $config;
}

function reloadConfig($file) {
  $cache = getRegistryItem('cache'); /** @var Zend_Cache_Backend_Apc|Zend_Cache_Backend_File $cache */
  $cache->remove('config_' . APPLICATION_ENV.APPLICATION_CLASS);
  initConfig($file);
}

function bootstrapApplication() {
  // Create application, bootstrap, and run
  try {
    $application = getApplication(
        initConfig(APPLICATION_PATH . "/application/conf/".strtolower(getRegistryItem('frm'))."/application.ini")
    );
    setRegistryItem('application', $application);

    return $application->bootstrap();
  } catch (Exception $e) {
    logException($e);
    $msg = "Initialization problem, please retry later\n";
    global $argv;
    if ('production'!=APPLICATION_ENV || isset($argv)) {
      $msg .= "Details: ".$e->getMessage()."\n";
    }
    die($msg);
  }
}

function finalizeApplication() {
  if (!defined('DISABLE_CODE_CACHE')) {
    My_NameScheme_Autoload::compileTo(APPLICATION_PATH . '/cache/All.php');
  }
  if (isRegistered('application')) {
    /* @var $app Zend_Application */
    $app = getRegistryItem('application');

    if (getRegistryItem('frm')==ZEND_FRM) {
      $bootstrap = $app->getBootstrap();
      if ($bootstrap && method_exists($bootstrap, 'finalize')) {
        $bootstrap->finalize();
      }
    } else {
      //@TODO: финалайзим аппликуху YAF
    }
  }
}

if (isset($_SERVER['APPLICATION_ENV']) && 'development'==$_SERVER["APPLICATION_ENV"] && isset($_COOKIE["XDEBUG_SESSION"])) {
  define('DISABLE_CODE_CACHE', true);
}

if (defined('DISABLE_CODE_CACHE')) {
  require_once "Zend/Loader/Autoloader.php";
} else {
  require_once "Core/Autoload/Autoload.php";

  if (@fopen(APPLICATION_PATH . '/cache/All.php', 'r', true)) {
    include_once(APPLICATION_PATH . '/cache/All.php');
  } else {
    if(getRegistryItem('frm')==ZEND_FRM) {
      class_exists('Zend_View_Helper_HeadMeta');
      class_exists('Zend_View_Helper_HeadTitle');
      class_exists('Zend_View_Helper_HeadLink');
      class_exists('Zend_View_Helper_HeadScript');
      class_exists('Zend_View_Helper_Layout');
      class_exists('Zend_Controller_Action_Helper_Redirector');
      class_exists('Zend_Uri_Http');
    }
    /** Zend_Application */
    //require_once 'Zend/Application.php';
  }
}

$autoloader = getAutoloaderInstance();

if (!defined('DISABLE_CODE_CACHE')) {
  $autoloader->setDefaultAutoloader(array("My_NameScheme_Autoload", "classAutoloader"));
}

$autoloader->registerNamespace('Core');


if (false&&APPLICATION_ENV!='production') {
  Core_Debug::setEnabled(true);
  Core_Debug::getGenerateTime('Begin');
}

initCache();

//include('zend.phar');
date_default_timezone_set('Europe/Moscow');

//require_once APPLICATION_PATH.'/application/'.getRegistryItem('frm').'_Bootstrap.php';
