<?php

if (class_exists('Yaf_Application')) {
  require_once APPLICATION_PATH.'/application/framework/Yaf.php';
} else {
  require_once APPLICATION_PATH.'/application/framework/Zend.php';
}
