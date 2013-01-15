<?php

class Core_Queue extends Zend_Queue_Adapter_AdapterAbstract {

  protected $_table = null;
  protected $_idColumn = 'id';
  protected $_sentColumn = 'date_sent';
  protected $_extraUpdateColumns = array();

  public function __construct($options, Zend_Queue $queue = null) {
    parent::__construct($options, $queue);

    //logVar($this->_options, 'queue options');

    if (!isset($this->_options['options']['table'])) {
      throw new Zend_Queue_Exception('Options array item: table must be specified');
    }
    $this->_table = $this->_options['options']['table'];
    if (isset($this->_options['options']['sentColumn'])) {
      $this->_sentColumn = $this->_options['options']['sentColumn'];
    }
    if (isset($this->_options['options']['idColumn'])) {
      $this->_idColumn = $this->_options['options']['idColumn'];
    }
  }

  public function count(Zend_Queue $queue = null) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = Zend_Registry::get('db');

    $query = $db->select();
    $query->from($this->_table, array(new Zend_Db_Expr('COUNT(1)')))
          ->where("{$this->_sentColumn} IS NULL");

    return intval($db->fetchOne($query));
  }

  public function send($message, Zend_Queue $queue = null) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = Zend_Registry::get('db');
    if (is_object($message)) {
      $message = $message->toArray();
    }
    unset($message[$this->_idColumn]);
    unset($message[$this->_sentColumn]);
    $db->insert($this->_table, $message);
    return new Zend_Queue_Message(array('data'=>$message));
  }

  public function receive($maxMessages = null, $timeout = null, Zend_Queue $queue = null) {
    if ($queue === null) {
      $queue = $this->_queue;
    }
    if ($maxMessages === null) {
      $maxMessages = 1;
    }
    if (!DbTransaction::$in_transaction) {
      throw new Zend_Queue_Exception("Queue processing must be done in transaction!");
    }
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = Zend_Registry::get('db');
    $select = $db->select()->from($this->_table)
                 ->where("{$this->_sentColumn} IS NULL")
                 ->forUpdate()
                 ->order("{$this->_idColumn} ASC")
                 ->limit($maxMessages);
    $messages = array(
      'queue'        => $queue,
      'data'         => $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC),
      'messageClass' => $queue->getMessageClass(),
    );
    $classname = $queue->getMessageSetClass();
    return new $classname($messages);
  }

  public function deleteMessage(Zend_Queue_Message $message) {
    $sent_col = $this->_sentColumn;
    $message->$sent_col = 'now';
    //logVar($message->toArray(), 'message');
    return $this->updateMessage($message);
  }

  public function updateMessage(Zend_Queue_Message $message) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = Zend_Registry::get('db');
    $id_col = $this->_idColumn;
    $sent_col = $this->_sentColumn;

    $where = $db->quoteInto("{$this->_idColumn}=?", $message->$id_col);

    $data = array(
      $sent_col => $message->$sent_col
    );
    foreach ($this->_extraUpdateColumns as $col) {
      $data[$col] = $message->$col;
    }
    //logVar($data, 'data');

    return !!$db->update($this->_table, $data, $where);
  }

  public function setExtraUpdateColumns($columns) {
    if (!is_array($columns)) {
      $columns = array($columns);
    }
    $this->_extraUpdateColumns = $columns;
    return $this;
  }

  public function getCapabilities() {
    return array(
        'create' => false,
        'delete' => false,
        'send' => true,
        'receive' => true,
        'deleteMessage' => true,
        'getQueues' => false,
        'count' => true,
        'isExists' => false,
    );
  }

  /**
   *
   * @param type $table
   * @param type $sentColumn
   * @param type $idColumn
   * @return Zend_Queue
   */
  public static function get($table, $sentColumn = 'date_sent', $idColumn = 'id') {
    $options = array(
      'options' => array(
        'table' => $table,
        'sentColumn' => $sentColumn,
        'idColumn' => $idColumn,
      )
    );
    $adapter = new self($options);
    return new Zend_Queue($adapter, array());
  }

  public function delete($name) {
    throw new Zend_Queue_Exception('Not implemented');
  }
  public function create($name, $timeout = null) {
    throw new Zend_Queue_Exception('Not implemented');
  }
  public function isExists($name) {
    throw new Zend_Queue_Exception('Not implemented');
  }
  public function getQueues() {
    throw new Zend_Queue_Exception('Not implemented');
  }
}
