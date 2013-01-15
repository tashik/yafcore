<?php

class Core_SoapClient extends Zend_Soap_Client
{
  protected $_dry_run = false;
  protected $_options = array();
  //protected $_request_override = false;

  public function __construct($wsdl = null, $options = null) {
    $supported_options = array(
      'https_verify_peer' => true,
      'internal_http_binding' => false,
    );
    $this->_options = array_merge($this->_options, $supported_options);
    foreach ($supported_options as $opt=>$val) {
      if (isset($options[$opt])) {
        $this->_options[$opt] = $options[$opt];
        unset($options[$opt]);
      }
    }
    return parent::__construct($wsdl, $options);
  }

  public function _doRequest(Zend_Soap_Client_Common $client, $request, $location, $action, $version, $one_way = null) {
    if ($this->_dry_run) {
      return '';
    //} elseif ($this->_request_override) {
    //  return parent::_doRequest($client, $this->_request_override, $location, $action, $version, $one_way);
    } else {
      return parent::_doRequest($client, $request, $location, $action, $version, $one_way);
    }
  }

  public function getRequest($action, $parameters) {
    $this->_dry_run = true;
    //try {
      $this->$action($parameters);
    /*} catch (SoapFault $e) {
      throw $e;
    }*/
    $this->_dry_run = false;
    return $this->getLastRequest();
  }

  public function doRequest($request, $address, $action, $version=SOAP_1_2) {
    if ($this->_options['internal_http_binding']) {
      if (!function_exists('curl_init')) {
        throw new Exception("Отсутствует необходимое расширение: CURL");
      }
      $handle = curl_init($address);
      if (!$handle) {
        throw new Exception("Ошибка инициализации ресурса CURL");
      }
      curl_setopt($handle, CURLOPT_POST, true);
      curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($handle, CURLOPT_HEADER, false);
      curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $this->_options['https_verify_peer']);
      curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-Type: application/soap+xml; charset=utf-8'));
      curl_setopt($handle, CURLOPT_USERAGENT, getConfigValue('general->user_agent', 'cm9zZWx0b3Jn01'));
      curl_setopt($handle, CURLOPT_POSTFIELDS, $request);
      $opts = $this->getOptions();
      if (isset($opts['login']) && isset($opts['password']) && !empty($opts['login']) && !empty($opts['password'])) {
        //logVar($opts, 'curl opts');
        curl_setopt($handle, CURLOPT_USERPWD, "{$opts['login']}:{$opts['password']}");
      }
      if (isset($opts['user_agent'])) {
        curl_setopt($handle, CURLOPT_USERAGENT, $opts['user_agent']);
      }
      $result = curl_exec($handle);
      if (false===$result) {
        $error = curl_errno($handle).": ".curl_error($handle);
        curl_close($handle);
        throw new Exception($error);
      }
      curl_close($handle);
      libxml_clear_errors();
      $err_handling = libxml_use_internal_errors(true);
      $xml = @simplexml_load_string($result, 'SimpleXMLElement', 0, 'http://schemas.xmlsoap.org/soap/envelope/');
      libxml_use_internal_errors($err_handling);
      if (!$xml) {
        $errors = libxml_get_errors();
        $str = '';
        foreach ($errors as $error) {
          $str .= parseXmlError($error, $result)."\n";
        }
        libxml_clear_errors();
        $str .= "Original XML: ".escapeXML($result);
        throw new Exception("XML Parsing errors: $str");
      }
      $fault = $xml->Body->Fault;
      if (count($fault)) {
        $str = '';
        foreach ($fault->children('') as $child) {
          $str .= $child->getName().": $child\n";
        }
        throw new Exception("SOAP Fault: $str");
      }
      //throw new Exception('parsing ok');
    } else {
      $client = $this->getSoapClient();
      $result = $this->_doRequest($client, $request, $address, $action, $version);
      if ((isset($client->__soap_fault)) && ($client->__soap_fault != null)) {
        throw $client->__soap_fault;
      }
    }
    /*$this->_request_override = $request;
    $action = $this->getFunctions();
    $action = $action[0];
    $action = preg_replace('@^[^ ]+ ([^( ]+)\s*\(.*$@', '$1', $action);
    $result = $this->$action(array());
    $this->_request_override = false;*/
    return $result;
  }
}

