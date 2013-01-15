<?php
$dis=Yaf_Dispatcher::getInstance();

//Initialize Routes for Admin module
$cfg = new Yaf_Config_Ini(__DIR__ . "/conf" . "/b2bedo.ini");
$dis->getRouter()->addConfig($cfg->routes);