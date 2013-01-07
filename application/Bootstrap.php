<?php
/**
 * @name Bootstrap
 * @author tashik
 * @desc Set "_init" beginning to all the methods of Bootstrap Class, so that YAF could call them automatically,
 * @see http://www.php.net/manual/en/class.yaf-bootstrap-abstract.php
 * These methods accept a parameter: Yaf_Dispatcher $dispatcher
 * Calling the order and affirm the order
 */
class Bootstrap extends My_Bootstrap_Abstract{

    public function _initConfig() {
		//Configuration load
		$arrConfig = My_Application::app()->getConfig();
		My_Registry::set('config', $arrConfig);
	}

	public function _initPlugin(Yaf_Dispatcher $dispatcher) {
		//Register a plugin
		$objSamplePlugin = new SamplePlugin();
		$dispatcher->registerPlugin($objSamplePlugin);
	}

	public function _initRoute(Yaf_Dispatcher $dispatcher) {
		//Register own routing protocol used by default simple routing
	}

	public function _initView(Yaf_Dispatcher $dispatcher){
		//register own template engine, for example smarty,firekylin
	}
}
