<?php

class Core_Config_Writer_Ini extends Zend_Config_Writer_Ini {

  /**
   * Prepare a value for INI
   *
   * @return string
   * @param  mixed $value
   *
   * @throws Zend_Config_Exception
   */
  protected function _prepareValue($value) {
    if (is_integer($value) || is_float($value)) {
      return $value;
    } elseif (is_bool($value)) {
      return ($value ? 'true' : 'false');
    } elseif (strpos($value, '"') === false) {
      return '"' . $value .  '"';
    } elseif (strpos($value, '\'') === false) {
      return '\'' . $value .  '\'';
    } else {
      require_once 'Zend/Config/Exception.php';
      throw new Zend_Config_Exception('Value can not contain double quotes "');
    }
  }

}