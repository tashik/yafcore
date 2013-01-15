<?php

class Core_Controller_Plugin_As2 extends Core_Controller_Plugin_XmlApi {

  protected $_request_action = null;
  protected $_request_controller = null;
  protected $_request_type='as2xml';

  public function notifyDispatchLoopStartup(Zend_Controller_Request_Abstract $request) {

    foreach ($this->_config['additionalParams'] as $pKey => $pValue) {
      $request->setParam($pKey, $pValue);
    }

    $input_xslt = getConfigValue('api->as2->xslt_input', false);
    if ($input_xslt && ($input_xslt=@simplexml_load_file($input_xslt))) {
      $xslt = $this->_getXSLTProcessor();
      $xslt->importStylesheet($input_xslt);
      
      $receiver = new Core_As2_Receiver();
      while ($filename = $receiver->nextFileName()) {
        $this->_request_data = new SimpleXMLElement(file_get_contents($filename));
        $this->_raw_data = $xslt->transformToXml($this->_request_data);
        $xml = simplexml_load_string($this->_raw_data);
        
        $arr = simplexml2array($xml);
        logVar($arr, 'xml array');
        $request->setParam('as2xml', $this);
        $request->setParam('format', 'as2xml');
        $request->setParam('rpc', array('data'=>$arr['params']));
        $request->setParam('message_id', $arr['message_id']);
        
        if ($this->_config['module']) {
          $request->setModuleName($this->_config['module']);
        }
    
        $action = explode('/', $arr['function']);
        $this->_request_controller = isset($action[0])?$action[0]:'Index';
        $request->setControllerName($this->_request_controller);
        $this->_request_action = isset($action[1])?$action[1]:'index';
        $request->setActionName($this->_request_action);
      }
    }
    
    
    /*$this->_server->setReturnResponse(true);
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
    $request->setActionName($this->_request_action);*/
  }
}
