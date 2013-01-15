<?php
/**
 * conjoon
 * (c) 2002-2010 siteartwork.de/conjoon.org
 * licensing@conjoon.org
 *
 * $Id$
 */

/**
 *
 *
 * @uses Zend_Controller_Plugin_Abstract
 * @package Core_Controller
 * @subpackage Plugin
 * @category Plugins
 *
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */
class Core_Controller_Plugin_ExtRequest_PreDispatcher extends Zend_Controller_Plugin_Abstract {

    /**
     * @var Core_Controller_Plugin_ExtRequest $_extDirect
     */
    protected $_extDirect = null;

    protected $_raw_data = null;

    /**
     * Constructor.
     *
     *
     * @param Core_Controller_Plugin_ExtRequest $extDirect
     */
    public function __construct(Core_Controller_Plugin_ExtRequest $extDirect, $raw_data = null)
    {
        $this->_extDirect = $extDirect;
        $this->_raw_data = $raw_data;
    }

    /**
     * Called before the request is in the dispatch loop.
     *
     * @param  Zend_Controller_Request_Abstract $request
     *
     * @return void
     */
    public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request)
    {
      //logVar($request->getParams(), 'request params predispatch');  
      $this->_extDirect->notifyDispatchLoopStartup($request, $this->_raw_data);
    }

}