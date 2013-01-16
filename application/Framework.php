<?php

define('YAF_FRM', 'Yaf');
define('ZEND_FRM', 'ZF');
if (isset($_SERVER['APPLICATION_ENV']) && 'development'==$_SERVER["APPLICATION_ENV"] && isset($_COOKIE["XDEBUG_SESSION"])) {
  define('DISABLE_CODE_CACHE', true);
}
if (defined('DISABLE_CODE_CACHE')) {
  require_once "Zend/Loader/Autoloader.php";
} else {
  require_once "Core/Autoload/Autoload.php";

  if (@fopen(APPLICATION_PATH . '/cache/All.php', 'r', true)) {
    include_once(APPLICATION_PATH . '/cache/All.php');
  }
}

if (class_exists('Yaf_Application')) {
  require_once APPLICATION_PATH.'/application/framework/Yaf.php';
} else {
  /*class_exists('Zend_View_Helper_HeadMeta');
  class_exists('Zend_View_Helper_HeadTitle');
  class_exists('Zend_View_Helper_HeadLink');
  class_exists('Zend_View_Helper_HeadScript');
  class_exists('Zend_View_Helper_Layout');
  class_exists('Zend_Controller_Action_Helper_Redirector');
  class_exists('Zend_Uri_Http');*/
  require_once APPLICATION_PATH.'/application/framework/Zend.php';
}

require_once 'Util/Bootstrap.php';
