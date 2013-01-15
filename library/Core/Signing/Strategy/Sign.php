<?php

class Core_Signing_Strategy_Sign extends Core_Signing_Strategy {

  const STAGE_LAST = 1;

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
    return self::STAGE_UNSIGNED;
  }

  public function getSignatureText(Model_Doc $document, Model_User $user) {
    return array();
  }

  public function sign(Model_Doc $document, array $params) {
    $signature = $this->checkSignature($params);

    if (!$document->getReceiver()) {
      throw new ResponseException('Не выбран получатель');
    }
    if ($document->getSignatures()->isSigned()) {
      throw new ResponseException('Документ уже подписан');
    }

    $document->setStatusId(Model_Doc::STATUS_SIGNED);
    $document->getDates()->setSigned(date('c'));
    $document->getSignatures()->addAuthor($params['signature'], $signature);
    $document->addHistoryItem(Model_DocHistory::SIGNED, $document, true);
    $this->incrementAuthorStage($document);
    $document->save();

    if ($document->getType()->needSendMessages()) {
      $office = Model_Office::getMainOffice($document->getReceiver()->getBusinessId());
      $email = $office['email'];
      $message = new Model_Message();
      $message->addDocSignMessage(array(
        'doc'      => $document,
        'author'   => $document->getAuthor()->toArray(),
        'receiver' => $document->getReceiver()->toArray(),
        'email'    => $email
      ));
    }
  }

  public function isActivityNeed(Model_Doc $document) {
    $this->operationNotSupported();
  }

  public function getActivityNeedCriteria() {
    $this->operationNotSupported();
  }

  public function cosign(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

  public function sendNotification(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

  public function confirmNotification(Model_Doc $document, array $params) {
    $this->operationNotSupported();
  }

  public function getReceiverSignature(Model_Doc $document) {
    return array();
  }
}