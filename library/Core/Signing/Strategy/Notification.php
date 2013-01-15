<?php

class Core_Signing_Strategy_Notification extends Core_Signing_Strategy_Sign {

  const STAGE_LAST_AUTHOR   = 2;
  const STAGE_LAST_RECEIVER = 3;

  /**
   * @return int
   */
  public function getLastAuthorSignStage() {
    return self::STAGE_LAST_AUTHOR;
  }

  /**
   * @return int
   */
  public function getLastReceiverSignStage() {
    return self::STAGE_LAST_RECEIVER;
  }

  public function getSignatureText(Model_Doc $document, Model_User $user) {
    if ($document->isAuthor($user)) {
      return $this->getAuthorSignatureText($document, $user);
    }
    if ($document->isReceiver($user)) {
      return $this->getReceiverSignatureText($document, $user);
    }
    return array();
  }

  public function sign(Model_Doc $document, array $params) {
    //@todo: Костыль {{{
    if ($document->getType() instanceof Model_DocType_SubInvoice && isSet($params['type'])) {
      $type = $document->getType(); /** @var Model_DocType_SubInvoice $type */
      if ($type->hasUnconfirmedCorrectionRequest($document) && in_array('correction_requested', $params['type'])) {
        $correctionParams         = $params;
        $correctionParams['type'] = 'correction_requested';
        unSet($correctionParams['sign_text']);
        $correctionDoc = $type->getWithCorrectionRequest($document);
        $this->confirmNotification($correctionDoc, $correctionParams);
      }
    }
    if (isSet($params['type']) && is_array($params['type']) && !in_array('sign_text', $params['type'])) {
      $this->confirmNotification($document, $params);
      return;
    }
    //}}}
    parent::sign($document, $params);
    $document->confirmOperatorReception();
  }

  public function sendNotification(Model_Doc $document, array $params) {
    $signature = $this->checkSignature($params);
    if (!$document->getSignatures()->isSigned()) {
      throw new ResponseException('Документ ещё не подписан автором');
    }
    if (empty($params['type'])) {
      throw new ResponseException('Не задано генерируемое уведомление');
    }

    $notification = $this->buildNotification($document, $params);
    if (null === $notification) {
      throw new ResponseException('Не поддерживается');
    }

    $docToSign = $notification->getGeneratedDocument();
    $docToSign->getSignStrategy()->sign($docToSign, $params);

    //@todo: Костыль
    $userId   = null;
    $operator = array('reception_confirmation', 'send_confirmation', 'document_confirmation');
    if (in_array($params['type'], $operator)) {
      $userId = (int)getConfigValue('general->operator->user_id');
    } elseif ('correction_requested' !== $params['type']) {
      if ($docToSign->getAuthor()->getUserId() === $document->getAuthor()->getUserId()) {
        $this->incrementAuthorStage($document);
      } else {
        $this->incrementReceiverStage($document);
      }
    }

    $document->addHistoryItem(Model_Notification::$subtypes[$params['type']], $docToSign, true, $userId);
    $document->save();
  }

  public function confirmNotification(Model_Doc $document, array $params) {
    $signature = $this->checkSignature($params);
    if (!$document->getSignatures()->isSigned()) {
      throw new ResponseException('Документ ещё не подписан автором');
    }

    if (empty($params['type'])) {
      throw new ResponseException('Нет уведомления, которе подтверждается');
    }

    if (!is_array($params['type'])) {
      $params['type'] = array($params['type']);
    }

    $signParams = array(
      'signature'      => $params['signature'],
      'signature_text' => $params['signature_text'],
    );
    foreach ($params['type'] as $type) {
      //todo: с этим что-то надо делать. костыль {{{
      unSet($signParams['type']);
      if ($type === 'document_received') {
        $signParams['type'] = $type;
        $this->sendNotification($document, $signParams);
        continue;
      }
      if ('correction_requested' === $type) {
        $template = array(
          'name' => Model_Notification::$subtypes[$type]
        );
      } else {
        $templateId = Model_Message::$notificationTemplatesMap[$type];
        $template   = Core_Template::findTemplate($templateId);
      }
      $notification = $document->getNotifications()->getByType($type);
      // }}}

      Model_Message::addDocNotificationMessage($template['name'], $params['signature_text'], $document);
      $confirmation = $document->getNotifications()->addConfirmation($type, $notification->getDocId());
      $docToSign    = $confirmation->getGeneratedDocument();
      $docToSign->getSignStrategy()->sign($docToSign, $signParams);

      if (!in_array('correction_requested',$params['type']))   {
        if ($docToSign->getAuthor()->getUserId() === $document->getAuthor()->getUserId()) {
          $this->incrementAuthorStage($document);
        } else {
          $this->incrementReceiverStage($document);
        }
      }

      $confirmationType = Model_DocNotifications::$confirmations[$type];
      $document->addHistoryItem(Model_Notification::$subtypes[$confirmationType], $docToSign, true);
      $document->save();
    }

    //todo: похоже на костыль
    $notifications = $document->getNotifications();
    if ($notifications->getDocumentReceived() && null === $notifications->getDocumentConfirmation()) {
      $document->confirmReceptionDate();
    }

    return true;
  }

  public function getReceiverSignature(Model_Doc $document) {
    if ($document->getNotifications()->getDocumentReceived()) {
      class_exists('Core_Crypto_Signature');
      if (defined('FAKE_CRYPTCP')) {
        return $this->fakeCertificate(false);
      }
      return $this->prepareCertificateInfo($document->getNotifications()->getDocumentReceived()->getGeneratedDocument()->getSignatures()->getReceiverCertificate());
    }
    return array();
  }

  public function isActivityNeed(Model_Doc $document) {
    if (!$document->getSignatures()->isSigned()) {
      return false;
    }
    if (!$document->getNotifications()->isReceived()) {
      return true;
    }
    if ($document->getNotifications()->needConfirmation()) {
      return true;
    }
    return false;
  }

  public function getActivityNeedCriteria() {
    return array(
      'sign_type'     => Core_Signing_Strategy::NOTIFICATION,
      'dates.deleted' => null,
      'dates.signed'  => array('$ne' => null),
      '$or'           => Model_DocNotifications::getNeedConfirmationCriteria()
    );
  }

  public function cosign(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

  public function getAuthorSignatureText(Model_Doc $document, Model_User $user) {
    $result =  array();
    $notifications = $document->getNotifications();
    if ($document->getStatusId() === Model_Doc::STATUS_NEW) {
      if ($document->getType() instanceof Model_DocType_SubInvoice) {
        $type = $document->getType(); /** @var Model_DocType_SubInvoice $type */

        if ($type->hasUnconfirmedCorrectionRequest($document)) {
          $params = array(
            'type' => 'correction_requested',
            'doc'  => $type->getParent($document),
          );
          $messageData = Model_Message::getNotificationTextForDocument($params);
          $result['correction_requested'] = array(
            'title' => Model_Notification::$subtypes['correction_confirmed'],
            'text'  => $messageData['message']
          );
        }
      }

      $result['sign_text'] = array(
        'title' => $document->getTitle(),
        'text'  => $document->createAuthorSignatureText()
      );
    } elseif ($notifications->getReceptionConfirmation()) {
      if (null === $notifications->getReceptionConfirmed()) {
        $params = array(
          'type' => 'reception_confirmation',
          'doc' => $document,
        );
        $messageData = Model_Message::getNotificationTextForDocument($params);
        $result['reception_confirmation'] = array(
          'title' => Model_Notification::$subtypes['reception_confirmed'],
          'text'  => $messageData['message']
        );
      }
    }

    return $result;
  }

  //@toDo порефакторить метод
  public function getReceiverSignatureText(Model_Doc $document, Model_User $user) {
    $result        = array();
    $notifications = $document->getNotifications();

    if ($notifications->getSendConfirmation()) {
      $params = array('doc' => $document);

      if (null === $notifications->getDocumentReceived()) {
        $params['type'] = 'document_received';
        $messageData = Model_Message::getNotificationTextForDocument($params);
        $result['document_received'] = array(
          'title' => Model_Notification::$subtypes['document_received'],
          'text'  => $messageData['message']
        );
      }
      if (null === $notifications->getSendConfirmed()) {
        $params['type'] = 'send_confirmation';
        $messageData = Model_Message::getNotificationTextForDocument($params);
        $result['send_confirmation'] = array(
          'title' => Model_Notification::$subtypes['send_confirmed'],
          'text'  => $messageData['message']
        );
      }
      if (empty($result) && $notifications->getDocumentConfirmation() && null === $notifications->getDocumentConfirmed()) {
        $params['type'] = 'document_confirmation';
        $messageData = Model_Message::getNotificationTextForDocument($params);
        $result['document_confirmation'] = array(
          'title' => Model_Notification::$subtypes['document_confirmed'],
          'text'  => $messageData['message']
        );
      }
    }

    return $result;
  }

  protected function buildNotification(Model_Doc $document, array $params) {
    switch ($params['type']) {
      case 'reception_confirmation': return $this->buildReceptionConfirmation($document, $params);
      case 'send_confirmation':      return $this->buildSendConfirmation($document, $params);
      case 'document_received':      return $this->buildDocumentReceivedConfirmation($document, $params);
      case 'document_confirmation':  return $this->buildDocumentConfirmation($document, $params);
      case 'correction_requested':   return $this->buildCorrectionRequested($document, $params);
      default:                       throw new ResponseException('Неизвестный тип уведомления');
    }
  }

  protected function buildReceptionConfirmation(Model_Doc $document, array $params) {
    //todo: should be signed and not confirmed
    $result = Model_Notification::createReceptionConfirmation($document, 0);
    $document->getNotifications()->setReceptionConfirmation($result);
    $document->getDates()->setOperatorConfirmed(date('c'));
    $document->setStatusId(Model_Doc::STATUS_OPERATOR_CONFIRMED);

    $message = new Model_Message();
    $message_data = array('doc'=>$document);
    $message->addDocReceptionConfirmationMessage($message_data);

    return $result;
  }

  protected function buildSendConfirmation(Model_Doc $document, array $params) {
    //todo: should be signed and not confirmed
    $result = Model_Notification::createSendConfirmation($document, 1);
    $document->getNotifications()->setSendConfirmation($result);
    $document->getDates()->setSent(date('c'));

    $message = new Model_Message();
    $message_data = array('doc'=>$document);
    $message->addDocSendConfirmationMessage($message_data);

    return $result;
  }

  protected function buildDocumentReceivedConfirmation(Model_Doc $document, array $params) {
    //todo: should be signed and confirmed but not receiver confirmed
    $document->ensureReceiver();
    $document->setReceiverReceptionDate();
    $result = Model_Notification::createDocumentReceived(
      $document,
      array('user_id' => getActiveUser()),
      array('user_id' => (int)getConfigValue('general->operator->user_id'))
    );
    $document->setStatusId(Model_Doc::STATUS_RECEPTED);
    $document->getNotifications()->setDocumentReceived($result);

    $message = new Model_Message();
    $message_data = array('doc'=>$document);
    $message->addDocReceivedConfirmationMessage($message_data);

    return $result;
  }

  protected function buildDocumentConfirmation(Model_Doc $document, array $params) {
    $result = Model_Notification::createReceptionConfirmation(
      $document->getNotifications()->getDocumentReceived()->getGeneratedDocument(), 0
    );
    $document->getNotifications()->getDocumentReceived()->setReceiver(
      Model_Contragent::constructContragent('Receiver', array('user_id' => $document->getAuthor()->getUserId()))
    );
    $document->getNotifications()->setDocumentConfirmation($result);
    $message = new Model_Message();
    $message_data = array('doc'=>$document);
    $message->addDocConfirmationMessage($message_data);

    return $result;
  }

  protected function buildCorrectionRequested(Model_Doc $document, array $params) {
    if (!isSet($params['comment'])) {
      throw new ResponseException('Не указана причина запроса корректировки');
    }
    $result = Model_Notification::createCorrectionRequested($document, $params['comment']);
    $document->getNotifications()->setCorrectionRequested($result);

    return $result;
  }

}