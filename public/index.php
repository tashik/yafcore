<?php

define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/library'),
    realpath(APPLICATION_PATH . '/application/models'),
    get_include_path(),
)));

require_once APPLICATION_PATH.'/application/Framework.php';

if ( APPLICATION_ENV != 'testing' && isset($_SERVER['REQUEST_URI'])) {
  Core_PageCache::display($_SERVER['REQUEST_URI']);
}

bootstrapApplication()->run();

if ( APPLICATION_ENV != 'testing' && isset($_SERVER['REQUEST_URI'])) {
  Core_PageCache::save($_SERVER['REQUEST_URI']);
}

Core_Debug::getGenerateTime('End');

finalizeApplication();
