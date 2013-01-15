<?php

class Core_Controller_Plugin_Simple_PreDispatcher extends Zend_Controller_Plugin_Abstract {

  protected $_handler = null;

  public function __construct($handler) {
    $this->_handler = $handler;
  }

  public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request) {
    $this->_handler->notifyDispatchLoopStartup($request);
  }

}
