<?php
class Queue_MailerQueue extends Queue_CoreDaoQueue {
  const MAILER_QUEUE_NAME = 'Mailer';

  function __construct() {
    parent::__construct(self::MAILER_QUEUE_NAME);
  }

}
?>
