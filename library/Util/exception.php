<?php

class ResponseException extends Exception {
  protected $_extra_params;

  public function __construct($message='', $code=0, $previous=null, $params=array()) {
    $this->_extra_params = $params;
    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 50300) {
      parent::__construct($message, $code, $previous);
    } else {
      parent::__construct($message, $code);
    }
  }

  public function setParam($name, $value) {
    $this->_extra_params[$name] = $value;
    return $this;
  }

  public function getParam($name) {
    if (isset($this->_extra_params[$name])) {
      return $this->_extra_params[$name];
    }
    return null;
  }

  public function getParams() {
    return $this->_extra_params;
  }

}
