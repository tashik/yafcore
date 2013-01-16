<?php
/**
 * @deprecated
 */
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
setRegistryItem('frm', ZEND_FRM);

function getApplication($path_to_config, $options=array()) {
  return new Zend_Application(APPLICATION_ENV, $path_to_config);
}

//class Extended_Application extends Zend_Application {};
class Extended_Bootstrap_Abstract extends Zend_Application_Bootstrap_Bootstrap {};
class Extended_Controller_Abstract extends Zend_Controller_Action {}
class Extended_Action_Abstract extends Zend_Controller_Action {}
class Extended_Plugin_Abstract extends Zend_Controller_Plugin_Abstract {}

class Extended_Config_Ini extends Zend_Config_Ini {}
class Extended_Config_Exception extends Zend_Config_Exception {}


function getRegistryItem($name) {
  return Zend_Registry::get($name);
}

function setRegistryItem($name, $value) {
  return Zend_Registry::set($name, $value);
}

function isRegistered($name) {
  return Zend_Registry::isRegistered($name);
}



function getAutoloaderInstance() {
  return Zend_Loader_Autoloader::getInstance();
}
