<?php

require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

class My_Application extends Zend_Application {
  public function __construct($options, $unused = null) {
    parent::__construct(APPLICATION_ENV, $options);
  }
}
class My_Bootstrap_Abstract extends Zend_Application_Bootstrap_Bootstrap {}
class My_Controller_Abstract extends Zend_Controller_Action {}
class My_Plugin_Abstract extends Zend_Controller_Plugin_Abstract {}
class My_Request_Abstract extends Zend_Controller_Request_Abstract {}
class My_Response_Abstract extends Zend_Controller_Response_Abstract {}
class My_Registry extends Zend_Registry {}
