<?php

abstract class Core_Controller_Plugin_XmlApi {

  const LOW_INDEX = -99999999;
  const HIGH_INDEX = 99999999;
  
  protected $_config = array(
    'lowIndex'          => self::LOW_INDEX,
    'highIndex'         => self::HIGH_INDEX,
    'additionalHeaders' => array(),
    'additionalParams'  => array(),
    'soapVersion'       => null,
    'module'            => 'default',
  );
  
  protected $_request_action = null;
  protected $_request_controller = null;
  protected $_requrest_type = null;

  public function __construct(array $config, $req=null) {
    Core_Util_Array::applyStrict($this->_config, $config);
    //logVar($this->_config, 'Request config');
   
    $this->_requrest_type = $this->_config['rpctype'];
    $this->_request_uri = getServerUrl().'index.php?rpctype='.$this->_requrest_type;
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
    $xslt->registerPHPFunctions(array('generateUUID', 'preg_match', 'getConfigValue'));
    return $xslt;
  }

  abstract function notifyDispatchLoopStartup(Zend_Controller_Request_Abstract $request) ;

  protected function _logRequest($result, $table, $is_error=false) {
    //$table = new DbTable_SoapLog();
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
