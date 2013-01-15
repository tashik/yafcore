<?php

require_once 'Crypto_Bootstrap.php';

class Core_Crypto_Signature
{
  static public $last_error='';

  static public function writeTemporaryFile($data, $prefix='stmp_')
  {
    if (empty($data))
    {
      self::$last_error='Нет данных для сохранения во временный файл';
      return false;
    }
    $tmpnam=tempnam(sys_get_temp_dir(), $prefix);
    $f=@fopen($tmpnam, 'w');
    if (false===$f)
    {
      self::$last_error='Невозможно открыть временный файл';
      return false;
    }
    $written=fwrite($f, $data);
    fclose($f);
    if (false===$written || strlen($data)!=$written)
    {
      self::$last_error='Запись подписи в файл не удалась';
      unlink($tmpnam);
      return false;
    }
    return $tmpnam;
  }

  static protected function _prepareSignatureFile($signature)
  {
    self::$last_error='';
    $t = base64_decode(preg_replace('@\s*@m', '', $signature), true);
    if ( false!==$t && !empty($t) )
    {
      $signature = $t;
    }
    $tmpnam = self::writeTemporaryFile($signature, 'sign_');
    if (false===$tmpnam)
    {
      return false;
    }
    return $tmpnam;
  }

  // $relaxed: true если не надо проверять валидность подписи (только синтаксически парсит)
  // $encoding: null автодетект, false оставить как есть, строка — форсированно определить кодировку данных
  static public function CheckSignature($data, $relaxed=false, $encoding=null)
  {
    if (defined('FAKE_CRYPTCP')) {
      return array('EDOTEST');
    }
    if ('none'==CRYPTO_MODE && $relaxed !== 'force') {
      return true;
    }
    $tmpnam = self::_prepareSignatureFile($data);
    $t=self::CheckSignatureFile($tmpnam, $relaxed, $encoding);
    @unlink($tmpnam);
    return $t;
  }

  static public function CheckDetachedSignature($signature, $file)
  {
    $tmpnam = self::_prepareSignatureFile($signature);
    $t=self::CheckDetachedSignatureFile($tmpnam, $file);
    @unlink($tmpnam);
    return $t;
  }

  static private function isSignatureValid($file, $skip_chain=false)
  {
    self::$last_error='';
    if (defined('FAKE_CRYPTCP'))
    {
      return file_exists($file);
    }
    if (defined('CRYPTCP_NO_CHAIN'))
    {
      $skip_chain=true;
    }
    //return file_exists($file);
    // TODO: проверить как работает вся эта магия. Пока заглушка.
    $dir = dirname($file);
    $name = basename($file);
    if (!class_exists('Model_Process'))
    {
      require_once APPLICATION_PATH.'/models/Process.php';
    }
    $proc = new Model_Process(CRYPTCP_COMMAND, $dir);

    $argv = array( '-verify' );
    if (defined('CRYPTCP_NO_REV') && !$skip_chain) // -norev и -nochain нельзя использовать одновременно
    {
      array_push($argv, '-norev');
    }
    array_push($argv, ($skip_chain ? '-nochain' : '-errchain'));
    array_push($argv, '-verall');
    array_push($argv, $name );

    $exitCode = $proc->Execute($argv, "C\r\n");

    $out=explode("\n", $proc->getOutput('windows-1251'));
    $code=trim(array_pop($out));
    if (''==$code)
    {
      $code=trim(array_pop($out));
    }
    $msg=trim(array_pop($out));

    if( $exitCode !== 0 )
    {
      array_push($argv, '-m');
      $exitCode = $proc->Execute($argv, "C\r\n");
    }

    if( $exitCode !== 0 )
    {
      array_pop( $argv );
      array_push( $argv, '-f' );
      array_push( $argv, $name );
      $exitCode = $proc->Execute($argv, "C\r\n");
    }

    // здесь еще более странная вещь. После -f должно идти имя файла,
    // из которого нужно брать сертификат. Т.е. имя файла с подписью.
    // Но так некоторые подписи не проверяются!!!
    // А вот если указать несуществующий файл, то подпись проверяется.
    if( $exitCode !== 0 )
    {
      array_pop( $argv );
      array_push( $argv, './no_such_file');
      $exitCode = $proc->Execute($argv, "C\r\n");
    }
    if (0===$exitCode)
      return true;
    self::$last_error="Подпись не прошла проверку (возможно подпись устарела или неверна): $code $msg";
    return 0===$exitCode;
  }

  static public function checkDetachedSignatureFile($signfile, $datafile)
  {
    if ('none'==CRYPTO_MODE) {
      return true;
    }
    self::$last_error='';
    if ( !isFileReadable($signfile) )
    {
      self::$last_error='Файл подписи отсутствует';
      return false;
    }
    if ( !isFileReadable($datafile) )
    {
      self::$last_error='Подписанный файл отсутствует';
      return false;
    }
    if (defined('FAKE_CRYPTCP'))
    {
      return true;
    }
    if (!class_exists('Model_Process'))
    {
      require_once APPLICATION_PATH.'/models/Process.php';
    }
    $proc = new Model_Process(CSPTESTF_COMMAND);

    $argv = array('-sfsign', '-verify', '-detached', '-signature', $signfile, '-in', $datafile);
    $exitCode = $proc->Execute($argv);
    $out=explode("\n", $proc->getOutput('windows-1251'));

    if (0 !== $exitCode)
    {
      $code = trim(array_pop($out));
      if (''==$code)
      {
        $code = trim(array_pop($out));
      }
      $t = trim(array_pop($out));
      $msg = trim(array_pop($out));
      self::$last_error = "$code $msg";
      return false;
    }
    return true;
  }

  static public function CheckSignatureFile($file, $relaxed=false, $encoding=null)
  {
    if (defined('FAKE_CRYPTCP')) {
      return array('EDOTEST');
    }
    if ('none'==CRYPTO_MODE && $relaxed !== 'force') {
//      return true;
    }
    self::$last_error='';
    if ( !file_exists($file) || !is_readable($file) || !is_file($file) )
    {
      self::$last_error='Файл подписи отсутствует';
      return false;
    }
    if ($relaxed !== true && 'none' != CRYPTO_MODE && !self::isSignatureValid($file))
    {
      return false;
    }
    $data=file_get_contents($file);
    if (false===$data)
    {
      return false;
    }
    if (isBase64($data))
    {
      $data=base64_decode($data);
    }
    if (!class_exists('Core_Crypto_ASN1'))
    {
      require_once APPLICATION_PATH.'/../library/Core/Crypto/ASN1.php';
    }
    $data=Core_Crypto_ASN1::parseSMIME($data);
    if (false===$data)
    {
      self::$last_error='Некорректная структура подписи';
      return false;
    }
    //$data=self::getCertData($data[1]['context'][3]['context'][0]); // сертификат подписчика
    $sdata=array();
    if ( $data['signed-by']['dn']['id-at-organizationName'] == $data['signed-by']['dn-signed-by']['id-at-organizationName'] &&
         $data['signed-by']['dn']['e-Mail'] == $data['signed-by']['dn-signed-by']['e-Mail']
       )
    { // это корневой сертификат — подписан самим собой
      self::$last_error='Некорректный сертификат!';
      return false;
    }
    if ( isset($data['signed-by']['dn']['INN']) )
    {
      $sdata['INN']=$data['signed-by']['dn']['INN'];
    }
    if ( isset($data['signed-by']['dn']['OGRN']) )
    {
      $sdata['OGRN']=$data['signed-by']['dn']['OGRN'];
    }
    if ( isset($data['signed-by']['dn']['KPP']) )
    {
      $sdata['KPP']=$data['signed-by']['dn']['KPP'];
    }
    if ( isset($data['signed-by']['dn']['id-at-organizationName']) )
    {
      $sdata['FullName']=$data['signed-by']['dn']['id-at-organizationName'];
    }
    if ( isset($data['signed-by']['dn']['id-at-commonName']) )
    {
      $sdata['UserFIO']=$data['signed-by']['dn']['id-at-commonName'];
    }
    if (!isset($sdata['UserFIO']) && isset($data['signed-by']['dn']['id-at-GivenName']) )
    {
      $sdata['UserFIO']=mb_trim(mb_trim($data['signed-by']['dn']['id-at-surname']).' '.mb_trim($data['signed-by']['dn']['id-at-GivenName']));
    }
    $sdata['signed-by']=$data['signed-by'];
    /*if (!function_exists('is_utf8'))
    {
      require_once APPLICATION_PATH.'/../library/Util/string.php';
    }*/

    if (null === $encoding)
    {
      if (false!==strpos($data['data'], "\x00")) {
        //в строке присутствуют бинарные данные, нельзя перекодировать
        $encoding = false;
      } else {
        $encs = array('UTF-16', 'UTF-16LE', 'UTF-16BE', 'UTF-8', 'windows-1251', 'auto');
        $enc = mb_detect_encoding($data['data'], $encs, true);
        /*echo "*** $enc ***\n";
        $enc='UTF-16LE';*/
        if ( 'UTF-8'==$enc)
        {
          // на самом деле может быть как UTF-8, так и UTF-16. Чтобы поймать последний вариант используем магию
          if (@iconv('UTF-16LE', 'windows-1251', $data['data']))
          {
            $enc='UTF-16LE';
          }
          //$sdata['SignedData']=iconv('windows-1251', 'UTF-8//IGNORE', $data['data']);
          //$sdata['SignedData']=iconv('UTF-16LE', 'UTF-8//IGNORE', $data['data']);
        //} else {
        //  $sdata['SignedData']=$data['data'];
        }
      }
    } else if ($encoding)
    {
      $enc = $encoding;
    }
    if (false !== $encoding)
    {
      $sdata['SignedData']=iconv($enc, 'UTF-8//IGNORE', $data['data']);
    } else {
      $sdata['SignedData']=$data['data'];
    }
    if (false===$sdata['SignedData'])
    {
      $sdata['SignedData']=$data['data'];
    }

    // Заглушка для кривых сертификатов казначейства
    if ((!isset($sdata['INN']) || !isset($sdata['OGRN']))
        // && 'Федеральное казначейство'==$sdata['signed-by']['dn-signed-by']['id-at-organizationName']
       ) // Оказывается издателей с кривыми сертификатами полно
    {
      $sdata['compatibility-mode']=true;
    }

    // Заглушка для кривых и прочих тестовых сертификатов. TODO: убрать
    /*if (!isset($sdata['INN']) || !isset($sdata['OGRN']))
    {
      $base_str=sprintf("%08u\n", crc32($data['signed-by']['dn']['id-at-commonName']));
      $base_str=substr($base_str, 0, 8);
      $sdata['INN']=$base_str.'11';
      $sdata['signed-by']['dn']['INN']=$sdata['INN'];
      $sdata['OGRN']=$base_str.'22222';
      $sdata['signed-by']['dn']['OGRN']=$sdata['OGRN'];
      if (!isset($sdata['FullName']))
      {
        $sdata['FullName']="FAKE:$base_str";
      }
      $sdata['signed-by']['dn']['id-at-organizationName']=$sdata['FullName'];
    }*/
    return $sdata;
  }

  static public function getSignatureFile($signature, $signed_data=false, $certificate=false, $relaxed=false)
  {
    self::$last_error='';
    $sign=base64_decode($signature);
    $name=tempnam(sys_get_temp_dir(), 'sign_');
    $f=fopen($name, 'w');
    if (!$f)
    {
      throw new Exception('Не могу сохранить подпись',405);
    }
    $s=fwrite($f, $sign);
    fclose($f);
    try
    {
      if ( $s!==strlen($sign) )
      {
        throw new Exception('Не получилось сохранить подпись',405);
      }

      $signature=self::CheckSignatureFile($name, $relaxed);
      if (false==$signature)
      {
        throw new Exception('Плохая подпись'.(''==self::$last_error?'':(': '.self::$last_error)));
      }
      if (!empty($certificate) && !self::compare($signature['signed-by'], $certificate))
      {
        throw new Exception('Не та подпись. Подпишите заявку ЭЦП, используемой в создании профиля пользователя',405);
      }

      if ( false!==$signed_data )
      {
        $s1=str_replace("\r\n", "\n", $signed_data);
        $s2=str_replace("\r\n", "\n", $signature['SignedData']);
        if(strpos($s1,'Дата и время подписания')) {
          $aStr1 = explode('Дата и время подписания', $s1);
          $s1 = $aStr1[0];
        }
        if(strpos($s2,'Дата и время подписания')) {
          $aStr2 = explode('Дата и время подписания', $s2);
          $s2 = $aStr2[0];
        }

        if ( 0!=strcmp($s1, $s2) )
        {
          logVar($s1, 'Должно быть в подписи:');
          logVar($s2, 'Фактически в подписи:');
          throw new Exception('Подписаны не те данные (Данные, которые были '.
              'заверены ЭЦП отличаются от ожидаемых. Попробуйте еще раз. '.
              'Если ошибка повторяется — обновите (переустановите) программное обеспечение СКЗИ до последней версии)',405);
        }
      }
    }
    catch (Exception $e)
    {
      if (file_exists($name))
      {
        unlink($name);
      }
      throw $e;
    }
    return $name;
  }

  public static function compare($sign1, $sign2, $deep=false)
  {
    if (!is_array($sign1))
      $sign1=unserialize($sign1);
    if (!is_array($sign2))
      $sign2=unserialize($sign2);

    $equal = false;
    if ($sign1['serial']==$sign2['serial']&&
        $sign1['valid-from']==$sign2['valid-from']&&
        $sign1['valid-for']==$sign2['valid-for']&&
        is_array($sign1['dn']) && is_array($sign2['dn']) &&
        $sign1['dn']['e-Mail']==$sign2['dn']['e-Mail']&&
        $sign1['dn']['id-at-organizationName']==$sign2['dn']['id-at-organizationName']&&
        $sign1['dn']['id-at-commonName']==$sign2['dn']['id-at-commonName'])
    {
      $equal = true;
    }
    if ($deep)
    {
      return $equal;
    }
    else
    {
      if (defined('CHECK_EDS_PROFILE')) {
        return $equal;
      }
      // Это надо убрать, сделать простое сравнение как и выше. Но потом.
      return true;
    }
  }

  static public function fillUserForm($cert, $cmp_id=null) {
    $form_data = array();
    if(!empty($cmp_id)) {
      $cmp = Model_Contragent::load($cmp_id);
      $cert['KPP'] = $cmp->getKpp();
    }
    if ($cert['INN']) {
      $form_data['inn'] = $cert['INN'];
    }
    if ($cert['KPP']) {
      $form_data['kpp'] = $cert['KPP'];
    }
    if ($cert['OGRN']) {
      $form_data['ogrn'] = $cert['OGRN'];
    }
    if ($cert['FullName']&&empty($cmp_id)) {
      $form_data['full_name'] = $cert['FullName'];
      $form_data['short_name'] = $form_data['full_name'];
    } elseif ($cmp) {
      $form_data['full_name'] = $cmp->getFullName();
      $form_data['short_name'] = $cmp->getShortName();
    }
    if ($cert['UserFIO']) {
      $fio = explode(' ',$cert['UserFIO']);
      $form_data['lastname']=$fio[0];
      $form_data['firstname']=$fio[1];
      $form_data['middlename']=$fio[2];
      $form_data['user_fio']=array(
          'lastname'=>$fio[0],
          'firstname'=>$fio[1],
          'middlename'=>$fio[2]);
    }
    if ($cert['signed-by']['dn']['e-Mail']) {
      $email = $cert['signed-by']['dn']['e-Mail'];
      $form_data['user_email'] = $email;
      $form_data['email'] = $email;
    }
    if ($cert['signed-by']['dn']['id-at-title']) {
      $form_data['user_job'] = $cert['signed-by']['dn']['id-at-title'];
    }
    if ($cert['signed-by']['dn']['id-at-stateOrProvinceName']) {
      $form_data['region_id_combo_value'] = $cert['signed-by']['dn']['id-at-stateOrProvinceName'];
    }
    if ($cert['signed-by']['dn']['id-at-localityName']) {
      $form_data['city_id_combo_value'] = $cert['signed-by']['dn']['id-at-localityName'];
    }
    if (is_array($cert['signed-by']['extensions'])) {
      $ext = $cert['signed-by']['extensions'];
      /**
       * @TODO: констант ролей сейчас в системе нет - так что кусок ваще нерабочий
       */
      $roles = array(USER_ROLE_CUST_MAIN       => OID_ADMIN,
                     USER_ROLE_MAIN            => OID_ADMIN,
                     USER_ROLE_CUST_SUBSIGNED  => OID_AUTHORIZED,
                     USER_ROLE_SUBSIGNED       => OID_AUTHORIZED,
                     USER_ROLE_CUST_CONTRACTOR => OID_CONTRACTOR,
                     USER_ROLE_CONTRACTOR      => OID_CONTRACTOR,
                    );
      $form_data['roles']=array();
      foreach ($roles as $role=>$oid) {
        if (in_array($oid, $ext)) {
          $form_data["role[r_$role]"] = $role;
          $form_data['roles'][] = $role;
        }
      }
    }
    return $form_data;
  }

  public static function sign($data, $certificate="CN=EETPTEST", $options=array()) {
    self::$last_error='';
    if (defined('SIGNING_SERVER')) {
      $h = null;
      $detached = false;
      if (isset($options['detached']) && $options['detached']) {
        $detached = true;
      }
      try {
        $h = curl_init();
        curl_setopt($h, CURLOPT_URL, SIGNING_SERVER);
        curl_setopt($h, CURLOPT_HEADER, 0);
        curl_setopt($h, CURLOPT_POST, 1);
        curl_setopt($h, CURLOPT_RETURNTRANSFER, 1);
        $sdata = array(
          'key' => $certificate,
          'detached' => $detached,
          'data' => base64_encode($data)
        );
        curl_setopt($h, CURLOPT_POSTFIELDS, array('action'=>'sign', 'data'=>json_encode($sdata)));
        $ret = curl_exec($h);

        if (false === $ret) {
          throw new Exception('Signature request error '.curl_errno($h).': '.curl_error($h));
        }
        curl_close($h);
        $h = null;
        //$ret = iconv('windows-1251', 'utf-8', $ret);
        $ret = json_decode($ret, true);
        if (NULL===$ret) {
          $ret = array (
            'success' => false,
            'message' => 'JSON parsing error'
          );
        }
        if (!isset($ret['success'])||!$ret['success']) {
          if (!isset($ret['message'])) {
            $ret['message'] = 'unknown error';
          }
          throw new Exception('Signing error: '.$ret['message']);
        }
        if (!isset($ret['result']) || !isset($ret['length'])) {
          throw new Exception('Signing error: empty result');
        }
        $len = intval($ret['length']);
        $decoded = base64_decode($ret['result']);
        if (strlen($decoded)!=$len) {
          throw new Exception("Signing error: result size mismatch ($len != ".strlen($decoded).")");
        }
        return $ret['result'];
      } catch (Exception $e) {
        self::$last_error=$e->getMessage();
        if ($h) {
          curl_close($h);
        }
        return false;
      }
    } else {
      if (defined('FAKE_CRYPTCP')) {
        return 'a';
      }
      $tmpnam = self::writeTemporaryFile($data, 'csign_');
      if (false===$tmpnam)
      {
        return false;
      }
      $signed = self::signFile($tmpnam, $certificate, $options);
      if (!$signed) {
        return false;
      }
      $signature = file_get_contents($signed);
      @unlink($tmpnam);
      @unlink($signed);
    }
    return $signature;
  }

  public static function signFile($filename, $certificate="CN=EETPTEST", $options=array()) {
    self::$last_error='';
    $detached = false;
    if (isset($options['detached']) && $options['detached']) {
      $detached = true;
    }
    if ( !isFileReadable($filename) )
    {
      self::$last_error='Файл для подписи отсутствует';
      return false;
    }
    if (defined('SIGNING_SERVER')) {
      $data = file_get_contents($filename);
      if (false===$data) {
        self::$last_error='Не могу прочитать файл для подписи';
        return false;
      }
      $signature = self::sign($data, $certificate);
      if (false===$signature) {
        return false;
      }
      $outfile = self::writeTemporaryFile($data, 'signed_');
    } else {
      if (defined('FAKE_CRYPTCP'))
      {
        return true;
      }
      if (!class_exists('Model_Process'))
      {
        require_once APPLICATION_PATH.'/models/Process.php';
      }
      $proc = new Model_Process(CRYPTCP_COMMAND);
      if ($detached) {
        $path=getcwd();
        $tmp_path = sys_get_temp_dir();
        @chdir($tmp_path);
        $outfile = $tmp_path.'/'.basename($filename).'.sgn';
      } else {
        $outfile=tempnam(sys_get_temp_dir(), 'signed_');
      }

      $argv = array(($detached?'-signf':'-sign'), '-nochain');
      if (is_array($certificate)) {
        $argv = array_merge($argv, $certificate);
      } else {
        $argv = array_merge($argv, array('-dn', $certificate));
      }
      $argv = array_merge($argv, array('-1', $filename/*, $outfile*/));
      if (!$detached) {
        $argv[] = $outfile;
      }
      $exitCode = $proc->Execute($argv, "O\r\n");
      $out=explode("\n", $proc->getOutput('windows-1251'));
      if ($detached) {
        @chdir($path);
      }

      if (0 !== $exitCode)
      {
        $code = trim(array_pop($out));
        if (''==$code)
        {
          $code = trim(array_pop($out));
        }
        //$t = trim(array_pop($out));
        $msg = trim(array_pop($out));
        self::$last_error = "$code $msg";
        @unlink($outfile);
        return false;
      }
    }
    return $outfile;
  }

  /**
   * Возвращает отпечаток ЭЦП (eds_short)
   * НомерСертификата:УЦ
   *
   * @param type $cert
   * @return type
   */
  static public function getEdsFingerprint($cert) {
    if (is_string($cert)) {
      $cert = unserialize($cert);
    }
    if (isset($cert['signed-by'])) {
      $cert = $cert['signed-by'];
    }
    if (!isset($cert['serial'])) {
      return null;
    }
    return $cert['serial'].':'.$cert['dn-signed-by']['id-at-organizationName'];
  }

  static public function getEdsName($eds) {
    if (isset($eds['serial']) && isset($eds['dn'])) {
      return "{$eds['dn']['id-at-commonName']}, {$eds['dn']['id-at-organizationName']} №{$eds['serial']} выдана «{$eds['dn-signed-by']['id-at-commonName']}»";
    }
    return "{$eds['UserFIO']} №{$eds['signed-by']['serial']} выдана «{$eds['signed-by']['dn-signed-by']['id-at-commonName']}»";
  }

  /**
   * Возвращает текстовое представление ЭЦПы из необработанных данных (голой ЭЦП)
   * @param string $eds
   * @return string
   */
  static public function getEdsNameFromRaw($eds) {
    if (empty($eds) || 'NULL'==$eds) {
      return 'Отсутствует';
    }
    $eds = self::CheckSignature($eds, 'force');
    if (!$eds) {
      return "Ошибка ЭЦП: ".self::$last_error;
    }
    return self::getEdsName($eds);
  }
}
