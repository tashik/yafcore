<?php

class Queue_CoreDaoAdapter extends  Zend_Queue_Adapter_AdapterAbstract
{

//  /**
//   * @var Core_DAO
//   */
//  protected $daoStorage = null;

  /**
   * @var string
   */
  protected $daoStorageClassName = null;

  /**
   * @param string $daoStorageClassName
   */
  public function setDaoStorageClassName($daoStorageClassName)
  {
    $this->daoStorageClassName = $daoStorageClassName;
  }




  /**
   * Does a queue already exist?
   *
   * Use isSupported('isExists') to determine if an adapter can test for
   * queue existance.
   *
   * @param  string $name Queue name
   * @return boolean
   */
  public function isExists($name)
  {
    // TODO: Implement isExists() method.
  }

  /**
   * Create a new queue
   *
   * Visibility timeout is how long a message is left in the queue
   * "invisible" to other readers.  If the message is acknowleged (deleted)
   * before the timeout, then the message is deleted.  However, if the
   * timeout expires then the message will be made available to other queue
   * readers.
   *
   * @param  string  $name Queue name
   * @param  integer $timeout Default visibility timeout
   * @return boolean
   */
  public function create($name, $timeout = null)
  {
    // TODO: Implement create() method.
  }

  /**
   * Delete a queue and all of its messages
   *
   * Return false if the queue is not found, true if the queue exists.
   *
   * @param  string $name Queue name
   * @return boolean
   */
  public function delete($name)
  {
    // TODO: Implement delete() method.
  }

  /**
   * Get an array of all available queues
   *
   * Not all adapters support getQueues(); use isSupported('getQueues')
   * to determine if the adapter supports this feature.
   *
   * @return array
   */
  public function getQueues()
  {
    // TODO: Implement getQueues() method.
  }

  /**
   * Return the approximate number of messages in the queue
   *
   * @param  Zend_Queue|null $queue
   * @return integer
   */
  public function count(Zend_Queue $queue = null)
  {

    $daoStorage = $this->getDaoStorage();

    $params = array(
      'select'=> array('queue_name' => $this->_queue->getName()),
      'count'=>true
    );

    list($rows, $total) = $daoStorage->findByCriteria($params);

    return $total;
  }

  /**
   * Send a message to the queue
   *
   * @param  mixed $message Message to send to the active queue
   * @param  Zend_Queue|null $queue
   * @return Zend_Queue_Message
   */
  public function send($message, Zend_Queue $queue = null)
  {

    if ($queue === null) {
      $queue = $this->_queue;
    }

    if (is_string($message)) {
      $md5 = md5($message);
    } else {
      $md5 = md5(serialize($message));
    }

    $daoStorage = $this->getDaoStorage();

    $data = array(
      'queue_name' => $queue->getName(),
      'body' => $message,
      'md5' => $md5,
      'timeout' => 0,
      'created' => date('c')
    );

    $daoStorage->_setData($data);

    $daoStorage->save();

  }

  /**
   * Get messages in the queue
   *
   * @param  integer|null $maxMessages Maximum number of messages to return
   * @param  integer|null $timeout Visibility timeout for these messages
   * @param  Zend_Queue|null $queue
   * @return Zend_Queue_Message_Iterator
   */
  public function receive($maxMessages = null, $timeout = null, Zend_Queue $queue = null)  {

    if ($maxMessages === null) {
      $maxMessages = 1;
    }
    if ($timeout === null) {
      $timeout = self::RECEIVE_TIMEOUT_DEFAULT;
    }

    if ($queue === null) {
      $queue = $this->_queue;
    }

    $daoStorage = $this->getDaoStorage();

    $messages = $daoStorage->findByCriteria(array(
      'fields'=>array('body', 'timeout', 'md5', 'created'),
      'select'=>array('queue_name'=>$queue->getName()),
      'limit'=>$maxMessages
    ));

    foreach ($messages as &$message) {

      $message['handle'] = md5(uniqid(rand(), true));

      $id = $message['_id']->__toString();
      $row = new $this->daoStorageClassName($id);

      $row->setHandle($message['handle']);
      $row->save();
    }

    $options = array(
      'queue'        => $queue,
      'data'         => $messages,
      'messageClass' => $queue->getMessageClass(),
    );

    $classname = $queue->getMessageSetClass();
    if (!class_exists($classname)) {
      //*** require_once 'Zend/Loader.php';
      Zend_Loader::loadClass($classname);
    }
    return new $classname($options);

  }

  /**
   * Delete a message from the queue
   *
   * Return true if the message is deleted, false if the deletion is
   * unsuccessful.
   *
   * @param  Zend_Queue_Message $message
   * @return boolean
   */
  public function deleteMessage(Zend_Queue_Message $message)
  {

    $daoStorage = $this->getDaoStorage();

    $params = array(
      'handle' => $message->handle
    );

    $messages = $daoStorage->findByCriteria(array(
      'select'=>$params,
      'limit'=>1
    ));

    foreach ($messages as &$message) {

      $id = $message['_id']->__toString();

      $daoStorage->remove($id);
    }

    return false;
  }

  /**
   * Return a list of queue capabilities functions
   *
   * $array['function name'] = true or false
   * true is supported, false is not supported.
   *
   * @return array
   */
  public function getCapabilities()
  {
    return array(
      'create'        => true,
      'delete'        => true,
      'send'          => true,
      'receive'       => true,
      'deleteMessage' => true,
      'getQueues'     => true,
      'count'         => true,
      'isExists'      => true,
    );
  }

  /**
   * @param null $id
   * @return Core_DAO
   * @throws Exception
   */
  protected function getDaoStorage($id=null) {
    $daoStorage = new $this->daoStorageClassName($id);

    if (!($daoStorage instanceof Core_DAO)) {
      throw new Exception('Класс должен быть наследником Core_DAO');
    }

    return $daoStorage;
  }
}
