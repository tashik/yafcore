<?php

class ArrayWrapper {

  protected $data = array();

  protected $doNumberFormating = false;

  public function  __construct($rawData, $recursive=false) {
    $this->setData($rawData, $recursive);
  }

  public function setDoFloatFormating($doNumberFormating) {
    $this->doNumberFormating = $doNumberFormating;
  }

  protected function convertDataRecursively($rawData) {
    $data = array();
    foreach($rawData as $k=>$v)  {
      $k = str_replace(' ', '', ucwords(str_replace('_', ' ', $k)));
      if (is_array($v)) {
        $data[$k] = new ArrayWrapper($v, true);
      } else {
        $data[$k] = $v;
      }
    }
    return $data;
  }

  public function setData($rawData, $recursive=false) {
    if ($recursive) {
      $this->data = self::convertDataRecursively($rawData);
    } else {
      $this->data = array();
      foreach($rawData as $k=>$v)  {
        $k = str_replace(' ', '', ucwords(str_replace('_', ' ', $k)));
        $this->data[$k] = $v;
      }
    }
  }

  public function __call($name, $arguments) {
    $name = str_replace('get', '', $name);

    if ($this->doNumberFormating) {
      if (is_float($this->data[$name]) || is_double($this->data[$name])) {

        return number_format($this->data[$name], 2, '.', '');
      }
    }

    return $this->data[$name];
  }
}
