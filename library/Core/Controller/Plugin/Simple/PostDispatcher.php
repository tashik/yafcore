<?php

class Core_Controller_Plugin_Simple_PostDispatcher extends Zend_Controller_Plugin_Abstract {

  protected $_handler = null;

  public function __construct($handler) {
    $this->_handler = $handler;
  }

  public function dispatchLoopShutdown() {
    $this->_handler->notifyDispatchLoopShutdown();
  }

}
