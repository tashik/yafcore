<?php

class Core_Controller_Plugin_Soap {

  const LOW_INDEX = -99999999;
  const HIGH_INDEX = 99999999;
  protected $_config = array(
    'lowIndex'          => self::LOW_INDEX,
    'highIndex'         => self::HIGH_INDEX,
    'additionalHeaders' => array(),
    'additionalParams'  => array(),
    'soapVersion'       => SOAP_1_2,
    'module'            => 'default',
  );
  protected $_request_action = null;
  protected $_request_controller = null;

  public function __construct(array $config, $req=null) {
    Core_Util_Array::applyStrict($this->_config, $config);
    //logVar($this->_config, 'Request config');
    $this->_request_uri = getServerUrl().'index.php?rpctype=soap';
    $this->_request_data = $req;
    return $this;
  }

  public function registerPlugins() {
    if ($this->_preDispatcher || $this->_postDispatcher) {
      throw new Zend_Controller_Exception("Plugins already registered.");
    }


    $this->_preDispatcher = new Core_Controller_Plugin_Simple_PreDispatcher($this);
    $this->_postDispatcher = new Core_Controller_Plugin_Simple_PostDispatcher($this);

    $front = Zend_Controller_Front::getInstance();

    $front->registerPlugin($this->_preDispatcher, $this->_config['lowIndex']);
    $front->registerPlugin($this->_postDispatcher, $this->_config['highIndex']);
  }

  protected function _getXSLTProcessor() {
    $xslt = new XSLTProcessor();
    $xslt->registerPHPFunctions(array('generateUUID', 'preg_match', 'getConfigValue', 'limitString'));
    return $xslt;
  }

  public function notifyDispatchLoopStartup(Zend_Controller_Request_Abstract $request) {
    $this->_server = new Zend_Soap_Server(null, array(
        'soap_version' => $this->_config['soapVersion'],
        'uri'          => $this->_request_uri,
        //'features' => SOAP_SINGLE_ELEMENT_ARRAYS|SOAP_USE_XSI_ARRAY_TYPE,
    ));

    foreach ($this->_config['additionalParams'] as $pKey => $pValue) {
      $request->setParam($pKey, $pValue);
    }

    $input_xslt = getConfigValue('api->soap->xslt_input', false);
    if ($input_xslt && ($input_xslt=@simplexml_load_file($input_xslt))) {
      $xslt = $this->_getXSLTProcessor();
      $xslt->importStylesheet($input_xslt);
      //logVar($this->_request_data, 'request');
      $this->_request_data = new SimpleXMLElement($this->_request_data);
      $this->_raw_data = $xslt->transformToXml($this->_request_data);
      //$this->_request_data = $this->_raw_data;
    } else {
      $this->_raw_data = $this->_request_data;
    }
    //logVar($this->_raw_data, "XML SOAP");

    $this->_server->setReturnResponse(true);
    $this->_server->setClass('Core_Controller_Plugin_Soap_Handler');
    $this->_handler = new Core_Controller_Plugin_Soap_Handler($this);
    $this->_server->setObject($this->_handler);
    $this->_server->handle($this->_raw_data);
    $request->setParam('soap_server', $this);
    //logVar($this->_handler->auth, 'auth');
    if ( $this->_handler->auth && ($p=strpos("{$this->_handler->auth}", ':')) ) {
      $auth = $this->_handler->auth;
      $result = Model_User::login(substr($auth, 0, $p), substr($auth, $p+1), true);
      //logVar($result, "in-place авторизация $auth");
    }
    $this->_message_id = $this->_handler->message_id;
    $this->_response_to = $this->_handler->response_to;
    $request->setParam('rpc', array('data'=>$this->_handler->params));
    //logVar($this->_handler->params, 'params');

    //logVar($this->_handler_response, "SOAP Response for {$this->_server->function}");
    $action = explode('/', $this->_handler->function);
    //logVar($action, "SOAP action");
    if ($this->_config['module']) {
        $request->setModuleName($this->_config['module']);
    }
    $this->_request_controller = isset($action[0])?$action[0]:'Index';
    $request->setControllerName($this->_request_controller);
    $this->_request_action = isset($action[1])?$action[1]:'index';
    $request->setActionName($this->_request_action);
  }

  protected function _soapFault($code, $message, $details=null, $error_name='', $error_code=0) {
    $response = array(
        'faultcode'=>$code,
        'faultstring'=>$message);
    if ($details) {
      $response['details'] = $details;
    }
    return array('Fault' => $response, 'FaultTechInfo'=>array('code'=>$error_code, 'name'=>$error_name));
    /*$response = array('SOAP-ENV:Body'=>array('SOAP-ENV:Fault'=>$response));
    $response = toXML('Envelope', $response, array('xmltag'=>true,
                                              'xmlns'=>array('SOAP-ENV'=>"http://schemas.xmlsoap.org/soap/envelope/"),
                                              'ns'=>'SOAP-ENV',
                                              'type'=>false));
    fireEvent('soap_result_fault', $response);
    return $response;*/
  }

  protected function _logRequest($result, $is_error=false) {
    $table = new DbTable_SoapLog();
    return $table->insert(array(
       'action' => $this->_request_action,
       'controller' => $this->_request_controller,
       'user_id' => Zend_Registry::isRegistered('userid')?Zend_Registry::get('userid'):null,
       'request' => is_object($this->_request_data)?$this->_request_data->asXML():$this->_request_data,
       'response' => $result,
       'errors_count' => $is_error?1:0,
       'message_id' => $this->_message_id,
       'response_to' => $this->_response_to,
    ));
  }

  public function getSoapResult($result) {
    $this->_server->setReturnResponse(true);
    $this->_handler->result = $result;
    $success = true;

    if ( isset($result['type']) && 'exception'==$result['type'] ) {
      $result = $this->_soapFault('Exception', $result['message'], $result['where'], 'INTERNAL_ERROR', 500);
      $success = false;
    } elseif (!isset($result['success']) || !$result['success']) {
      $code = 'Unsuccessful operation';
      $techcode = 503;
      $techname = 'GENERAL_FAILURE';
      if (isset($result['no_session']) && $result['no_session']) {
        $code = 'Not authorized or wrong credentials supplied';
        $techcode = 401;
        $techname = 'AUTHENTICATION_FAILURE';
      } elseif (isset($result['no_access']) && $result['no_access']) {
        $code = 'Access denied';
        $techcode = 403;
        $techname = 'FORBIDDEN';
      }
      $result = $this->_soapFault($code, $result['message'], null, $techname, $techcode);
      $success = true;
      //fireEvent('soap_result_failure', $result_xml, $tagname, $this->_request_data, $log_id, $code, $message);
      //return $result;
    }

    $output_xslt = getConfigValue('api->soap->xslt_output', false);
    if ($output_xslt) {
      $result = toXML('Result', array('Response'=>$result), array('xmltag'=>true, 'xmlns'=>true));
      //logVar($result, "SOAP result before preprocessing");
      if (!is_object($this->_request_data)) {
        //logVar($this->_request_data, 'request');
        $request_data = new SimpleXMLElement($this->_request_data);
      } else {
        $request_data = $this->_request_data;
      }
      $root_tag = $request_data->xpath("/*[local-name() = 'Envelope']/*[local-name() = 'Body']/*");
      //logVar($root_tag, 'root tag');
      if ( $root_tag && count($root_tag)) {
        $request_data = $root_tag[0];
      }
      $tagname = $request_data->getName();
      $request_data = dom_import_simplexml($request_data);

      $xslt = $this->_getXSLTProcessor();
      $template = @file_get_contents($output_xslt);
      if (!$template) {
        return $result;
      }
      $template = str_replace('{REQUEST_URI}', $this->_request_uri, $template);
      $template = simplexml_load_string($template);
      if (!$template) {
        return $result;
      }
      $xslt->importStylesheet($template);
      $result = new SimpleXMLElement($result);
      $request = $result->addChild('Request', null, '');
      $request->addChild('Function', $tagname);
      $result_dom = dom_import_simplexml($result);
      $request_data = $result_dom->ownerDocument->importNode($request_data, true);
      //$element = $result->createElement('Result');
      //$element->appendChild($this->_request_data);
      $result_dom->appendChild($request_data);
      //logVar($this->_request_data->saveXML(), "SOAP request before transform");
      //logVar($result->asXML(), "SOAP result before transform");
      $result = $xslt->transformToXml($result);
      //logVar($result, "SOAP result");
    } else {
      if ($success) {
        $result = $this->_server->handle($this->_raw_data);
      } else {
        $result = array('SOAP-ENV:Body'=>array('SOAP-ENV:Fault'=>$result['Fault']));
        $result = toXML('Envelope', $result, array('xmltag'=>true,
                                                  'xmlns'=>array('SOAP-ENV'=>"http://schemas.xmlsoap.org/soap/envelope/"),
                                                  'ns'=>'SOAP-ENV',
                                                  'type'=>false));
      }
    }
    $result_xml = new SimpleXMLElement($result);
    $response = $result_xml->xpath("/*[local-name() = 'Envelope']/*[local-name() = 'Body']/*");
    if ($response && count($response)) {
      $tagname = $response[0];
      $tagname = $tagname->getName();
    }
    $log_id = $this->_logRequest($result);
    fireEvent($success?'soap_result_success':'soap_result_failure', $result_xml, $tagname, $this->_request_data, $log_id);
    return $result;
  }

  public function notifyDispatchLoopShutdown() {
    $postDispatcher = $this->_postDispatcher;

    foreach ($this->_config['additionalHeaders'] as $type => $value) {
      $postDispatcher->getResponse()->setHeader($type, $value);
    }
    /*$orgResponse = clone $postDispatcher->getResponse();
    $orgRequest  = clone $postDispatcher->getRequest();

    foreach ($config['additionalHeaders'] as $type => $value) {
      $orgResponse->setHeader($type, $value);
    }

    $front = Zend_Controller_Front::getInstance();
    $front->setRequest($orgRequest);
    $front->setResponse($orgResponse);*/
  }
}
