<?php
/**
 * @name IndexController
 * @author patris
 * @desc Default Controller
 * @see http://www.php.net/manual/en/class.yaf-controller-abstract.php
 */
class IndexController extends Yaf_Controller_Abstract {

	/** 
     * Default Action
     * Yaf supports directly Yaf_Request_Abstract::getParam() get the parameter with the same name  as Action parameters
     * For the following example, when accessing http://yourhost/sample/index/index/index/name/patris you will get the different name param
     */
	public function indexAction($name = "Stranger") {
		//1. fetch query
		$get = $this->getRequest()->getQuery("get", "default value");

		//2. fetch model
		$model = new SampleModel();

		//3. assign
		$this->getView()->assign("content", $model->selectSample());
		$this->getView()->assign("name", $name);

		//4. render by Yaf. If return FALSE here YAF would not invoke view renderer
        return TRUE;
	}
}
