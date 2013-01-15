<?php
//This is an API DOC for IDE like PHPStorm for code completion
//require_once ('YafAPI.php');


class Extended_Application extends Yaf_Application {}
class Extended_Bootstrap_Abstract extends Yaf_Bootstrap_Abstract {}
abstract class Extended_Action_Abstract extends Yaf_Action_Abstract {}
class Extended_Plugin_Abstract extends Yaf_Plugin_Abstract {}

$autoLoader = Yaf_Loader::getInstance();
$autoLoader->registerLocalNamespace('Yaf');
class Extended_Controller_Action extends Yaf_Controller_Abstract {}



function getRegistryItem($name) {
  return Yaf_Registry::get($name);
}

function setRegistryItem($name, $value) {
  return Yaf_Registry::set($name, $value);
}

function isRegistered($name) {
  return Yaf_Registry::isRegistered($name);
}

setRegistryItem('frm', YAF_FRM);

function getAutoloaderInstance() {
  return Yaf_Loader::getInstance();
}

function getConfigPath() {
  return APPLICATION_PATH . "/application/conf/application.ini";
}
