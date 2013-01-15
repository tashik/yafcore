<?php

class Queue_NotificationQueue extends Queue_CoreDaoQueue
{

  const ADMIN_NOTIFICATIONS_QUEUE_NAME = 'AdminNotifications';


  function __construct() {
    parent::__construct(self::ADMIN_NOTIFICATIONS_QUEUE_NAME);
  }

  public function createAdminNotification($data) {
    $this->send($data);
  }

  public function sendAdminNotifications() {
    $messages = $this->receive(1);

    $mailerQueue = new Queue_MailerQueue();

    if (!$messages) {
      return;
    }

    foreach ($messages as $message) {
      $receivers = array();

      $params = $message->body;

      if (isset($params['for_all']) && $params['for_all']) {
        $receivers = Model_Business::findContragents(array('add_users'=>true, 'all'=>true));
      } else if (isset($params['receiver']) && $params['receiver']) {
        $receivers = Model_Business::findContragents(array(
          'add_users'=>true,
          'all'=>true,
          'select'=>array('business_id' => $params['receiver']))
        );
      }

      if (!$receivers) {
        return;
      }

      foreach($receivers['rows'] as $receiver) {
        $messageCollection = new Model_Message();
        $params['receiver'] = array('business_id' => $receiver['id']);
        $messageCollection->addMessage($params);

        foreach ($receiver['users'] as $user) {
          $mailData = array(
            'subject'=>$params['subject'],
            'body'=>$params['content'],
            'email'=>$user['email'],
            'receiver_user_id'=>$user['id'],
            'datetime_sent'=>date('c')
          );
          $mailerQueue->send($mailData);
        }
      }

      $this->deleteMessage($message);
    }
  }
}
