<?php

class Cli_Controller_Plugin extends Zend_Controller_Plugin_Abstract {

  public function routeStartup(Zend_Controller_Request_Abstract $request) {
    if (Zend_Layout::getMvcInstance()) {
      Zend_Layout::getMvcInstance()->disableLayout();
    }
  }

}