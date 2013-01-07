<?php

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));
require_once APPLICATION_PATH.'/application/Framework.php';

$application = new My_Application( APPLICATION_PATH . "/application/conf/application.ini");

$application->bootstrap()->run();
