<?php
/**
 * conjoon
 * (c) 2002-2010 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Id$
 */

/**
 * Simplify IPhone context switching based on HTTP_USER_AGENT
 *
 * This plugin works together with the ExtDirectRequest plugin which automates processing
 * of merged requests.
 * You are strongly advised never to use the baseclass of this helper together with this
 * helper, since the ContextSwitch attaches silently "context" properties to the given
 * ActionController without further identifying which helper set this property.
 *
 * @uses       Zend_Controller_Action_Helper_Abstract
 * @category   Core
 * @package    Core_Controller
 * @subpackage Core_Controller_Action_Helper
 */
class Core_Controller_Action_Helper_CoreContext extends Zend_Controller_Action_Helper_ContextSwitch
{
    /**
     * @var Core_Controller_Plugin_ExtRequest $_extRequest
     */
    protected $_extRequest = null;

    /**
     * Constructor
     *
     * Add HTML context
     *
     * @return void
     */
    public function __construct()
    {
        try {
            $this->_extRequest = getRegistryItem(Core_Keys::EXT_REQUEST_OBJECT);
        } catch (Zend_Exception $e) {
            $this->_extRequest = null;
        }

        parent::__construct();
        $this->addContext('direct',
          array('headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'suffix'  => 'direct',
                'callbacks' => array(
                  'init' => array($this, 'disableRenderer'),
                  'post' => array($this, 'postDirectContext'),
                 )
               )
        );
        $this->addContext('soap',
          array('headers' => array('Content-Type' => 'application/soap+xml; charset=utf-8'),
                'suffix'  => 'soap',
                'callbacks' => array(
                  'init' => array($this, 'disableRenderer'),
                  'post' => array($this, 'postSoapContext'),
                 )
               )
        );
        $this->addContext('as2xml',
          array('headers' => array('Content-Type' => 'application/xml; charset=utf-8'),
                'suffix'  => 'as2xml',
                'callbacks' => array(
                  'init' => array($this, 'disableRenderer'),
                  'post' => array($this, 'postAs2Context'),
                 )
               )
        );
        $this->addContext('htmljson',
          array('headers' => array('Content-Type' => 'text/html; charset=utf-8'),
                'suffix'  => 'html',
                'callbacks' => array(
                  'init' => array($this, 'disableRenderer'),
                  'post' => array($this, 'postHtmlJsonContext'),
                 )
               )
        );
        //$this->addContext('iphone', array('suffix' => 'iphone'));
    }

    /**
     * Initialize Iphone context switching
     *
     * Checks if HTTP_USER_AGENT contains "iphone" or "ipod". if detected,
     * attempts to perform context switch.
     *
     * @param  string $format
     * @return void
     */
    public function initContext($format = null)
    {
        // give paret's implementation presedence, in case format
        // parameter was passed
        parent::initContext($format);

        // context found, skip iphone detection
        if ($this->_currentContext != null) {
            return;
        }

        //logVar($this->_currentContext, 'context set');

        /*if (!isset($_SERVER['HTTP_USER_AGENT'])) {
          return;
        }

        $ipod   = strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'ipod');
        $iphone = strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'iphone');

        if ($ipod === false && $iphone === false) {
            // nope, no iphone
            return;
        }

        $suffix = $this->getSuffix('iphone');
        $this->_getViewRenderer()->setViewSuffix($suffix);

        $this->_currentContext = 'iphone';*/
    }


    /**
     * Processes view variables before the parent implementation serializes
     * to JSON.
     *
     * @return void
     */
    public function postDirectContext()
    {
      //logVar($this, 'postDirectContext');
        Core_Debug::getGenerateTime('postJsonContext start');
      //logVar($this, 'postJsonContext');
        if (!$this->_extRequest || !$this->getAutoJsonSerialization()) {
            return parent::postJsonContext();
        }

        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view = $viewRenderer->view;
        if (!($view instanceof Zend_View_Interface)) {
            return parent::postJsonContext();
        }


        $request      = $this->getRequest();
        $params       = $request->getParams();
        $response     = $this->getResponse();
        //logVar($params, 'req params');
        $extParameter = $this->_extRequest->getConfigValue('extParameter');
        //$extParameter = 'rpc';
        $response->setHttpResponseCode(200);
        $user = null;
        if(Zend_Auth::getInstance()->hasIdentity())
           $user = Zend_Auth::getInstance()->getIdentity();
        if (isset($params[$extParameter])) {
            $params = $params[$extParameter];

            $vars = $view->getVars();
            $view->clearVars();

            if (is_array($params)) {
                if (isset($params['action'])) {
                    $view->action = $params['action'];
                }
                if (isset($params['method'])) {
                    $view->method = $params['method'];
                }
                if (isset($params['tid'])) {
                    $view->tid = $params['tid'];
                }
                if (isset($params['upload'])&&$params['upload']) {
                  $this->getResponse()->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
                }
                if ( isset($vars['success']) && $vars['success'] === false ) {
                  $view->status = false;
                }
                if ('production'!=APPLICATION_ENV && isRegistered('warnings')) {
                  $warnings = getRegistryItem('warnings');
                  if ($warnings) {
                    $view->warnings = $warnings;
                  }
                }

                if ($response->isException()) {
                    $this->_handleException($view, $response, $params, $vars);
                } else {
                    if (isset($params['type'])) {
                        $view->type = $params['type'];
                    }

                    if (isset($vars['result'])) {
                      $vars = $vars['result'];
                    }

                    $view->result = $vars;
                    //Model_Syslog::log($params);
                }


            } else {
                // can only be an exception then
                if ($response->isException()) {
                    $this->_handleException($view, $response, $params, $vars);
                } else {
                    $view->result = $vars;
                    //Model_Syslog::log($params);
                }
            }

        }
        parent::postJsonContext();
        if (isset($params['upload']) && $params['upload']) {
          $body = $response->getBody();
          $body = strtr($body, array('<' => '\u003c',
                                     '>' => '\u003e',
                                     '&' => '\u0026',
                                     "'" => '\u0027',
                                    ));
          $response->setBody($body);
        }
        Core_Debug::getGenerateTime('postJsonContext end');
    }

    protected function _handleException($view, $response, $params, $vars) {
      $view->type    = 'exception';
      $view->status = false;
      $exceptions = $response->getException();//$vars['exception'];
      $msg = array();
      $where = array();
      if ($exceptions) {
        /* @var $e Exception */
        foreach ($exceptions as $e) {
          if (isDebug()) {
            $code = $e->getCode();
            $msg[] = $e->getMessage();
            $where[] = 'At '.$e->getFile().':'.$e->getLine()."\nTrace:\n".$e->getTraceAsString();
          } else {
            $code = $e->getCode();
            $msg_text = $e->getMessage();
            if (   $e instanceof Zend_Db_Exception
                || $e instanceof PDOException)
            {
              $code = 40000;
              $msg_text = 'Invalid request or internal error';
            }
            $msg[] = "[$code] $msg_text";
          }
          //Model_Syslog::log($params, $e->getMessage());
        }
        $view->message = join('\n', $msg);
        if (!empty($where)) {
          $app_path = realpath(APPLICATION_PATH.'/../').'/';
          $view->where = str_replace($app_path, '', join('\n', $where));
        }
      } else {
        $view->message = $vars;
        Model_Syslog::log($params, $view->message);
      }
      $view->disable = true;
      $response->setHttpResponseCode(200);
    }

    public function disableRenderer()
    {
      //logStr("disableRenderer()");
      $this->setAutoDisableLayout(true);
      $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
      $view = $viewRenderer->view;
      if ($view instanceof Zend_View_Interface) {
          $viewRenderer->setNoRender(true);
      }
    }

    protected function _prePocessResponse() {
      $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
      $view = $viewRenderer->view;

      //$request  = $this->getRequest();
      $response = $this->getResponse();

      if ($response->isException()) {
        $this->_handleException($view, $response, '', 'Exception');
      }
      $vars = $view->getVars();
      if ($vars['result']) {
        $vars = $vars['result'];
      }
      if ($response->isException() && $vars['where']) {
        $vars['where'] = split("\n", $vars['where']);
      }
      return $vars;
    }

    public function postSoapContext() {
      $vars = $this->_prePocessResponse();
      $request  = $this->getRequest();
      $response = $this->getResponse();
      $response->setBody($request->getParam('soap_server')->getSoapResult($vars));
      //logStr("postSoapContext");
      //$this->disableRenderer();
    }

    public function postAs2Context() {
      $vars = $this->_prePocessResponse();
      $response = $this->getResponse();
      //$response->setBody($request->getParam('as2xml')->getResult($vars));
      //logStr("postSoapContext");
      //$this->disableRenderer();
      exit;
    }

    public function postHtmlJsonContext() {
      $vars = $this->_prePocessResponse();
      $response = $this->getResponse();

      $response->setBody("<html><body><textarea>".htmlspecialchars(json_encode($vars))."</textarea></body></html>");
    }
}
