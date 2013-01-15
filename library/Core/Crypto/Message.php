<?php
require_once 'Crypto_Bootstrap.php';

class Core_Crypto_Message
{
  const TEMPLATE="-------- Cообщение, заверенное средствами ЭТП --------\n\n{MESSAGE}\n------ Информация об отправителе данного сообщения ------\nНаименование электронной торговой площадки: {ETP_TITLE}\nАдрес электронной торговой площадки в сети Интернет: {ETP_URL}\n\n------ Информация о подлинности данного сообщения ------\n{DESCRIPTION}\n\n---------------- Контрольная сумма сообщения -----------------\n{SIGNATURE}\n\n----- Конец сообщения, заверенного средствами ЭТП -----";
  const MESSAGE_POS = 1;
  const DIGEST_POS = 5;

  static public function normalizeMessage($msg)
  {
    $msg=strip_tags($msg);
    $msg=preg_replace('@[\s]+@m', ' ', $msg);

    return mb_trim($msg);
  }

  static public function check($message)
  {
    $ret=array('success'=>false, 'msg'=>'Данное сообщение средствами ЭТП не отправлялось');
    $parser=self::TEMPLATE;
    $parser=preg_replace('@{.+}@U', '(.+)', $parser);
    if (preg_match("@$parser@s", $message, $matches))
    {
      $msg=self::normalizeMessage($matches[self::MESSAGE_POS]);
      $sign=$matches[self::DIGEST_POS];
      $sign=unserialize(base64_decode($sign));
      if ( !isset($sign['hash']) || !isset($sign['date']) || !isset($sign['keyid']) )
      {
        $ret['msg']='Сообщение сформировано некорректно';
        return $ret;
      }
      if ( 0!==$sign['keyid'] )
      {
        $ret['msg']='Неправильный ключ подписи';
        return $ret;
      }
      $hash=Core_Crypto_Hash::String($msg.$sign['date'].SERVER_KEY);
      if ( $hash!==$sign['hash'] )
      {
        $ret['msg']='Внимание! Это сообщение не отправлялось системой или было модифицировано!';
        return $ret;
      }
      $ret['success']=true;
      $ret['msg']='Сообщение прошло проверку подлинности';
      $ret['timestamp']=$sign['date'];
      $ret['date']=formatTimestamp($sign['date']);
      $ret['text']=$msg;
    }
    return $ret;
  }

  static public function create($message)
  {
    $time=time();
    $signature=array('hash'=>Core_Crypto_Hash::String(self::normalizeMessage($message).$time.SERVER_KEY),
                    'date'=>$time,
                    'keyid'=>0);
    if (!function_exists('getConfigValue'))
    {
      require_once APPLICATION_PATH.'/../library/Util/functions.php';
    }
    $url = getServerUrl();
    $title = getConfigValue('general->etp->title');
    $data=array('message'=>$message,
                'signature'=>formatLine(base64_encode(serialize($signature))),
                'etp_url'=>$url,
                'etp_title'=>$title,
                'description'=>"Данное сообщение сформировано и направлено автоматизированными средствами электронной торговой площадки. Его подлинность можно проверить по адресу {$url}verify",
               );
    return Core_Template::process(preg_replace('@<br\s?/>@u', "\n", self::TEMPLATE), $data);
  }
}
