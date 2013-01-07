<?php

require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

class My_Application extends Zend_Application {};
class My_Bootstrap_Abstract extends Zend_Application_Bootstrap_Bootstrap {};
class My_Controller_Abstract extends Zend_Controller_Action {}
class My_Plugin_Abstract extends Zend_Controller_Plugin_Abstract {}
