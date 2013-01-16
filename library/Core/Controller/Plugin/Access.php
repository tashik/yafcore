<?php
class Core_Controller_Plugin_Access extends Zend_Controller_Plugin_Abstract {
  protected $_identity;

  protected function _getIdentity() {
    $auth = Zend_Auth::getInstance();
    $identity = $auth->getIdentity();
    if (!$identity) {
      $identity = new stdClass();
      $identity->id = 1;
      $identity->business_id = 0;
    }
    $this->_identity = $identity;
  }

  public function preDispatch(Zend_Controller_Request_Abstract $request) {
    if (defined('CLI')) {
      return;
    }
    //return;
    //logVar($request->getParams(), 'request');
    //logVar($request->getActionName(), 'request');
    if (!$this->_identity) {
      $this->_getIdentity();
    }

    Core_Debug::getGenerateTime('ACL check start');

    $params = $request->getParams();
    $cookie_name = getConfigValue('resources->session->name', ini_get('session.name'));

    if (isset($_COOKIE[$cookie_name])
        && !empty($_COOKIE[$cookie_name])
        && isset($params['rpc'])
        && isset($_REQUEST['rpctype']) && $_REQUEST['rpctype']=='direct'
        && "{$params['rpc']['token']}"!=="{$_COOKIE[$cookie_name]}"
        && ('Index'!=$request->getControllerName() || 'index'!= $request->getActionName())
       )
    {
      $token = $params['rpc']['token'];
      $cookie = $_COOKIE[$cookie_name];
      $allowed = false;
    } else {
      //logVar($request->getParams(), 'req params');
      $allowed = Model_Acl::checkAccessAllowed($this->_identity->id,
                                            $request->getControllerName(),
                                            $request->getActionName(),
                                            (isset($_REQUEST['module'])) ? $_REQUEST['module'] : $request->getModuleName());
    }
    //logVar($allowed, "access check: ".$request->getControllerName().", ".$request->getActionName().", {$this->_identity->id}");
    if (!$allowed) {
      //$request->setModuleName($module);
      $request->setParam('resource', $request->getModuleName().'/'.$request->getControllerName().'/'.$request->getActionName() );
      $request->setControllerName('error');
      $request->setActionName('noaccess');

      //logStr('ACCESS DENIED');
      // @TODO: следует ли логать такие случаи?
    } else {
      // @TODO: возможно надо перенести в postDispatch, чтобы залогать и результат операции?
      $r = Model_Acl::getAPIResource(array('controller'=>$request->getControllerName(),
                                           'action'=> $request->getActionName(),
                                           'module'=> $request->getModuleName(),
                                    ));
      if ($r && $r['log']) {
        //$params = $request->getParams();
        if (isRegistered(Core_Keys::EXT_REQUEST_OBJECT)) {
          $extDirect = getRegistryItem(Core_Keys::EXT_REQUEST_OBJECT);
          $extparam = $extDirect->getConfigValue('extParameter');
          if ($extparam && isset($params[$extparam])) {
            $params = $params[$extparam];
          }
        }
        $params['action_descr'] = $r['descr'];
        Model_Syslog::log($params);
      }
    }
    Core_Debug::getGenerateTime('ACL check end');
  }
}
