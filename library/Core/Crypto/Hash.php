<?php
require_once 'Crypto_Bootstrap.php';

// Класс поддержки низкоуровнего слоя криптографии
class Core_Crypto_Hash
{
  private static $minCSPSize =128;
  private static $S = array(
      array(0xA,0x4,0x5,0x6,0x8,0x1,0x3,0x7,0xD,0xC,0xE,0x0,0x9,0x2,0xB,0xF),
      array(0x5,0xF,0x4,0x0,0x2,0xD,0xB,0x9,0x1,0x7,0x6,0x3,0xC,0xE,0xA,0x8),
      array(0x7,0xF,0xC,0xE,0x9,0x4,0x1,0x0,0x3,0xB,0x5,0x2,0x6,0xA,0x8,0xD),
      array(0x4,0xA,0x7,0xC,0x0,0xF,0x2,0x8,0xE,0x1,0x6,0x5,0xD,0xB,0x9,0x3),
      array(0x7,0x6,0x4,0xB,0x9,0xC,0x2,0xA,0x1,0x8,0x0,0xE,0xF,0xD,0x3,0x5),
      array(0x7,0x6,0x2,0x4,0xD,0x9,0xF,0x0,0xA,0x1,0x5,0xB,0x8,0xE,0xC,0x3),
      array(0xD,0xE,0x4,0x1,0x7,0x0,0x5,0xA,0x3,0xC,0x8,0xF,0x6,0x2,0x9,0xB),
      array(0x1,0x3,0xA,0x9,0x5,0xB,0x4,0xF,0x8,0x6,0x7,0xE,0xD,0x0,0x2,0xC));

  // Возвращает хеш файла
  static function File($filename, $throw=false)
  {
    if (@filesize($filename) <= self::$minCSPSize) {
      $contents = @file_get_contents($filename);
      if (false===$contents) {
        if ($throw)
        {
          throw new Exception('Внутренняя ошибка хеширования: не прочитать файл');
        }
        return false;
      }
      return self::gostHash($contents);
    }
    if (defined('FAKE_CRYPTCP'))
    {
      $t=@hash_file('gost', $filename);
      if ($throw && !$t)
      {
        throw new Exception('Ошибка хеширования: не подсчитать хеш');
      }
      return $t;
    }
    //echo "Hashing $filename (".HASH_COMMAND.")\n";
    $path=getcwd();
    @chdir(dirname($filename));
    $t=@exec(HASH_COMMAND.' '.escapeshellarg($filename).' 2>/dev/null');
    chdir($path);
    if ( preg_match(HASH_VALUE, trim($t), $matches) )
    {
      $t=file_get_contents($filename.'.hsh');
      unlink($filename.'.hsh');
      if ( strlen($t)!=32 )
      {
        if ($throw)
        {
          throw new Exception('Внутренняя ошибка хеширования: неправильный хеш');
        }
        return false;
      }
      return (bin2hex($t));
      //return trim($matches[1]);
    }
    if ($throw)
    {
      throw new Exception('Ошибка хеширования: не создать хеш');
    }
    return false;
  }

  // Возвращает хеш строки
  static function String($str, $throw=false)
  {
    if (defined('FAKE_CRYPTCP'))
    {
      // INFO: если вам кажется что PHP считает ГОСТовые хеши неправильно — вам кажется верно!
      // PHP использует иную таблицу хеширования, нежели cryptcp, и поэтому результаты получаются разные
      // Однако реализация в PHP соответствует примеру из ГОСТа, а криптопрошная нет.
      // Это потому что в PHP используется тестовая таблица перестановок, поэтому юзаем собственную реализацию
      // для малых строк (т.к. она тормозная ппц), но хоть совместимость паролей будет
      if (strlen($str)<=self::$minCSPSize) {
        $res = self::gostHash($str);
      } else {
        $res = hash('gost', $str);
      }
      #logVar($res, __METHOD__ . 'fakehash: ');
      return $res;
    }
    $tmpnam=tempnam(sys_get_temp_dir(), 'hash_');
    $f=@fopen($tmpnam, 'w');
    if (false===$f)
    {
      if ($throw)
      {
        throw new Exception('Ошибка хеширования: не создать файл');
      }
      return false;
    }
    $written=fwrite($f, $str);
    fclose($f);
    if (false===$written || strlen($str)!=$written)
    {
      unlink($tmpnam);
      if ($throw)
      {
        throw new Exception('Ошибка хеширования: не сохранить данные в файл');
      }
      return false;
    }
    try
    {
      $t=self::File($tmpnam, $throw);
    }
    catch (Exception $e)
    {
      unlink($tmpnam);
      throw $e;
    }
    unlink($tmpnam);
    return $t;
  }

  private static function E_f($A, $K, &$R, $o) { // Функция f в ГОСТ 28147-89
    $c = 0; //Складываем по модулю 2^32. c - перенос  в следующий разряд
    for ($i = 0; $i < 4; $i++) {
      $c += $A[$i] + $K[$o+$i];
      $R[$i] = $c & 0xFF;
      $c >>= 8;
    }

    for ($i = 0; $i < 8; $i++) {                  // Заменяем 4х-битные кусочки согласно S-блокам
      $x = $R[$i >> 1] & (($i & 1) ? 0xF0 : 0x0F);   // x - 4х-битный кусочек
      $R[$i >> 1] ^= $x;                                // Обнуляем соответствующие биты
      $x >>= ($i & 1) ? 4 : 0;                         // сдвигаем x либо на 0, либо на 4 бита влево
      $x = self::$S[$i][$x];                                   // Заменяем согласно S-блоку
      $R[$i >> 1] |= $x << (($i & 1) ? 4 : 0);           //
    }

    $tmp = $R[3]; // Сдвигаем на 8 бит (1 байт) влево
    $R[3] = $R[2];
    $R[2] = $R[1];
    $R[1] = $R[0];
    $R[0] = $tmp;

    $tmp = $R[0] >> 5; // Сдвигаем еще на 3 бита влево
    for ($i = 1; $i < 4; $i++) {
      $nTmp = $R[$i] >> 5;
      $R[$i] = 0xFF&(($R[$i] << 3) | $tmp);
      $tmp = $nTmp;
    }
    $R[0] = 0xFF&(($R[0] << 3) | $tmp);
  }

  private static function E($D, $K, &$R, $o) { // ГОСТ 28147-89
    $A = array();
    $B = array();
    for ($i = 0; $i < 4; $i++) $A[$i] = $D[$o+$i];
    for ($i = 0; $i < 4; $i++) $B[$i] = $D[$o+$i + 4];

    for ($step = 0; $step < 3; $step++)         // K1..K24 идут в прямом порядке - три цикла K1..K8
      for ($i = 0; $i < 32; $i += 4) {
        $tmp = array();
        self::E_f($A, $K, $tmp, $i);              // (K + i) - массив K с i-го элемента
        for ($j = 0; $j < 4; $j++) $tmp[$j] ^= $B[$j];
        $B = $A;
        $A = $tmp;
      }
    for ($i = 28; $i >= 0; $i -= 4) { // А K25..K32 идут в обратном порядке
      $tmp = array();
      self::E_f($A, $K, $tmp, $i);
      for ($j = 0; $j < 4; $j++) $tmp[$j] ^= $B[$j];
      $B = $A;
      $A = $tmp;
    }
    for ($i = 0; $i < 4; $i++) $R[$o+$i] = $B[$i];      //Возвращаем результат
    for ($i = 0; $i < 4; $i++) $R[$o+$i + 4] = $A[$i];
  }
  // GOST R 34.11-94
  private static function A($Y, &$R) {
    for ($i = 0; $i < 24; $i++) $R[$i] = $Y[$i + 8];
    for ($i = 0; $i < 8; $i++) $R[$i + 24] = $Y[$i] ^ $Y[$i + 8];
  }
  private static function fi($arg) { // Функция фи. Отличие от функции в статье - нумерация не 1..32, а 0..31
    $i = $arg & 0x03;
    $k = $arg >> 2; $k++;
    return ($i << 3) + $k - 1;
  }
  private static function P($Y, &$R) {
    for ($i = 0; $i < 32; $i++)
      $R[$i] = $Y[self::fi($i)];
  }

  private static function psi(&$arr, $p) {
    while ($p--) {
      $y16 = array(0, 0);
      $y16[0] ^= $arr[ 0]; $y16[1] ^= $arr[ 1];
      $y16[0] ^= $arr[ 2]; $y16[1] ^= $arr[ 3];
      $y16[0] ^= $arr[ 4]; $y16[1] ^= $arr[ 5];
      $y16[0] ^= $arr[ 6]; $y16[1] ^= $arr[ 7];
      $y16[0] ^= $arr[24]; $y16[1] ^= $arr[25];
      $y16[0] ^= $arr[30]; $y16[1] ^= $arr[31];
      for ($i = 0; $i < 30; $i++) $arr[$i] = $arr[$i + 2];
      $arr[30] = $y16[0]; $arr[31] = $y16[1];
    }
  }

  private static function f($H, $M) { // Функция f
    $C = array();
    $C[0] = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    $C[1] = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    $C[2] = array(0x00, 0xFF, 0x00, 0xFF, 0x00, 0xFF, 0x00, 0xFF, 0xFF, 0x00, 0xFF, 0x00, 0xFF, 0x00, 0xFF, 0x00,
            0x00, 0xFF, 0xFF, 0x00, 0xFF, 0x00, 0x00, 0xFF, 0xFF, 0x00, 0x00, 0x00, 0xFF, 0xFF, 0x00, 0xFF);
    $C[3] = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);

    $U = array();
    $V = array();
    $W = array();
    $K = array();
    $tmp = array();

    $U = $H;
    $V = $M;
    for ($i = 0; $i < 32; $i++)
      $W[$i] = $U[$i] ^ $V[$i];
    $K[0] = array();
    $K[1] = array();
    $K[2] = array();
    $K[3] = array();
    self::P($W, $K[0]);

    for ($step = 1; $step < 4; $step++) {
      self::A($U, $tmp);
      for ($i = 0; $i < 32; $i++) $U[$i] = $tmp[$i] ^ $C[$step][$i];
      self::A($V, $tmp); self::A($tmp, $V);
      for ($i = 0; $i < 32; $i++) $W[$i] = $U[$i] ^ $V[$i];
      self::P($W, $K[$step]);
    }

    $S = array();
    for ($i = 0; $i < 32; $i += 8)
      self::E($H, $K[$i >> 3], $S, $i);

    self::psi($S, 12);
    for ($i = 0; $i < 32; $i++) $S[$i] ^= $M[$i];
    self::psi($S, 1 );
    for ($i = 0; $i < 32; $i++) $S[$i] ^= $H[$i];
    self::psi($S, 61);
    return $S;
  }

  public static function bin2hex($s) {
    $str = '';
    $digits = array('0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f');
    foreach ($s as $n) {
      $str .= $digits[$n/16] . $digits[$n%16];
    }
    return $str;
  }

  public static function str2bin($s) {
    $arr = array();
    for($i = 0; $i<strlen($s); $i++) {
      $arr[] = ord($s[$i]);
    }
    return $arr;
  }

  public static function gostHash($s) {
    $pos = 0;
    $posIB = 0;
    $blklen = 32;
    $buf = self::str2bin($s);
    $len = strlen($s);

    $Sum = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    $H = array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
    $block = array();
    $newH = array();
    $L = array();

    while (($posIB < $len) || $pos) {
      if ($posIB < $len)
        $block[$pos++] = $buf[$posIB++];
      else
        $block[$pos++] = 0;
      if ($pos == 32) {
        $pos = 0;

        $c = 0;
        for ($i = 0; $i < 32; $i++) {
          $c += $block[$i] + $Sum[$i];
          $Sum[$i] = $c & 0xFF;
          $c >>= 8;
        }

        $H = self::f($H, $block);
      }
    }

    $c = $len << 3;
    for ($i = 0; $i < 32; $i++) {
      $L[$i] = $c & 0xFF;
      $c >>= 8;
    }
    $H = self::f($H, $L);
    return self::bin2hex(self::f($H, $Sum));
  }

  public static function password($passwd) {
    $algo = getConfigValue('crypto->pwhash', 'gost');
    if ('gost'==$algo) {
      return self::String($passwd);
    } else {
      return hash($algo, $passwd);
    }
  }

  public static function hex2raw($hex) {
    return pack ('H*', $hex);
  }

  public static function hmac_gost($data, $key) {
    // Adjust key to exactly 64 bytes
    if (strlen($key) > 64) {
      $key = str_pad(self::hex2raw(self::String($key)), 64, chr(0));
    }
    if (strlen($key) < 64) {
      $key = str_pad($key, 64, chr(0));
    }

    // Outter and Inner pad
    $opad = str_repeat(chr(0x5C), 64);
    $ipad = str_repeat(chr(0x36), 64);

    $opad = $opad ^ $key;
    $ipad = $ipad ^ $key;

    return self::String($opad . self::hex2raw(self::String($ipad . $data)));
  }

  /**
   * Аналог PHP’шной crypt, но для соленого ГОСТового хеша (см. Core_Crypto_Hash::crypt)
   * Базируется на HMAC алгоритме, см. Core_Crypto_Hash::hmac_gost
   * @param string $password
   * @param string $salt
   * @return string
   */
  static public function gost_crypt($password, $salt) {
    try {
      $salt = self::_getSalt($salt);
      if (!$salt) {
        return false;
      }
      $hash = self::hmac_gost($password, $salt);
      if (!$hash) {
        return false;
      }
      $hash = str_replace(array('+', '='), array('.', ''), HexToBase64($hash));
      return '$g$'.$salt.'$'.$hash;
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
   * @param string $algorithm - The hash algorithm to use. Recommended: SHA256
   * @param string $password - The password.
   * @param string $salt - A salt that is unique to the password.
   * @param int $count - Iteration count. Higher is better, but slower. Recommended: At least 1024.
   * @param int $key_length - The length of the derived key in BYTES. Defaults to algorithm hash length
   * @param bool $raw_output - When set to TRUE, outputs raw binary data. FALSE outputs lowercase hexits.
   * @return string A $key_length-byte key derived from the password and salt (in binary).
   *
   * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
   */
  public static function pbkdf2($algorithm, $password, $salt, $count=1024, $key_length=false, $raw_output = false)
  {
      $algorithm = strtolower($algorithm);
      if(!in_array($algorithm, hash_algos(), true))
          throw new Exception('PBKDF2 ERROR: Invalid hash algorithm.');
      if($count <= 0)
          throw new Exception('PBKDF2 ERROR: Invalid parameters.');

      // number of blocks = ceil(key length / hash length)
      $hash_length = strlen(hash($algorithm, "", true));
      if ($key_length === false) {
        $key_length = $hash_length;
      }

      $block_count = $key_length / $hash_length;
      if($key_length % $hash_length != 0)
          $block_count += 1;

      $output = "";
      for($i = 1; $i <= $block_count; $i++)
      {
          $output .= self::pbkdf2_f($password, $salt, $count, $i, $algorithm, $hash_length);
      }

      $output = substr($output, 0, $key_length);
      if (!$raw_output) {
        $output = bin2hex($output);
      }
      return $output;
  }

  /**
   * The pseudorandom function used by PBKDF2.
   * Definition: https://www.ietf.org/rfc/rfc2898.txt
   */
  private static function pbkdf2_f($password, $salt, $count, $i, $algorithm, $hash_length)
  {
      //$i encoded as 4 bytes, big endian.
      $last = $salt . chr(($i >> 24) % 256) . chr(($i >> 16) % 256) . chr(($i >> 8) % 256) . chr($i % 256);
      $xorsum = "";
      for($r = 0; $r < $count; $r++)
      {
          $u = hash_hmac($algorithm, $last, $password, true);
          $last = $u;
          if(empty($xorsum))
              $xorsum = $u;
          else
          {
              for($c = 0; $c < $hash_length; $c++)
              {
                  $xorsum[$c] = chr(ord(substr($xorsum, $c, 1)) ^ ord(substr($u, $c, 1)));
              }
          }
      }
      return $xorsum;
  }

  static private function _getSalt($salt) {
    if (preg_match('@^\$[a-z]{1,2}\$([a-zA-Z0-9/.]+)\$[a-zA-Z0-9/.]*$@', $salt, $matches)) {
      return $matches[1];
    }
    return false;
  }

  /**
   * Аналог PHP’шной crypt, но для соленого ГОСТового хеша (см. Core_Crypto_Hash::crypt)
   * Базируется на алгоритме gost($salt+gost($password)), поддерживает в качестве пароля
   * гост-хеш пароля (т.е. с возможностью «досолки» уже готового хеша)
   * @param string $password пароль или его хеш.
   * @param string $salt
   * @param bool $is_half_hash пароль — уже наполовину готовый хеш (т.е. это gost($password))
   * @return string
   */
  static public function gost_crypt_simple($password, $salt, $is_half_hash=false) {
    try {
      $salt = self::_getSalt($salt);
      if (!$salt) {
        return false;
      }
      if (true==$is_half_hash) {
        $hash = $password;
      } else {
        $hash = self::String($password, true);
      }
      $hash = self::String($salt . $hash, true);
      $hash = str_replace(array('+', '='), array('.', ''), HexToBase64($hash));
      return '$gs$'.$salt.'$'.$hash;
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * Враппер для PHP’шной crypt(), но с поддержкой дополнительных вариантов соли:
   *   $g$соль$хеш — ГОСТовый соленый хеш. Соль произвольного размера, из латинских букв,
   *   цифр и символов «.» и «/».
   * @param string $password пароль
   * @param string $salt соль, см. crypt()
   * @return string
   */
  public static function crypt($password, $salt) {
    if ('$g$'==substr($salt, 0, 3)) {
      // ГОСТовый хеш с солью
      return self::gost_crypt($password, $salt);
    } elseif ('$gs$'==substr($salt, 0, 4)) {
      // ГОСТовый хеш с солью
      return self::gost_crypt_simple($password, $salt, $is_half_hash);
    }
    return crypt($password, $salt);
  }
}
