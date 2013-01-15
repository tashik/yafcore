<?php

class Cli_Controller_Action extends Zend_Controller_Action {

  public function preDispatch() {
    if (!$this->getRequest() instanceof Zend_Controller_Request_Simple) {
      throw new Zend_Controller_Action_Exception('', 404);
    }
    if (!$this->getResponse() instanceof Zend_Controller_Response_Cli) {
      throw new Zend_Controller_Action_Exception('', 404);
    }
  }

}