<?php

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));
require_once APPLICATION_PATH.'/application/Framework.php';

$application = new My_Application( APPLICATION_PATH . "/application/conf/application.ini");

$application->bootstrap()->run();
