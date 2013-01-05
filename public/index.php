<?php

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));

$application = new Yaf_Application( APPLICATION_PATH . "/application/conf/application.ini");

$application->bootstrap()->run();
?>
