<?php
/**
 * @deprecated
 */
//require_once 'Zend/Loader/Autoloader.php';
//$autoloader = Zend_Loader_Autoloader::getInstance();
setRegistryItem('frm', ZEND_FRM);

class_exists('Zend_Controller_Action_HelperBroker');
class_exists('Zend_View_Helper_HeadMeta');
class_exists('Zend_View_Helper_HeadTitle');
class_exists('Zend_View_Helper_HeadLink');
class_exists('Zend_View_Helper_HeadScript');
class_exists('Zend_View_Helper_Layout');
class_exists('Zend_Controller_Action_Helper_Redirector');
class_exists('Zend_Uri_Http');

function getApplication($options=array()) {
  return new Zend_Application(APPLICATION_ENV, $options);
}

//class Extended_Application extends Zend_Application {};
class Extended_Bootstrap_Abstract extends Zend_Application_Bootstrap_Bootstrap {};
class Extended_Controller_Action extends Zend_Controller_Action {}
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
