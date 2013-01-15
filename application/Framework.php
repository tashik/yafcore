<?php

define('YAF_FRM', 'Yaf');
define('ZEND_FRM', 'ZF');

if (class_exists('Yaf_Application')) {
  require_once APPLICATION_PATH.'/application/framework/Yaf.php';
} else {
  /**
 * @deprecated
 */
  require_once APPLICATION_PATH.'/application/framework/Zend.php';
}

require_once 'Util/Bootstrap.php';
