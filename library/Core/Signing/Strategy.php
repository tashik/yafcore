<?php

abstract class Core_Signing_Strategy {

  const STAGE_UNSIGNED = 0;

  const COSIGN       = 1;
  const NOTIFICATION = 2;
  const SIGN         = 3;

  /**
   * @var Core_Signing_Strategy
   */
  private static
    $notification = null,
    $cosign       = null,
    $sign         = null
  ;

  /**
   * @return boolean
   * @param $value
   */
  public static function isValid($value) {
    if (self::COSIGN === $value) {
      return true;
    }
    if (self::NOTIFICATION === $value) {
      return true;
    }
    if (self::SIGN === $value) {
      return true;
    }
    return false;
  }

  /**
   * Возвращает реализацию схемы подписания для документа
   *
   * @return Core_Signing_Strategy
   * @param Model_Doc $document
   *
   * @throws ResponseException
   */
  public static function getForDocument(Model_Doc $document) {
    return self::getForType($document->getSignType());
  }

  public static function getForType($type) {
    class_exists('Core_Crypto_Signature');
    switch ($type) {
      case self::SIGN:
        return self::signInstance();
      case self::COSIGN:
        return self::cosignInstance();
      case self::NOTIFICATION:
        return self::notificationInstance();
      default:
        throw new ResponseException('Некорректная схема подписания документа');
    }
  }

  /**
   * @return int
   */
  abstract public function getLastAuthorSignStage();

  /**
   * @return int
   */
  abstract public function getLastReceiverSignStage();

  /**
   * Формирует текст подписи для документа $document, который предоставляется пользователю $user.
   * Текст должен формироваться в зависимости от состояния документа.
   *
   * @return array
   * @param Model_Doc $document
   * @param Model_User $user
   */
  abstract public function getSignatureText(Model_Doc $document, Model_User $user);

  /**
   * Выполняет подписание документа отправителем (используется текущий пользователь)
   *
   * @return void
   * @param Model_Doc  $document
   * @param array $params
   */
  abstract public function sign(Model_Doc $document, array $params);

  /**
   * Выполняет подписание документа получателем (используется текущий пользователь)
   *
   * @return void
   * @param Model_Doc  $document
   * @param array $params
   */
  abstract public function cosign(Model_Doc $document, array $params);

  /**
   * Генерирует уведомление об изменении состояния документа
   *
   * @return void
   * @param Model_Doc $document
   * @param array $params
   */
  abstract public function sendNotification(Model_Doc $document, array $params);

  /**
   * Генерирует подтверждение уведомления об изменении состояния документа
   *
   * @return boolean
   * @param Model_Doc $document
   * @param array $params
   */
  abstract public function confirmNotification(Model_Doc $document, array $params);

  /**
   * @param Model_Doc $document
   * @return array
   */
  abstract public function getReceiverSignature(Model_Doc $document);

  /**
   * Возвращает true, если от пользователя требуется что-либо сделать с документом
   *
   * @return boolean
   * @param Model_Doc $document
   */
  abstract public function isActivityNeed(Model_Doc $document);

  /**
   * Возвращает условия отбора документов, для которых от пользователя требуется что-либо сделать с документом
   *
   * @return array
   */
  abstract public function getActivityNeedCriteria();

  protected function checkSignature(array $params) {
    if (!isSet($params['signature'])) {
      throw new ResponseException('Не передан контейнер подписи');
    }
    if (defined('FAKE_CRYPTCP')) {
      return array('EDOTEST');
    }

    $result = Model_User::checkSignature($params['signature'], false);
    if (false === $result) {
      throw new ResponseException('Подпись не прошла проверку', 405);
    }

    return $result;
  }

  protected function operationNotSupported() {
    throw new RuntimeException('Операция не поддерживается');
  }

  /**
   * @return Core_Signing_Strategy_Notification
   */
  private static function notificationInstance() {
    if (null === self::$notification) {
      self::$notification = new Core_Signing_Strategy_Notification();
    }
    return self::$notification;
  }

  /**
   * @return Core_Signing_Strategy_Cosign
   */
  private static function cosignInstance() {
    if (null === self::$cosign) {
      self::$cosign = new Core_Signing_Strategy_Cosign();
    }
    return self::$cosign;
  }

  /**
   * @return Core_Signing_Strategy_Sign
   */
  private static function signInstance() {
    if (null === self::$sign) {
      self::$sign = new Core_Signing_Strategy_Sign();
    }
    return self::$sign;
  }

  public function getSenderSignature(Model_Doc $document) {
    class_exists('Core_Crypto_Signature');
    if (defined('FAKE_CRYPTCP')) {
      return $this->fakeCertificate(true);
    }
    return $this->prepareCertificateInfo($document->getSignatures()->getAuthorCertificate());
  }

  protected function incrementAuthorStage(Model_Doc $document) {
    $document->setAuthorSignStage(
      $this->incrementStage($document->getAuthorSignStage(), $this->getLastAuthorSignStage())
    );
  }

  protected function incrementReceiverStage(Model_Doc $document) {
    $document->setReceiverSignStage(
      $this->incrementStage($document->getReceiverSignStage(), $this->getLastReceiverSignStage())
    );
  }

  protected function incrementStage($stage, $last) {
    if ($stage == $last) {
      throw new RuntimeException('Неверная операция');
    }
    return $stage + 1;
  }

  protected function prepareCertificateInfo($userCertificate) {
    $certificate = array();
    if (!empty($userCertificate)) {
      $certificate['issuer']        = $userCertificate['signed-by']['dn-signed-by']['id-at-commonName'];
      $certificate['user_name']     = $userCertificate['UserFIO'];
      $certificate['user_post']     = $userCertificate['signed-by']['dn']['id-at-title'];
      $certificate['business_name'] = $userCertificate['signed-by']['dn']['id-at-organizationName'];
      $certificate['date_added']    = date('d.m.Y', strToTime($userCertificate['signed-by']['valid-from']));
    }

    return $certificate;
  }

  protected function fakeCertificate($author = true) {
    if ($author) {
      return array(
        'issuer'        => 'ОАО "Тестовый оператор ЭДО"',
        'user_name'     => 'Тестовый автор',
        'user_post'     => 'тестировщик',
        'business_name' => 'ОАО "Разработка ПО"',
        'date_added'    => date('d.m.Y', strToTime('-1 month')),
      );
    }
    return array(
      'issuer'        => 'ОАО "Тестовый оператор ЭДО"',
      'user_name'     => 'Тестовый получатель',
      'user_post'     => 'тестировщик',
      'business_name' => 'ОАО "Покупка ПО"',
      'date_added'    => date('d.m.Y', strToTime('-1 month')),
    );
  }

}