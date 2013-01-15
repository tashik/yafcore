<?php

class Core_Crypto_ASN1
{
  private static $allowedASN1Tags=array(
    0x00=>'Context',
    0x01=>'Bool',
    0x02=>'Integer',
    0x03=>'BitString',
    0x04=>'OctetString',
    0x05=>'Null',
    0x06=>'OID',
/*    0x07=>'ObjectDesc',
    0x09=>'Real',
    0x0A=>'Enum',*/
    0x0C=>'UTF8',
//    0x13=>'RelOID',
    0x10=>'Sequence',
    0x11=>'Set',
    0x13=>'PrintableString',
    0x16=>'IA5String',
    0x17=>'UTCTime',
    0x1e=>'BMPString',
  );
  static private function getASN1Record($data)
  {
    $type = ord($data[0]);
    /*if ( !in_array($type, self::$allowedASN1Tags) )
    {
      // Неизвестный тег
      return false;
    }*/
    $len = isset($data[1])?ord($data[1]):0;
    $bytes = 0;
    $padlen=0;
    if ($len & 0x80)
    {
      $bytes = $len & 0x7f;
      if (0==$bytes) // indefinite-form
      {
        $tdata=substr($data, $bytes+2);
        $tlen=0;
        $len=false;
        while (false!=$tdata&&''!=$tdata)
        {
          $tdata=self::getASN1Record($tdata);
          if (0==$tdata['rawtype'] && 2==$tdata['fulllen'])
          {
            $len=$tlen;
            break;
          }
          $tlen+=$tdata['fulllen'];
          $tdata=$tdata['rest'];
        }
        if (false===$len)
        {
          return false;
        }
        $padlen=2;
      }
      else
      {
        $len = 0;
        for ($i = 0; $i < $bytes; $i++)
        {
          $len = ($len << 8) | ord($data[$i + 2]);
        }
      }
    }
    if (strlen($data)<$bytes+2+$len+$padlen)
    {
      // Недостаточно данных (или битый ASN.1)
      return false;
    }
    $tagdata=substr($data, $bytes+2, $len);
    if (strlen($data)==$bytes+2+$len+$padlen)
    {
      $restdata=false;
    }
    else
    {
      $restdata=substr($data, $bytes+2+$len+$padlen);
    }
    return array('type'=>($type&0x1f),
                 'class'=>(($type&0xc0) >>6),
                 'construct'=>(($type&0x20) >>5),
                 'rawtype'=>($type&0x3f),
                 'data'=>$tagdata,
                 'fulllen'=>$bytes+2+$len+$padlen,
                 'rest'=>$restdata);
  }

  static private function _parseOID($data)
  {
    // Unpack the OID
    $oid_data=$data['data'];
    $plain  = floor(ord($oid_data[0]) / 40);
    $plain .= '.' . ord($oid_data[0]) % 40;
    $value = 0;
    $i = 1;
    while ($i < strlen($oid_data))
    {
      $value = $value << 7;
      $value = $value | (ord($oid_data[$i]) & 0x7f);
      if (!(ord($oid_data[$i]) & 0x80))
      {
        $plain .= '.' . $value;
        $value = 0;
      }
      $i++;
    }
    return $plain;
  }

  static private function _parseSequence($data)
  {
    $data=$data['data'];
    $ret=array();
    while (strlen($data)>0)
    {
      $data=self::getASN1Record($data);
      $ret[]=self::parseASN1($data);
      $data=$data['rest'];
    }
    return $ret;
  }

  static private function _parseSet($data)
  {
    $data=$data['data'];
    $ret=array();
    while (strlen($data)>0)
    {
      $data=self::getASN1Record($data);
      $ret[]=self::parseASN1($data);
      $data=$data['rest'];
    }
    return $ret;
  }

  //context-related
  static private function _parseContext($data)
  {
    if ($data['construct'])
    {
      //return self::parseASN1($data['data']);
      $data=$data['data'];
      $ret = array();
      while (strlen($data)>0)
      {
        $data=self::getASN1Record($data);
        $ret[]=self::parseASN1($data);
        $data=$data['rest'];
      }
      return $ret;
    }
    return 'END-OF-CONTENT';
  }

  static private function _parseInteger($data)
  {
    $integer_data = $data['data'];
    $value = 0;
    $value = '';
    for ($i = 0; $i < strlen($integer_data); $i++) {
      $value .= str_pad( dechex(ord($integer_data[$i])), 2, '0', STR_PAD_LEFT );
    }
    return $value;
    if (strlen($integer_data) <= 4)
    {
      /* Method works fine for small integers */
      for ($i = 0; $i < strlen($integer_data); $i++)
      {
        $value = ($value << 8) | ord($integer_data[$i]);
      }
    } else
    {
      /* Method works for arbitrary length integers */
      for ($i = 0; $i < strlen($integer_data); $i++)
      {
        $value = bcadd(bcmul($value, 256), ord($integer_data[$i]));
      }
    }
    return $value;
  }

  static private function _parseBool($data)
  {
    return (0!=$data['data']);
  }

  static private function _parseBitString($data)
  {
    if ($data['construct'])
    {
      return self::parseASN1($data['data']);
    }
    return $data['data'];
  }

  static private function _parseUTF8($data)
  {
    return $data['data'];
  }

  static private function _parseOctetString($data)
  {
    if ($data['construct'])
    {
      return self::parseASN1($data['data']);
    }
    return $data['data'];
    //return iconv('UCS-2', 'UTF-8//IGNORE', $data['data']);
  }

  static private function _parseBMPString($data)
  {
    return iconv('UTF-16BE', 'UTF-8//IGNORE', $data['data']);
  }

  static private function _parsePrintableString($data)
  {
    return $data['data'];
  }

  static private function _parseIA5String($data)
  {
    return $data['data'];
  }

  static private function _parseUTCTime($data)
  {
    if ( preg_match('@^([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})[0-9]*Z$@', $data['data'], $matches) )
    {
      $time=gmmktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[3], $matches[1]);
    }
    else
    {
      $time=1;
    }
    return gmdate('d-m-Y H:i:s e', $time);
  }

  static private function _parseNull($data)
  {
    return null;
  }

  static public function parseASN1($data)
  {
    if (is_string($data))
    {
      $data=self::getASN1Record($data);
    }
    $result=false;
    if (false===$data)
    {
      return false;
    }
    if ( isset(self::$allowedASN1Tags[$data['type']]) )
    {
      $method='_parse'.self::$allowedASN1Tags[$data['type']];
      return array(
          'length'=>strlen($data['data']),
          'rawlength'=>$data['fulllen'],
          'type'=>self::$allowedASN1Tags[$data['type']],
          'val'=>self::$method($data),
                  );
    }
    return array('length'=>strlen($data['data']),
        'rawlength'=>$data['fulllen'],
        'type'=>$data['rawtype'],
        'val'=>$data['data']);
  }

  static public function collapseData($data)
  {
    if (!is_array($data))
    {
      return $data;
    }
    if (!class_exists('Core_Crypto_OID'))
    {
      require_once APPLICATION_PATH.'/../library/Core/Crypto/OID.php';
    }
    if ('OID'==$data['type'])
    {
      return Core_Crypto_OID::getObjectName($data['val']);
    }
    if ('Sequence'==$data['type'] || 'Set'==$data['type'] || 'Context'==$data['type'])
    {
      $ret=array();
      foreach ($data['val'] as $val)
      {
        $ret[]=self::collapseData($val);
      }
      return $ret;
    }
    if ('OctetString'==$data['type'] && is_array($data['val']) && 'OctetString'==$data['val']['type'])
    {
      return $data['val']['val'];
    }
    if ('BitString'==$data['type'] && is_array($data['val']) && isset($data['val']['val'])) {
      return self::collapseData($data['val']);
    }
    /*
    if ('Context'==$data['type'])
    {
      return array('context'=>self::collapseData($data['val']));
    }*/
    return $data['val'];
/*    if ( 'Sequence'==$data['type'] && is_array($data['val']) && 2<=count($data['val']) && 'OID'==$data['val'][0]['type'] )
    {
      $name=self::getObjectName($data['val'][0]['val']);
      return array( $name=>self::collapseData($data['val'][1]) );
    }

    if ( 'Context'==$data['type'] )
    {
      return self::collapseData($data['val']);
    }
    if (!is_array($data['val']))
    {
      if ('OID'==$data['type'])
      {
        return self::getObjectName($data['val']);
      }
      return $data['val'];
    }
    else
    {
      $ret=array();
      foreach ($data['val'] as $val)
      {
        $val=self::collapseData($val);
        if ( is_array($val) && 1==count($val))
        {
          list($key,$value)=each($val);
          while (isset($ret[$key]) )
          {
            $key.='+';
          }
          $ret[$key]=$value;
        }
        else
        {
          $ret[]=$val;
        }
      }
//       if ( 1==count($ret) )
//       {
//         return $ret[0];
//       }
      return $ret;
    }*/
  }

  static public function getCertDN($data)
  {
    $dndata=array();
    foreach($data as $val)
    {
      if ( isset($dndata[$val[0][0]]) )
      {
        if (!is_array($dndata[$val[0][0]]))
        {
          $dndata[$val[0][0]]=array($dndata[$val[0][0]]);
        }
        $dndata[$val[0][0]][]=$val[0][1];
      }
      else
      {
        $dndata[$val[0][0]]=$val[0][1];
      }
    }
    if (isset($dndata['PKCS-9 unstructuredName']))
    {
      $dndata = array_merge($dndata, self::parseUnstructuredName($dndata['PKCS-9 unstructuredName']));
    }
    return $dndata;
  }

  static public function parseUnstructuredName($unstructured_name) {
    $dndata = array();
    $reglament_regexp = '@INN\s*=\s*([0-9]+)(?:\s*[;,\\\/]\s*KPP\s*=\s*([0-9]+))?(?:\s*[;,\\\/]\s*OGRN\s*=\s*([0-9]+))?@';
    if (is_array($unstructured_name)) {
      // для начала глянем — м.б. там все по регламенту, просто несколько таких полей
      foreach ($unstructured_name as $name) {
        if (preg_match($reglament_regexp, $name)) {
          $unstructured_name = $name;
          break;
        }
      }
    }
    if (is_array($unstructured_name))
    {
      if (isset($unstructured_name[0]))
      {
        $dndata['OGRN']=$unstructured_name[0];
      }
      if (isset($unstructured_name[1]))
      {
        $dndata['KPP']=$unstructured_name[1];
      }
      if (isset($unstructured_name[2]))
      {
        $dndata['INN']=$unstructured_name[2];
      }
    } elseif (is_string($unstructured_name))
    {
      if (strlen($unstructured_name)==32 && is_numeric($unstructured_name))
      {
        $unstructured_name=mb_trim($unstructured_name);
        $dndata['INN']=substr($unstructured_name, 0, 10);
        $dndata['KPP']=substr($unstructured_name, 9, 9);
        $dndata['OGRN']=substr($unstructured_name, 19, 13);
      } elseif (preg_match("@^([0-9]+)\x11([0-9]+)\x12([0-9]+)$@", $unstructured_name, $matches))
      { // посылаю луч любви и обожания тому человеку, который придумал использовать такие разделители
        $dndata['INN']=$matches[1];
        $dndata['KPP']=$matches[2];
        $dndata['OGRN']=$matches[3];
      } elseif (preg_match('@^([0-9]+)\s*[,;.\\\/\-]\s*([0-9]+)\s*[,;.\\\/\-]\s*([0-9]+)$@', $unstructured_name, $matches))
      {
        $dndata['INN']=$matches[1];
        $dndata['KPP']=$matches[2];
        $dndata['OGRN']=$matches[3];
      } elseif (preg_match($reglament_regexp, $unstructured_name, $matches))
      //} elseif (preg_match('@INN\s*=\s*([0-9]+)\s*(?:[;,\\\/]\s*KPP\s*=\s*([0-9]+))?\s*(?:[;,\\\/]\s*OGRN\s*=\s*([0-9]+))?@', $unstructured_name, $matches))
      {
        $dndata['INN']=$matches[1];
        $dndata['KPP']=isset($matches[2])?$matches[2]:null;
        $dndata['OGRN']=isset($matches[3])?$matches[3]:null;
      } elseif (preg_match('@ИНН[ =]([0-9]+)[;,\\\/ ]+КПП[ =]([0-9]+)@', $unstructured_name, $matches))
      {
        $dndata['INN']=$matches[1];
        $dndata['KPP']=$matches[2];
        $dndata['OGRN']='';
      }
    }
    return $dndata;
  }

  static public function getCertData($data)
  {
    if ('02'!=$data[0][0]) // неправильная версия сертификата
    {
      return false;
    }
    $res=array(
      'serial'=>$data[1],
      'valid-from'=>$data[4][0],
      'valid-for'=>$data[4][1],
      'dn'=>self::getCertDN($data[5]),
      'dn-signed-by'=>self::getCertDN($data[3]),
    );
    for ($i = 7; $i<count($data); $i++) {
      $ext = false;
      if (isset($data[$i]) && is_array($data[$i])) {
        // расширенные поля сертификата
        foreach ($data[$i] as $attr) {
          if ('2.5.29.37'==$attr[0]) { // Extended key usage
            $ext = self::parseASN1($attr[1]);
            if ($ext) {
              $res['extensions'] = self::collapseData($ext);
            }
            break;
          }
        }
        if($ext) {
          break;
        }
      }
    }
    return $res;
  }

  static public function parseSMIME($data)
  {
    if (is_string($data))
    {
      $data=self::parseASN1($data);
    }
    if (!is_array($data))
    {
      return false;
    }
    //return $data;
    $data=self::collapseData($data);
    if ('PKCS-7 2'!=$data[0])
    {
      return false;
    }
    $ret=array(
      'hash'=>isset($data[1][0][1][0][0])?$data[1][0][1][0][0]:null,
      'data'=>isset($data[1][0][2][1][0])?$data[1][0][2][1][0]:null,
    );
    $i=1;
    foreach($data[1][0][3] as $signature)
    {
      $cert=self::getCertData($signature[0]);
      if ($cert['dn']['id-at-organizationName'] != $cert['dn-signed-by']['id-at-organizationName']
          || $cert['dn']['e-Mail'] != $cert['dn-signed-by']['e-Mail']
         ) // нет пути самоподписанным
      {
        $ret["signed-by"]=$cert;
      }
      $ret["signed-by-$i"]=$cert;
      $i++;
    }
    if (!isset($ret['signed-by']))
    {
      $ret["signed-by"]=$ret["signed-by-1"];
    }
    return $ret;
  }

  /*static public function parsePKCS12($data)
  {
    if (is_string($data))
    {
      $data=self::parseASN1($data);
    }
    if (!is_array($data))
    {
      return false;
    }
    $data=self::collapseData($data);
    $data=$data[1][1]['context'];
    $data=self::parseASN1($data);
    $data=self::collapseData($data);
    return $data;
  }*/
}