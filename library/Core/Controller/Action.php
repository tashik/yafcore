<?php

class Core_Controller_Action extends Extended_Action_Abstract {
  public function execute($args=null) {
    return $this->dispatch($this->getActionName());
  }

  public function dispatch($action) {
        // Notify helpers of action preDispatch state
        $this->_helper->notifyPreDispatch();

        $this->preDispatch();
        if ($this->getRequest()->isDispatched()) {
            $this->autoContext();
            /*if (null === $this->_classMethods) {
                $this->_classMethods = get_class_methods($this);
            }
            logVar($this->_classMethods, 'class methods');
            logVar($action, 'action to call');

            // preDispatch() didn't change the action, so we can continue
            if ($this->getInvokeArg('useCaseSensitiveActions') || in_array($action, $this->_classMethods)) {
                if ($this->getInvokeArg('useCaseSensitiveActions')) {
                    trigger_error('Using case sensitive actions without word separators is deprecated; please do not rely on this "feature"');
                }
                $this->_callAction($action);
            } else {
                $this->__call($action, array());
            }*/
            $this->_callAction($action);
            $this->postDispatch();
        }

        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
        $this->_helper->notifyPostDispatch();
  }

  protected function _callAction($action) {
    $parameters = array();
    //logVar(get_object_vars($this), 'controller variables');
    //logVar($this->getRequest()->getParams(), 'request variables');
    $params = $this->getRequest()->getParams();

    if (isset($params['rpc'])) {
      if (is_array($params['rpc']['data'])) {
        $parameters = $params['rpc']['data'];
      }
    } else {
      $parameters = array($params);
    }
    //logVar($params, 'request params');
    /*
    $reflection = Zend_Server_Reflection::reflectClass($this);
    $methods = $reflection->getMethods();

    $mtd = null;
    foreach($methods as $m) {
      if($m->getName() == $action)
      $mtd = $m;
    }

    if(!$mtd)
      throw new RuntimeException('Method "' . $action . '" not found');

    $protos = $mtd->getPrototypes();
    $args = $protos[0]->getParameters();

    $parameters = array();
    $basicTypes = array('int','float','string','bool');
    foreach($args as $arg) {
      $name = $arg->getName();
      $param = $this->getRequest()->getParam($name, null);
      $type = $arg->getType();

      if($arg->isOptional() && $param === null) {
        $param = $arg->getDefaultValue();
      }
      elseif($param === null) {
        throw new RuntimeException("Parameter '$name' does not exist");
      }

      if(in_array($type, $basicTypes)) {
        settype($param, $type);
      }

      $parameters[] = $param;
    }*/
    Core_Debug::getGenerateTime("Core_Controller_Action->callAction $action");
    try {
      $transaction = DbTransaction::$in_transaction;
      switch(count($parameters)) {
        case 0: $this->{$action}(); break;
        case 1: $this->{$action}($parameters[0]); break;
        case 2: $this->{$action}($parameters[0], $parameters[1]); break;
        case 3: $this->{$action}($parameters[0], $parameters[1], $parameters[2]); break;
        case 4: $this->{$action}($parameters[0], $parameters[1], $parameters[2], $parameters[3]); break;
        case 5: $this->{$action}($parameters[0], $parameters[1], $parameters[2], $parameters[3], $parameters[4]); break;
        default: call_user_func_array(array($this, $action), $parameters);  break;
      }
      //call_user_func_array(array($this, $action), $parameters);
      if (DbTransaction::$in_transaction>$transaction) {
        logVar(DbTransaction::$transactions_trace, "Transaction is not commited, but no exception risen in ".get_class($this)."->{$action}!");
        DbTransaction::rollback();
      } elseif (DbTransaction::$in_transaction<$transaction) {
        logVar(DbTransaction::$transactions_trace, "Extra transaction commit/rollback in ".get_class($this)."->{$action}!");
      }
    } catch (ResponseException $e) {
      $this->_hangleException($e);
      $this->view->success = false;
      $this->view->message = $e->getMessage();
      $this->view->code = $e->getCode();
      foreach ($e->getParams() as $k=>$v) {
        $this->view->$k = $v;
      }
      if (!$this->_context) {
        $this->view->disable_exception_log = true;
        $req = $this->getRequest();
        $req->setModuleName('default');
        $req->setControllerName('error');
        $req->setActionName('error');
      }
    } catch (Exception $e) {
      $this->_hangleException($e);
      if ($this->_context && in_array($this->_context->getCurrentContext(), array('direct', 'soap'))) {
        $frontController = Zend_Controller_Front::getInstance();
        $frontController->setParam('noErrorHandler', true);
        $this->getResponse()->setException($e);
      } else {
        throw $e;
      }
    }
    Core_Debug::getGenerateTime("Core_Controller_Action end $action");
  }

  private function _hangleException($e) {
    if (DbTransaction::$in_transaction) {
      logStr("Implicit transaction rollback!");
      DbTransaction::rollback();
    }
    if ('testing'==APPLICATION_ENV) {
      throw $e;
    }
    logException($e);
  }

  protected function autoContext() {
    $format= $this->getRequest()->getParam('format');
    if (!$format) {
      return;
    }
    $coreContext = $this->_helper->coreContext();

    $class_hash = get_class($this);
    $cache = Zend_Registry::get('cache');
    if (!($methods = $cache->load("class_reflection_{$class_hash}"))) {
      $reflection = Zend_Server_Reflection::reflectClass($this);
      $methods_raw = $reflection->getMethods();
      $methods = array();
      foreach($methods_raw as $method) {
        $is_action = preg_match('/^(.+)Action$/', $method->getName(), $matches);
        if ($is_action && $method->isPublic()) {
          $methods[] = array(
            'name' => $method->getName(),
            'is_public' => $method->isPublic(),
            'is_action' => $is_action,
            'action' => $matches?$matches[1]:null,
            //'doc_comment' => $method->getDocComment(),
            'remotable' => (preg_match('/@remotable/', $method->getDocComment()) > 0)
          );
        }
      }
      $cache->save($methods, "class_reflection_{$class_hash}");
    }
    foreach($methods as $method) {
      if (
        $method['is_public']
        && $method['is_action']
        //&& $method['remotable']
        )
      {
        //logStr('Auto context: '.$matches[1]);
        $coreContext->addActionContext($method['action'], $format);
        //logStr($method['action'].' is a '.RESPONSE_CONTEXT);
      }
    }
    $coreContext->initContext();
    $this->_context = $coreContext;
  }

  protected function _disableView() {
    $this->_helper->viewRenderer->setNoRender(true);
    $this->_helper->layout->disableLayout();
  }
}
