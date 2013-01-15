<?php
/**
 * conjoon
 * (c) 2002-2010 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Id$
 */

/**
 * This is not directly a plugin, but more of a mediator between two plugins:
 * The Core_Controller_Plugin_ExtRequest_PreDispatcher and
 * Core_Controller_Plugin_ExtRequest_PostDispatcher.
 *
 * Those two plugins combined are capable of recognizing whether a request to
 * the server was made using the Ext.Direct-API. In some cases, the
 * Ext.Direct-API might merge a number of requests into a single one: The plugins
 * splits one request into multiple requests if it detects that Ext did collect
 * data to send in one request, whereas each dataIndex denotes an individual
 * action to process, just as if this data was sent in a single request.
 *
 * Before the dispatch loop gets invoked, the
 * Core_Controller_Plugin_ExtRequest_PreDispatcher checks if there is a specific
 * parameter available, then JSON-decodes it (as it assumes it's json encoded
 * data) and tests if the array is a numeric array - if that is the case, each
 * array index will be searched for the action and method values and based on this
 * several requests (Zend_Controller_Request_Http) which will be moved onto a
 * stack. This stack will be processed later on by the
 * Core_Controller_Plugin_ExtRequest_PostDispatcher which resets the
 * Front-Controller to it's state when the original request has been made in a
 * loop that processes those requests. The individual responses will then be merged
 * again and send back to the client.
 *
 *
 * Example:
 *
 * The application wants to read out feed accounts, check if a feed address is
 * valid, and read out feed items afterwards. All of those actions are merged by Ext.
 * The Ext.Direct Provider API looks like this:
 *
 * { url : '/groupware', // the module
 *      actions : {
 *          feeds : [{ // controller
 *              name : 'getFeedAccounts' // action
 *          }, {
 *              name : 'isFeedAddressValid'
 *          }, {
 *              name : 'getFeedItems'
 *          }]
 *      }
 *    namespace : 'com.conjoon.groupware.remote'
 * }
 *
 * Client side calls then:
 *
 *  com.conjoon.groupware.remote.feeds.getFeedAccounts();
 *  com.conjoon.groupware.remote.feeds.isFeedAddressValid();
 *  com.conjoon.groupware.remote.feeds.getFeedItems();
 *
 * Ext does now merge those requests into one. The url called is
 *
 * '/groupware'. The value of the specific Ext.Direct parameter looks like this:
 *  [
 *      {"action":"feeds","method":"getFeedAccounts","data":[],"type":"rpc","tid":2},
 *      {"action":"feeds","method":"isFeedAddressValid","data":[],"type":"rpc","tid":3},
 *      {"action":"feeds","method":"getFeedItems","data":[],"type":"rpc","tid":4}
 *  ]
 *
 *
 * Core_Controller_Plugin_ExtRequest_PreDispatcher inspects the post data.
 * It finds a numeric array and assumes that multiple requests were merged into
 * one. Requests-Objects will be created, moved onto the stack and processed by
 * the postDispatcher later on.
 * Additionally, if the data-value is numeric, the param "data" for each request
 * will be added as a parameter. If the data value is associative, the properties
 * with their values will be added as parameters. The original data will be stored
 * decoded under the name of the specified Ext.Direct Post parameter.
 * On the other hand, if the specified Ext.Direct post parameter was found, but
 * does not hold a numeric array, the PreDispatcher assumes it deals with a single
 * request made by the Ext.Direct-API. Data will be decoded and stored as described
 * above. The PostDispatcher will not take action then.
 *
 * Please note, that using multiple requests merged into one is expensive, since
 * copies of all plugins and helpers found initially are stored and exchanged
 * between plugins and helpers. The front controller's "dispatch()" method will be
 * called as many times as requests are available on the stack.
 *
 * When using those plugins, you have to make sure that the PreDispatcher is the
 * VERY FIRST plugin that gets processed, and the PostDispatcher is the VERY LAST
 * plugin that gets processed. This mediator will help by defining default stack
 * indexes. If you provide your own stack indexes, make sure that no other plugin
 * gets a lower stack index than the PreDispatcher, and no higher index than
 * the PostDispatcher.
 *
 * Note:
 * 1) Before the request stack gets processed, the request that holds the merged ones
 * gets processed as usual, with the only difference that its response body gets later
 * on completely overwritten with the merged responses from the processed requests.
 * 2) For the initial request, you have to specify a module, a controller and an action
 * that should be called.
 *
 *
 * @package Core_Controller
 * @subpackage Plugin
 * @category Plugins
 *
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */
class Core_Controller_Plugin_ExtRequest {

    /**
     * @const Integer LOW_INDEX The default index for the PreDispatcher
     */
    const LOW_INDEX = -99999999;

    /**
     * @const Integer LOW_INDEX The default index for the PostDispatcher
     */
    const HIGH_INDEX = 99999999;

    /**
     * @const String INDEX_KEY The default param name for the index of a splitted
     * request in the request stack
     */
    const INDEX_KEY = '__ExtDirectRequestRouter_processIndex__';

    /**
     * @var array $_requestInfo A stack of additional informations for processing
     * a request when in the dispatchLoopStartup method.
     */
    protected $_requestInfo = array();

    /**
     * @var array $_requestStack A stack of requests that were created from
     * merged requests.
     */
    protected $_requestStack = array();

    /**
     * @var Boolean $_isExtMultiRequest Gets set to true if the plugins detect
     * a merged request that was sent by the Ext.Direct API. Note, that this
     * property gets in fact only set to true if the request was merged.
     * For checking whether the request was made using the Ext.Direct-API,
     * see $_isExtRequest
     */
    protected $_isExtMultiRequest = false;

    /**
     * @var Boolean $_isExtRequest Gets set to true if the plugins detect
     * a request that was sent by the Ext.Direct API.
     */
    protected $_isExtRequest = false;

    /**
     * @var Boolean $_isForm Gets set to true if the form was submitted by Ext.Direct
     */
    protected $_isForm = false;

    /**
     * @var Boolean $_processed Whether the dispatchLoopStartup has been called
     * at least once for the PreDispatcher.
     */
    protected $_processed = false;

    /**
     * @var Array $_paramsList Stores a copy of the original params of
     * the front controller with which the PreDispatcher was called.
     */
    protected $_paramsList = array();

    /**
     * @var Array $_helperList Stores a copy of the original helper that
     * where found in the HelperBroker with which the PreDispatcher was called.
     */
    protected $_helperList = array();

    /**
     * @var Array $_pluginsList Stores a copy of the original plugins that
     * where found in the front's plugins broker with which the PreDispatcher
     * was called, except the PreDispatcher and the PostDispatcher
     */
    protected $_pluginsList = array();

    /**
     * @var Core_Controller_Plugin_ExtRequest_PreDispatcher $_preDispatcher
     */
    protected $_preDispatcher = null;

    /**
     * @var Core_Controller_Plugin_ExtRequest_PreDispatcher $_postDispatcher
     */
    protected $_postDispatcher = null;

    /**
     * @var array of request data
     */
    protected $_data = array();

    /**
     * @var array of raw request data
     */
    protected $_raw_data = null;

    /**
     * @var Array _config
     */
    protected $_config = array(
        'extParameter'      => 'rpc',
        'additionalParams'  => array(),
        'lowIndex'          => self::LOW_INDEX,
        'highIndex'         => self::HIGH_INDEX,
        'indexKey'          => self::INDEX_KEY,
        'additionalHeaders' => array(),
        'singleException'   => false
    );

    /**
     * Constructor.
     *
     * @param Array $config The configuration for an instance of this
     * class. Possible key/value/pairs are:
     *
     *   String extParameter The post parameter name which holds data submitted
     *                       by an Ext.Direct-request. Defaults to extDirectData
     *   Array additionalParams A set of additional params that get added to
     *                          _each_ requests params list. Defaults to an
     *                          empty array. Params in thes list get added before
     *                          automatic parameter handling for the Ext.Direct-data
     *                          is invoked
     *   Integer lowIndex The stack index for the PreDispatcher to make sure it
     *                    gets called as the very first plugin. Defaults to
     *                    LOW_INDEX
     *   Integer highIndex The stack index for the PostDispatcher to make sure it
     *                     gets called as the very last plugin. Defaults to
     *                     HIGH_INDEX
     *   String action The action to call for the original request. Defaults to
     *                 null, which means the url of the original request will
     *                 be routed as usual
     *   String controller The controller where action can be found.  Defaults to
     *                 null, which means the url of the original request will
     *                 be routed as usual
     *   String module The module where controller can be found.  Defaults to
     *                 null, which means the url of the original request will
     *                 be routed as usual
     *   Integer indexKey If the passed request to either of the plugins is
     *                    a splitted request, this holds the index of the
     *                    request in the request stack. Defaults to INDEX_KEY
     *   Array additionalHeaders a list of additional headers to set for the
     *                           response
     *   Boolean singleException whether an exception that happens during the initial
     *                           request should becopied and returned for all tids.
     *                           If set to "false", copies the exception, otherwise
     *                           returns the exception without specifying further tids.
     *                           Defaults to "false".
     */
    public function __construct(Array $config, $req=null)
    {
        Core_Util_Array::applyStrict($this->_config, $config);
        //logVar($req, 'req');
        $this->_raw_data = $req;
        return $this;
    }

    /**
     * Registers the PreDispatcher and PostDispatcher to the front's
     * plugins list.
     *
     * @throws Zend_Controller_Exception if the plugins are already registered.
     */
    public function registerPlugins()
    {
        if ($this->_preDispatcher || $this->_postDispatcher) {
            throw new Zend_Controller_Exception("Plugins already registered.");
        }


        $this->_preDispatcher  = new Core_Controller_Plugin_ExtRequest_PreDispatcher($this, $this->_raw_data);
        $this->_postDispatcher = new Core_Controller_Plugin_ExtRequest_PostDispatcher($this);

        $front = Zend_Controller_Front::getInstance();

        $front->registerPlugin($this->_preDispatcher,  $this->_config['lowIndex']);
        $front->registerPlugin($this->_postDispatcher, $this->_config['highIndex']);
        //logVar($front->getRequest(), 'request regplug');
    }

    /**
     * Returns the value for the specified config key, or null if it does
     * not exist.
     *
     * @param String $key
     *
     * @return mixed
     */
    public function getConfigValue($key)
    {
        if (!isset($this->_config[$key])) {
            return null;
        }

        return $this->_config[$key];
    }

    /**
     * Returns the current configured request stack for this instance.
     * Note, that depending on when you call this method, not all
     * configured request may be available in the request stack anymore.
     *
     * @return Array
     *
     * @throws Zend_Controller_Exception If trying to access the property when
     * the plugins were not processed yet
     */
    public function getRequestStack()
    {
        if (!$this->_processed) {
            throw new Zend_Controller_Exception("dispatchLoopStartup was not yet called for the preDispatcher.");
        }

        return $this->_requestStack;
    }

    /**
     * Return true if the request was made using the Ext.Direct-API.
     *
     * @return Boolean
     *
     * @throws Zend_Controller_Exception If trying to access the property when
     * the plugins were not processed yet
     */
    public function isExtRequest()
    {
        if (!$this->_processed) {
            throw new Zend_Controller_Exception("dispatchLoopStartup was not yet called for the preDispatcher.");
        }

        return $this->_isExtRequest;
    }

    /**
     * Returns true if the request was made using the Ext.Direct-API and if more
     * than one request were merged into this one.
     *
     * @return Boolean
     *
     * @throws Zend_Controller_Exception If trying to access the property when
     * the plugins were not processed yet
     */
    public function isExtMultiRequest()
    {
        if (!$this->_processed) {
            throw new Zend_Controller_Exception("dispatchLoopStartup was not yet called for the preDispatcher.");
        }

        return $this->_isExtMultiRequest;
    }

    public function parseRequest($request) {
      //logVar($this->_raw_data, 'raw data');
      $rpctype = $request->getParam('rpctype',null);
      $extAction = $request->getParam('extAction',null);
      //logVar($request->getParams(), 'rpctype');
      if(!empty($rpctype) && $rpctype=='direct' && empty($extAction)) {
        $this->_data = json_decode($this->_raw_data, true);
      }
      else {
          $req = $request->getParams();

          $this->_raw_data = json_decode($this->_raw_data, true);
          $post = $request->getPost();
          $dt = $post;
          if(!empty($extAction)){ // form post
            $this->isForm = true;
            $this->isUpload = ('true'==$req['extUpload']);
          }
          $data = array();
          $action = $req['extAction'];
          $method = $req['extMethod'];
          $tid = $req['extTID'];
          $token = $req['extToken'];
          unset($dt['extAction']);
          unset($dt['extMethod']);
          unset($dt['extTID']);
          unset($dt['extType']);
          unset($dt['extUpload']);
          unset($dt['extToken']);
          $data = array($dt, $_FILES);

          $this->_data = array('action'=>$action, 'method'=>$method, 'tid'=>$tid, 'token'=>$token,
                               'data'=>$data, 'upload'=>$req['extUpload'], 'type'=>$req['extType'],
                               'module'=>$this->_raw_data['module']);
      }
      return $this;
    }

    /**
     * Method gets called when the PreDispatcher's dispatchLoopStartup
     * method gets called.
     * This method is API only.
     *
     * @param Zend_Controller_Request_Abstract $request
     *
     * @packageprotected
     */
    public function notifyDispatchLoopStartup(Zend_Controller_Request_Abstract $request, $raw_data)
    {

        if ($this->_preProcessRequest($request)) {
            return;
        }

        if ($this->_isExtMultiRequest) {
            return;
        }

        if(empty($this->_raw_data)) $this->_raw_data = $raw_data;
        $config =& $this->_config;

        $this->parseRequest($request);

        $extDirectData = $this->_data;
        //logVar($this->_data, 'parsed data');

        if (!empty($extDirectData)) {

            //$decoded = Zend_Json::decode($extDirectData);

            if (!Core_Util_Array::isAssociative($extDirectData)) {

                $this->_isExtMultiRequest = true;
                $this->_isExtRequest      = true;

                for ($i = count($extDirectData)-1; $i >= 0; $i--) {

                    $controller = $extDirectData[$i]['action'];
                    $action     = $extDirectData[$i]['method'];
                    $module     = isset($extDirectData[$i]['module'])?$extDirectData[$i]['module']:'default';

                    $req = new Zend_Controller_Request_Http();

                    $req->setActionName($action)
                        ->setControllerName($controller)
                        ->setModuleName(($module&&!empty($module))? $module:'default');
                        //->setModuleName($request->getModuleName());

                    $this->_requestStack[] = $req;
                    $this->_requestInfo[]  = array(
                        'action'     => $action,
                        'controller' => $controller,
                        'module'     => ($module&&!empty($module))? $module:'default'
                    );

                    $extDirectData[$i][$config['indexKey']] = count($this->_requestStack)-1;

                    foreach ($config['additionalParams'] as $pKey => $pValue) {
                        $req->setParam($pKey, $pValue);
                    }

                    if (!isset($extDirectData[$i]['data'])) {
                      $extDirectData[$i]['data'] = array();
                    }

                    foreach ($extDirectData[$i]['data'] as $pKey => $pValue) {
                        $req->setParam($pKey, $pValue);
                    }

                    $this->_applyParams($req, $extDirectData[$i]);
                }

                foreach ($config['additionalParams'] as $pKey => $pValue) {
                    $request->setParam($pKey, $pValue);
                }

                /*if ($config['module']) {
                    $request->setModuleName($config['module']);
                }
                if ($config['controller']) {
                    $request->setControllerName($config['controller']);
                }
                if ($config['action']) {
                    $request->setActionName($config['action']);
                }*/

                $this->_copyParams();
                $this->_copyHelper();
                $this->_copyPlugins();

            } else {
                $this->_isExtRequest = true;
                $this->_applyParams($request, $extDirectData);
            }
        }

        $this->_processed = true;

    }

    /**
     * Method gets called when the PostDispatcher's dispatchLoopShutdown
     * method gets called.
     * This method is API only.
     * Note, that this method only gets processed for the initial request that
     * has been made, not for requests found on the requestStack.
     *
     * @packageprotected
     */
    public function notifyDispatchLoopShutdown()
    {
        $config         =& $this->_config;
        $postDispatcher = $this->_postDispatcher;

        if (!$this->_isExtMultiRequest ||
            $postDispatcher->getRequest()->getParam($config['indexKey'], null) !== null) {
            return;
        }

        $front = Zend_Controller_Front::getInstance();

        $orgResponse = clone $postDispatcher->getResponse();
        $orgRequest  = clone $postDispatcher->getRequest();

        if ($orgResponse->isException()) {
            // we have neither control nor access over the
            // exceptions that might get thrown here.
            // set front's exception throwing to true
            $front->throwExceptions(true);
            $this->_mergeInitException($orgResponse);
        } else {
            // call to the loop
            $this->_processRequestStack($front, $orgResponse);
        }

        $this->_resetHelper();
        $this->_resetPlugins();
        $this->_resetParams();

        foreach ($config['additionalHeaders'] as $type => $value) {
            $orgResponse->setHeader($type, $value);
        }

        $front->setRequest($orgRequest);
        $front->setResponse($orgResponse);
    }


// -------- helper

    /**
     * Decides whether to process the request or not. Requests get processed
     * if either their indexKey param is not set or if the requestInfo for this
     * request was not already processed.
     * If the requestInfo is still available, the method will invoke the
     * appropriate actions and then mark this request as processed.
     *
     * @param Zend_Controller_Request_Abstract $request
     *
     * @return Boolean true if the request was already processed, otherwise false
     */
    protected function _preProcessRequest(Zend_Controller_Request_Abstract $request)
    {

      if (($index = $request->getParam($this->_config['indexKey'], null)) !== null) {

            $info = $this->_requestInfo[$index];
            //logVar($info, 'to info');
            if (!$info) {
                return true;
            }

            $this->_requestInfo[$index] = null;

            $request->setActionName($info['action'])
                    ->setControllerName($info['controller'])
                    ->setModuleName($info['module']);

            return true;
        }

        return false;
    }

    /**
     * Refreshes the parameter list for the specified request.
     *
     * @param Zend_Controller_Request_Abstract $request
     * @param Array $params
     */
    protected function _applyParams(Zend_Controller_Request_Abstract $request, Array $data)
    {
        $rData = empty($data['data']) ? array() : $data['data'];
        $rData['format'] = 'direct';
        $rData['type'] = 'rpc';
        //logVar($data, 'request data');
        if (!Core_Util_Array::isAssociative($data)) {
            $request->setParam('data', $rData);
        } else {
            $module=(isset($data['module']) && !empty($data['module'])) ? $data['module'] : $request->getModuleName();
            $request->setActionName($data['method'])
                        ->setControllerName($data['action'])
                        ->setModuleName($module);
            $request->setParams($rData);

        }
        //logVar($request->getParams(), 'request object');
        //unset($data['data']);

        $request->setParam($this->_config['extParameter'], $data);
    }

    /**
     * Clones all helpers as found in the HelperBroker and stores them.
     */
    protected function _copyHelper()
    {
        $stack = Zend_Controller_Action_HelperBroker::getStack();
        foreach ($stack as $key => $helper) {
            $this->_helperList[$key] = clone $helper;
        }
    }

    /**
     * Clones all initial params set for the front controller
     * and stores them.
     */
    protected function _copyParams()
    {
        $front = Zend_Controller_Front::getInstance();

        $params = $front->getParams();
        foreach ($params as $key => $value) {
            $this->_paramsList[$key] = $value;
        }
    }

    /**
     * Clones all plugins except those plugins registered by this instance
     * one and stores them
     */
    protected function _copyPlugins()
    {
        $front = Zend_Controller_Front::getInstance();
        $plugins = $front->getPlugins();
        foreach ($plugins as $stackIndex => $plugin) {
            if ($plugin == $this->_preDispatcher ||
                $plugin == $this->_postDispatcher) {
                continue;
            }

            $this->_pluginsList[$stackIndex] = clone $plugin;
        }
    }

    /**
     * Resets all helpers to the helpers found in $_helperList.
     */
    protected function _resetHelper()
    {
        Zend_Controller_Action_HelperBroker::resetHelpers();
        foreach ($this->_helperList as $key => $helper) {
            Zend_Controller_Action_HelperBroker::getStack()->offsetSet($key, (clone $helper));
        }
    }

    /**
     * Resets all plugins to the plugins found in $_pluginsList.
     */
    protected function _resetPlugins()
    {
        $front = Zend_Controller_Front::getInstance();

        $plugins = $front->getPlugins();
        $tmp = array();
        foreach ($plugins as $stackIndex => $plugin) {
            if ($plugin == $this->_preDispatcher ||
                $plugin == $this->_postDispatcher) {
                continue;
            }
            $front->unregisterPlugin($plugin);
            $plugin = null;
        }

        $i = 0;
        foreach ($this->_pluginsList as $stackIndex => $plugin) {
            $pluginsIndex = array_keys($front->getPlugins());
            $i = $stackIndex;
            // workaround needed since we do not remove THIS plugin, which makes
            // the implementation in the PluginBroker in registerPlugin count funny
            while (isset($pluginsIndex[$i])) {
                $i++;
            }

            $front->registerPlugin((clone $this->_pluginsList[$stackIndex]), $i);
        }
    }

    /**
     * Resets the parameters for the front controller to the params found
     * in $_paramsList.
     * If the optional array is specified, copies those values into the
     * param list, too.
     *
     * @param Array $params
     */
    protected function _resetParams(Array $additionalParameters = array())
    {
        $front = Zend_Controller_Front::getInstance();

        $nullParams = $front->getParams();

        $nullParams = array_fill_keys(array_keys($nullParams), null);

        // result will hold the complete nullified params, overwritten
        // by the original values of the front controller, overwritten
        // by additional parameters
        $result = array_merge($nullParams, $this->_paramsList, $additionalParameters);

        $front->setParams($result);
    }

    /**
     * Copies an exception found in the response of the initial request
     * into the responses of teh requests found in the requestStack.
     *
     *
     * @param Zend_Controller_Response_Abstract $response
     */
    protected function _mergeInitException(Zend_Controller_Response_Abstract $response)
    {
        $result = Core_Controller_DispatchHelper::mergeExceptions(
            $response->getException()
        );

        $response->clearBody();
        $response->setBody(Zend_Json::encode($result));
    }

    /**
     * Processes the requestStack and merges their responses into $response.
     *
     * @param Zend_Controller_Front $front
     * @param Zend_Controller_Response_Abstract $response
     *
     */
    protected function _processRequestStack($front, Zend_Controller_Response_Abstract $response)
    {
        $stack  =& $this->_requestStack;
        $config =& $this->_config;

        $i = count($stack)-1;

        $responseStack = array();

        $front->returnResponse(true);
        do {
            $myResponse = new Zend_Controller_Response_Http();

            $nextRequest = array_pop($stack);
            $nextRequest->setParam($config['indexKey'], $i);

            $this->_resetHelper();
            $this->_resetPlugins();
            $this->_resetParams($nextRequest->getParams());

            $front->setRequest($nextRequest);
            $front->setResponse($myResponse);

            $responseStack[] = $front->dispatch($nextRequest, $myResponse);
            resetApplication();
        } while ($i--);
        $front->returnResponse(false);

        $bodies = array();
        for ($i = 0, $len = count($responseStack); $i < $len; $i++) {
            $bodies[] = $responseStack[$i]->getBody();
        }

        $body = implode(',', $bodies);

        $response->setBody('[' . $body . ']');
    }

}