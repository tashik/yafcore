<?php

class Core_Signing_Strategy_Cosign extends Core_Signing_Strategy_Sign {

  /**
   * @return int
   */
  public function getLastAuthorSignStage() {
    return self::STAGE_LAST;
  }

  /**
   * @return int
   */
  public function getLastReceiverSignStage() {
    return self::STAGE_LAST;
  }

  public function getSignatureText(Model_Doc $document, Model_User $user) {
    return array();
  }

  public function cosign(Model_Doc $document, array $params) {
    $signature = $this->checkSignature($params);

    if (!$document->getSignatures()->isSigned()) {
      throw new ResponseException('Документ не подписан отправителем');
    }
    if ($document->getSignatures()->isCosigned()) {
      throw new ResponseException('Документ уже подписан получателем');
    }

    $document->getDates()->setCosigned(date('c'));
    $document->setReceiver(Model_Contragent::constructContragent('Receiver', array('user_id' => getActiveUser())));
    $document->setStatusId(Model_Doc::STATUS_COSIGNED);
    $document->getSignatures()->addReceiver($params['signature'], $signature);
    $document->addHistoryItem(Model_DocHistory::COSIGNED, $document, true);
    $this->incrementReceiverStage($document);
    $document->save();

    $office = Model_Office::getMainOffice($document->getAuthor()->getBusinessId());
    $email = $office['email'];
    $message = new Model_Message();
    $message->addDocSignMessage(array(
      'doc'      => $document,
      'receiver'   => $document->getAuthor()->toArray(), //меняем местами автора и получателя, чтобы уведомление ушло автору документа
      'author' => $document->getReceiver()->toArray(),
      'email' => $email,
    ));
  }

  public function getReceiverSignature(Model_Doc $document) {
    if ($document->getSignatures()->isCosigned()) {
      class_exists('Core_Crypto_Signature');
      if (defined('FAKE_CRYPTCP')) {
        return $this->fakeCertificate(false);
      }
      return $this->prepareCertificateInfo($document->getSignatures()->getReceiverCertificate());
    }
    return array();
  }

  public function isActivityNeed(Model_Doc $document) {
    if ($document->getDates()->getDeleted()) {
      return false;
    }
    if (!$document->getSignatures()->isSigned()) {
      return false;
    }
    if (Model_Doc::STATUS_REJECTED_BY_RECEIVER == $document->getStatusId()) {
      return true;
    }
    if (!$document->getSignatures()->isCosigned()) {
      return true;
    }
    return false;
  }

  public function getActivityNeedCriteria() {
    return array(
      'sign_type'     => Core_Signing_Strategy::COSIGN,
      'dates.deleted' => null,
      'dates.signed'  => array('$ne' => null),
      '$or' => array(
        array(
          'dates.cosigned' => null,
          'status_id'      => array('$ne' => Model_Doc::STATUS_REJECTED_BY_RECEIVER)
        ),
        array(
          'status_id' => Model_Doc::STATUS_REJECTED_BY_RECEIVER
        )
      )
    );
  }

  public function sendNotification(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

  public function confirmNotification(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

}