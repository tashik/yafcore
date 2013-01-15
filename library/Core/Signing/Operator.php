<?php

class Core_Signing_Operator {

  public static function sign($data) {
    $certificateDn = getConfigValue('general->operator->certificate', null);
    if (empty($certificateDn)) {
      throw new ResponseException('Не настроен сертификат оператора');
    }

    $result = Core_Crypto_Signature::sign($data, $certificateDn);
    if (false === $result) {
      throw new ResponseException('Не удалось подписать: ' . Core_Crypto_Signature::$last_error);
    }

    return $result;
  }

}