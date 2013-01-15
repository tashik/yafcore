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
 * @uses Zend_Controller_Plugin_Abstract
 * @package Core_Controller
 * @subpackage Plugin
 * @category Plugins
 *
 * @author Thorsten Suckow-Homberg <ts@siteartwork.de>
 */
class Core_Controller_Plugin_ExtRequest_PostDispatcher extends Zend_Controller_Plugin_Abstract {

    /**
     * @var Core_Controller_Plugin_ExtRequest $_extDirect
     */
    protected $_extDirect = null;

    /**
     * Constructor.
     *
     *
     * @param Core_Controller_Plugin_ExtRequest $extDirect
     */
    public function __construct(Core_Controller_Plugin_ExtRequest $extDirect)
    {
        $this->_extDirect = $extDirect;
    }

    /**
     * Called when the fonts dispatchLoop shuts down.
     *
     * @return void
     */
    public function dispatchLoopShutdown()
    {
        $this->_extDirect->notifyDispatchLoopShutdown();
    }

}