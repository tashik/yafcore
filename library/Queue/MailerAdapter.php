<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Queue
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: DbForUpdate.php 20096 2010-01-06 02:05:09Z bkarwin $
 */


/**
 * Class for using connecting to a Zend_Db-based queuing system
 *
 * $config['options'][Zend_Db_Select::FOR_UPDATE] is a new feature that was
 * written after this code was written.  However, this will still serve as a
 * good example adapter
 *
 * @category   Zend
 * @package    Zend_Queue
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Queue_MailerAdapter extends Zend_Queue_Adapter_Db
{

    public function __construct($options, Zend_Queue $queue = null)
    {
        try {
            $db = getRegistryItem('db');

            $this->_messageTable = new Queue_MailerMessage(array(
                'db' => $db,
            ));
        } catch (Zend_Db_Exception $e) {
            throw new Zend_Queue_Exception('Error connecting to database: ' . $e->getMessage(), $e->getCode());
        }
    }

    /*
     * Get an array of all available queues
     *
     * Not all adapters support getQueues(), use isSupported('getQueues')
     * to determine if the adapter supports this feature.
     *
     * @return array
     * @throws Zend_Queue_Exception - database error
     */
    public function getQueues()
    {
        return array('mailingList');
    }

    protected function getQueueId($name)
    {
        return 1;
    }
    /**
     * Return the approximate number of messages in the queue
     *
     * @param  Zend_Queue $queue
     * @return integer
     * @throws Zend_Queue_Exception
     */
    public function count(Zend_Queue $queue = null)
    {
        if ($queue === null) {
            $queue = $this->_queue;
        }

        $info  = $this->_messageTable->info();
        $db    = $this->_messageTable->getAdapter();
        $query = $db->select();
        $query->from($info['name'], array(new Zend_Db_Expr('COUNT(1)')));

        // return count results
        return (int) $db->fetchOne($query);
    }

    /**
     * Return the first element in the queue
     *
     * @param  integer    $maxMessages
     * @param  integer    $timeout
     * @param  Zend_Queue $queue
     * @return array
     */
    public function receive($maxMessages=null, $timeout=null, Zend_Queue $queue=null)
    {
        if ($maxMessages === null) {
            $maxMessages = 1;
        }
        if ($timeout === null) {
            $timeout = self::RECEIVE_TIMEOUT_DEFAULT;
        }
        if ($queue === null) {
            $queue = $this->_queue;
        }

        $msgs = array();

        $info = $this->_messageTable->info();

        $microtime = microtime(true); // cache microtime

        $db = $this->_messageTable->getAdapter();

        try {
            // transaction must start before the select query.
            $db->beginTransaction();

            // changes: added forUpdate
            $query = $db->select()->forUpdate();
            $query->from($info['name'], array('*'));
            $query->where('emailed=?', 'false');
            $query->where('private=?', 'false');
            $query->where('email is not null');
//            $query->where('email = ?', 'vera.dot.gulina@gmail.com');
            $query->where('handle IS NULL');
            $query->limit($maxMessages);

            foreach ($db->fetchAll($query) as $data) {
                // setup our changes to the message
                $data['handle'] = md5(uniqid(rand(), true));

                $update = array(
                    'handle'  => $data['handle']
                );

                // update the database
                $where = array();
                $where[] = $db->quoteInto('id=?', $data['id']);

                $count = $db->update($info['name'], $update, $where);

                // we check count to make sure no other thread has gotten
                // the rows after our select, but before our update.
                if ($count > 0) {
                    $msgs[] = $data;
                    logStr('Получение сообщений:' . $data['id'] . ' byte size=' . strlen($data['body']), 'mail_log');
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            logStr('Операции получения сообщений не удалось: '. $e->getMessage(), 'mail_log');
            /**
             * @see Zend_Queue_Exception
             */
            require_once 'Zend/Queue/Exception.php';
            throw new Zend_Queue_Exception($e->getMessage(), $e->getCode());
        }

        return $msgs;
    }

    /**
     * Delete a message from the queue
     *
     * Returns true if the message is deleted, false if the deletion is
     * unsuccessful.
     *
     * @param  string $messageHandle
     * @return boolean
     * @throws Zend_Queue_Exception - database error
     */
    public function removeMessage($messageHandle)
    {
        $db    = $this->_messageTable->getAdapter();
        $db->query('update mail_log set emailed=true, datetime_sent=now() where '.$db->quoteInto('handle=?', $messageHandle));
    }

}
