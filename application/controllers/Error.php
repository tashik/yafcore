<?php
/**
 * @name ErrorController
 * @desc Error controller, which is called at the moment when uncaught exception occurred
 * @see http://www.php.net/manual/en/yaf-dispatcher.catchexception.php
 * @author tashik
 */
class ErrorController extends My_Controller_Abstract {

	//Directly through the parameters to get an exception
	public function errorAction($exception) {
		//1. assign to view engine
		$this->getView()->assign("exception", $exception);
		//5. render by Yaf
	}
}
