<?php

class Queue_CoreDaoQueue extends Zend_Queue
{
  function __construct($queueName) {

    $options = $this->_initQueueOptions($queueName);

    parent::__construct('Array', $options);

    $adapter = new Queue_CoreDaoAdapter(array());
    $adapter->setDaoStorageClassName('Model_ZQMessage');

    $this->setAdapter($adapter);
  }

  protected function _initQueueOptions($queueName) {

    $options = array(
      'name'          => $queueName,
      'options' => array(
        Zend_Db_Select::FOR_UPDATE => true
      )
    );

    return $options;
  }
}
