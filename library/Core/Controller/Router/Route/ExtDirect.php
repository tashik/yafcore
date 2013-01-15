<?php
/**
 * Core_Controller_Router_Route_ExtDirect
 *
 * Ext.Direct JSON route.
 *
 * @uses       Zend_Controller_Router_Route_Abstract
 *
 */

class Core_Controller_Router_Route_ExtDirect extends Zend_Controller_Router_Route_Abstract
{
    /**
     * Default values for the route (ie. module, controller, action, params)
     * @var array
     */
    protected $_defaults;

    protected $_values      = array();
    protected $_moduleValid = false;
    protected $_keysSet     = false;

    /**#@+
     * Array keys to use for module, controller, and action. Should be taken out of request.
     * @var string
     */
    protected $_moduleKey     = 'module';
    protected $_controllerKey = 'controller';
    protected $_actionKey     = 'action';
    /**#@-*/

    /**
     * @var Zend_Controller_Dispatcher_Interface
     */
    protected $_dispatcher;

    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request;

        public function getVersion() {
        return 0;
    }

    /**
     * Constructor
     *
     * @param array $defaults Defaults for map variables with keys as variable names
     * @param Zend_Controller_Dispatcher_Interface $dispatcher Dispatcher object
     * @param Zend_Controller_Request_Abstract $request Request object
     */
    public function __construct(array $defaults = array(),
                Zend_Controller_Dispatcher_Interface $dispatcher = null,
                Zend_Controller_Request_Abstract $request = null)
    {
        $this->_defaults = $defaults;

        if (isset($request)) {
            $this->_request = $request;
        }

        if (isset($dispatcher)) {
            $this->_dispatcher = $dispatcher;
        }
    }

    /**
     * Instantiates route based on passed Zend_Config structure
     */
    public static function getInstance(Zend_Config $config)
    {
        $frontController = Zend_Controller_Front::getInstance();

        $defs       = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        $dispatcher = $frontController->getDispatcher();
        $request    = $frontController->getRequest();

        return new self($defs, $dispatcher, $request);
    }


    /**
     * Matches an Ext.Direct request
     * Assigns and returns an array of defaults on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($request, $partial = false)
    {
                $return = false;
                if (isset($GLOBALS['HTTP_RAW_POST_DATA']))
                {
                        try
                        {
                                $data = Zend_Json_Decoder::decode($GLOBALS['HTTP_RAW_POST_DATA']);
                                if(
                                        is_array($data) &&
                                        count($data) > 0 && (
                                                (isset($data["type"]) && $data["type"] == "rpc") ||
                                                (isset($data[0]["type"]) && $data[0]["type"] == "rpc")
                                        )
                                )
                                {
                                        $params = array();
                                        if ( !isset($data[0]) )
                                        {
                                                $params["extRequest"][0] = $data;
                                        }
                                        else
                                        {
                                                $params["extRequest"] = $data;
                                        }
                                        $return = true;
                                }
                        }
                        catch (Zend_Json_Exception $exception) {}
                }

                if( isset( $_POST['extAction'] ) )
                {
                        $params = array();
                        $params["extRequest"][0] = array();
                        $params["extRequest"][0]["action"]   = $_POST['extAction'];
                        $params["extRequest"][0]["method"]   = $_POST['extMethod'];
                        $params["extRequest"][0]["tid"]      = $_POST['extTID'];
                        $params["extRequest"][0]["type"]     = $_POST['extType'];
                        $params["extRequest"][0]["isUpload"] = $_POST['extUpload'] == 'true';
                        $params["extRequest"][0]["isForm"]   = true;
                        $params["extRequest"][0]["files"]    = $_FILES;
                        $params["extRequest"][0]["data"]     = array();
                        $filterParams = array(
                          "extAction", "extMethod", "extTID", "extType", "extUpload"
                        );
                        foreach( $_POST as $id => $value )
                        {
                                if ( !in_array($id, $filterParams) )
                                {
                                        $params["extRequest"][0]["data"][$id] = $value;
                                }
                        }
                        $return = true;
                }

                if ($return)
                {
                        $values[$this->_moduleKey]     = "beaver";
                        $values[$this->_controllerKey] = "index";
                        $values[$this->_actionKey]     = "ext-direct-routing";
                        $this->_values = $values + $params;
                        return $this->_values + $this->_defaults;
                }
                return false;

                //var_dump(Zend_Json_Decoder::decode($GLOBALS['HTTP_RAW_POST_DATA']));

                exit;
    }

    /**
     * Set request keys based on values in request object
     *
     * @return void
     */
    protected function _setRequestKeys()
    {
        if (null !== $this->_request) {
            $this->_moduleKey     = $this->_request->getModuleKey();
            $this->_controllerKey = $this->_request->getControllerKey();
            $this->_actionKey     = $this->_request->getActionKey();
        }

        if (null !== $this->_dispatcher) {
            $this->_defaults += array(
                $this->_controllerKey => $this->_dispatcher->getDefaultControllerName(),
                $this->_actionKey     => $this->_dispatcher->getDefaultAction(),
                $this->_moduleKey     => $this->_dispatcher->getDefaultModule()
            );
        }

        $this->_keysSet = true;
    }

    /**
     * Assembles user submitted parameters forming a URL path defined by this route
     *
     * @param array $data An array of variable and value pairs used as parameters
     * @param bool $reset Weither to reset the current params
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = array(), $reset = false, $encode = true, $partial = false)
    {
        if (!$this->_keysSet) {
            $this->_setRequestKeys();
        }

        $params = (!$reset) ? $this->_values : array();

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $params[$key] = $value;
            } elseif (isset($params[$key])) {
                unset($params[$key]);
            }
        }

        $params += $this->_defaults;

        $url = '';

        if ($this->_moduleValid || array_key_exists($this->_moduleKey, $data)) {
            if ($params[$this->_moduleKey] != $this->_defaults[$this->_moduleKey]) {
                $module = $params[$this->_moduleKey];
            }
        }
        unset($params[$this->_moduleKey]);

        $controller = $params[$this->_controllerKey];
        unset($params[$this->_controllerKey]);

        $action = $params[$this->_actionKey];
        unset($params[$this->_actionKey]);

        foreach ($params as $key => $value) {
            $key = ($encode) ? urlencode($key) : $key;
            if (is_array($value)) {
                foreach ($value as $arrayValue) {
                    $arrayValue = ($encode) ? urlencode($arrayValue) : $arrayValue;
                    $url .= '/' . $key;
                    $url .= '/' . $arrayValue;
                }
            } else {
                if ($encode) $value = urlencode($value);
                $url .= '/' . $key;
                $url .= '/' . $value;
            }
        }

        if (!empty($url) || $action !== $this->_defaults[$this->_actionKey]) {
            if ($encode) $action = urlencode($action);
            $url = '/' . $action . $url;
        }

        if (!empty($url) || $controller !== $this->_defaults[$this->_controllerKey]) {
            if ($encode) $controller = urlencode($controller);
            $url = '/' . $controller . $url;
        }

        if (isset($module)) {
            if ($encode) $module = urlencode($module);
            $url = '/' . $module . $url;
        }

        return ltrim($url, self::URI_DELIMITER);
    }

    /**
     * Return a single parameter of route's defaults
     *
     * @param string $name Array key of the parameter
     * @return string Previously set default
     */
    public function getDefault($name) {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
    }

    /**
     * Return an array of defaults
     *
     * @return array Route defaults
     */
    public function getDefaults() {
        return $this->_defaults;
    }

}
