<?php

class Queue_MailerMessage extends Zend_Db_Table_Abstract
{
    /**
     * @var string
     */
    protected $_name = 'mail_log';

    /**
     * @var string
     */
    protected $_primary = 'id';

    /**
     * @var mixed
     */
    protected $_sequence = true;
}
