<?php
//This is an API DOC for IDE like PHPStorm for code completion
//require_once ('YafAPI.php');
//require_once "Zend/Loader/Autoloader.php";

function getApplication($path_to_config, $options=array()) {
  return new Yaf_Application($path_to_config);
}

class Extended_Bootstrap_Abstract extends Yaf_Bootstrap_Abstract {}
abstract class Extended_Action_Abstract extends Yaf_Action_Abstract {}
class Extended_Plugin_Abstract extends Yaf_Plugin_Abstract {}
class Extended_Config_Exception extends Yaf_Exception {}

/*$autoLoader = Yaf_Loader::getInstance();
$autoLoader->registerLocalNamespace('Yaf');*/
class Extended_Controller_Action extends Yaf_Controller_Abstract {}



function getRegistryItem($name) {
  return Yaf_Registry::get($name);
}

function setRegistryItem($name, $value) {
  return Yaf_Registry::set($name, $value);
}

function isRegistered($name) {
  $registered =  Yaf_Registry::has($name);
  return $registered;
}

setRegistryItem('frm', YAF_FRM);

function getAutoloaderInstance() {
  return Zend_Loader_Autoloader::getInstance();
}

function getConfigPath() {
  return APPLICATION_PATH . "/application/conf/application.ini";
}
