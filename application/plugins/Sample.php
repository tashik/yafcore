<?php
/**
 * @name SamplePlugin
 * @desc Yaf defined 6 Hooks, the execution order is preserved in the notation below
 * @see http://www.php.net/manual/en/class.yaf-plugin-abstract.php
 * @author tashik
 */
class SamplePlugin extends My_Plugin_Abstract {

	public function routerStartup(My_Request_Abstract $request, My_Response_Abstract $response) {
	}

	public function routerShutdown(My_Request_Abstract $request, My_Response_Abstract $response) {
	}

	public function dispatchLoopStartup(My_Request_Abstract $request, My_Response_Abstract $response) {
	}

	public function preDispatch(My_Request_Abstract $request, My_Response_Abstract $response) {
	}

	public function postDispatch(My_Request_Abstract $request, My_Response_Abstract $response) {
	}

	public function dispatchLoopShutdown(My_Request_Abstract $request, My_Response_Abstract $response) {
	}
}
