<?php
/**
 * @deprecated
 */
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

class Extended_Application extends Zend_Application {};
class Extended_Bootstrap_Abstract extends Zend_Application_Bootstrap_Bootstrap {};
class Extended_Controller_Abstract extends Zend_Controller_Action {}
class Extended_Action_Abstract extends Zend_Controller_Action {}
class Extended_Plugin_Abstract extends Zend_Controller_Plugin_Abstract {}


function getRegistryItem($name) {
  return Zend_Registry::get($name);
}

function setRegistryItem($name, $value) {
  return Zend_Registry::set($name, $value);
}

function isRegistered($name) {
  return Zend_Registry::isRegistered($name);
}

setRegistryItem('frm', YAF_FRM);

function getAutoloaderInstance() {
  return Zend_Loader_Autoloader::getInstance();
}
