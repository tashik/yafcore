<?php

class Core_Controller_Plugin_Soap_Handler
{
  public $function;
  public $params;
  public $result = null;
  public $auth = null;
  public $message_id = null;
  public $response_to = null;

  /**
   * Main entry point
   *
   * @param string $function Функция API для вызова в формате Controller/action
   * @param array $params Массив параметров функции
   * @param array|string $extra_params Или данные авторизации в формате «пользователь:хеш_от_пароля», или в виде массива
   * @return Zend_Soap_Wsdl_Strategy_AnyType результат вызова
   */
  function action($function='Index/index', $params=array(), $extra_params=null) {
    //logVar($function, 'SOAP action');
    //logVar($params, 'SOAP params');
    //logVar($extra_params, 'SOAP auth');
    $params = toArray($params);

    if ( $params['item']) {
      $params = $params['item'];
    }
    if (!is_array($params) || !isset($params[0])) {
      $params = array($params);
    }
    //logVar($params, 'SOAP parameters');
    $this->function = $function;
    $this->params = $params;
    if (is_object($extra_params)) {
      $extra_params = toArray($extra_params);
    }
    if (is_array($extra_params)) {
      if (isset($extra_params['auth'])) {
        $this->auth = $extra_params['auth'];
      } elseif (isset($extra_params['login']) && !empty($extra_params['login'])) {
        $this->auth = "{$extra_params['login']}:{$extra_params['password']}";
      }
      $this->message_id = isset($extra_params['message_id'])?mb_trim($extra_params['message_id']):null;
      $this->response_to = isset($extra_params['response_to'])?mb_trim($extra_params['response_to']):null;
    } elseif (!empty($extra_params)) {
      $this->auth = $extra_params;
    }
    //logVar($this->result, 'SOAP result');
    /*if ($this->result) {
      $this->result['testArray'] = array('some', 'test', 'values');
    }*/
    return $this->result;
  }
}

