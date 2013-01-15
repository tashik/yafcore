<?php

function ShowVar($varname, $varvalue, $level=0, $terminator="\n")
{
  $prefix="";
  for ($i=0;$i<$level;$i++)
  {
    $prefix.="  ";
  }
  if ( is_array($varvalue) )
  {
    $t=$prefix.$varname."[".count($varvalue)."]:".$terminator;
    foreach($varvalue as $name=>$value)
    {
      $t.=ShowVar($name,$value,$level+1,$terminator);
    }
    return $t;
  }
  elseif (is_object($varvalue))
  {
    $t=$prefix.$varname." (instance of ".get_class($varvalue)."):".$terminator;
    $varvalue=get_object_vars($varvalue);
    foreach($varvalue as $name=>$value)
    {
      $t.=ShowVar($name,$value,$level+1,$terminator);
    }
    return $t;
  }
  elseif (is_bool($varvalue))
  {
    return ($prefix.$varname." (bool) = ".($varvalue?'TRUE':'FALSE').$terminator);
  }
  elseif (null===$varvalue)
  {
    return ($prefix.$varname." = NULL".$terminator);
  }
  elseif (is_int($varvalue))
  {
    return ($prefix.$varname." (int) = ".$varvalue.$terminator);
  }
  else
  {
    return ($prefix.$varname." (".gettype($varvalue).") = '".to_string($varvalue)."'".$terminator);
  }
}

function MyStrToLower($str)
{
  return mb_strtolower($str, 'UTF-8');
}

function MyStrToUpper($str)
{
  return mb_strtoupper($str, 'UTF-8');
}

function MyStrLen($str)
{
  return mb_strlen($str, 'UTF-8');
}

function MySubStr($str, $from=0, $count=0)
{
  if ($from<0)
  {
    $from=MyStrLen($str)+$from;
  }
  if ($count<0)
  {
    $str=mb_substr($str, 0, MyStrLen($str)+$count, 'UTF-8');
    $count=0;
  }
  if (0==$count)
  {
    $count=MyStrLen($str)-$from;
  }
  return mb_substr($str, $from, $count, 'UTF-8');
}

function to_string($var)
{
  if (is_bool($var))
  {
    $var=$var?1:0;
  } elseif (null===$var)
  {
    $var='NULL';
  } elseif (is_float($var))
  {
    $var=str_replace(',', '.', "$var");
  }
  return "$var";
}

function mb_trim($string, $charlist='\\\\s\\\\pZ\\\\pM\\\\pC', $ltrim=true, $rtrim=true)
{
  if (empty($string)) {
    return '';
  }
  $both_ends = $ltrim && $rtrim;

  $char_class_inner = preg_replace(
      array( '/[\^\-\]\\\]/S', '/\\\{4}/S' ),
      array( '\\\\\\0', '\\' ),
      $charlist
  );

  $work_horse = '[' . $char_class_inner . ']+';
  $ltrim && $left_pattern = '^' . $work_horse;
  $rtrim && $right_pattern = $work_horse . '$';

  if($both_ends)
  {
      $pattern_middle = $left_pattern . '|' . $right_pattern;
  }
  elseif($ltrim)
  {
      $pattern_middle = $left_pattern;
  }
  else
  {
      $pattern_middle = $right_pattern;
  }

  return preg_replace("/$pattern_middle/usSD", '', $string);
}

function is_utf8($str) {
    $c=0; $b=0;
    $bits=0;
    $len=strlen($str);
    for($i=0; $i<$len; $i++){
        $c=ord($str[$i]);
        if($c > 128){
            if(($c >= 254)) return false;
            elseif($c >= 252) $bits=6;
            elseif($c >= 248) $bits=5;
            elseif($c >= 240) $bits=4;
            elseif($c >= 224) $bits=3;
            elseif($c >= 192) $bits=2;
            else return false;
            if(($i+$bits) > $len) return false;
            while($bits > 1){
                $i++;
                $b=ord($str[$i]);
                if($b < 128 || $b > 191) return false;
                $bits--;
            }
        }
    }
    return true;
}

function sanitizeUTF8($str) {
  $str = mb_convert_encoding($str, 'utf-8', 'utf-8');
}

/**
 * Склоняем слово относящееся к числительному в зависимости от этого числа
 * @param int $n «сколько» объектов
 * @param string $one единственное число объектов («объект»)
 * @param string $two когда два объекта («объекта»)
 * @param string $more когда объектов больше двух («объектов»)
 * @return string одна из строк $one, $two, $more в зависимости от числа $n
 */
function declensionRus($n, $one, $two, $more)
{
  $n=$n%100;
  if (10<$n && 20>$n)
  {
    return $more;
  }
  $n=$n%10;
  if (1==$n)
  {
    return $one;
  }
  if (2<=$n && 4>=$n)
  {
    return $two;
  }
  return $more;
}

function collapseName($fullname)
{
  $name=explode(' ', mb_trim($fullname));
  $ret=mb_trim($name[0]).' ';
  $k = 1;
  for($i=1; $i<count($name) && $k<3; $i++)
  {
    $n = mb_trim($name[$i]);
    if (''==$n) {
      continue;
    }
    $ret.=MySubStr($n, 0, 1).'.';
    $k++;
  }
  return mb_trim($ret);
}

function limitString($str, $count=30)
{
  $len=MyStrLen($str);
  if ($len>$count)
  {
    $str=MySubStr($str, 0, $count-1).'…';
  }
  return $str;
}

function toTimestamp($stamp) {
  if ($stamp instanceof Zend_Date) {
    $stamp = $stamp->getTimestamp();
  } elseif ('now'===$stamp) {
    $stamp = time();
  } elseif (class_exists('MongoDate') && $stamp instanceof MongoDate) {
    $stamp = $stamp->sec+($stamp->usec/1000);
  } elseif (!empty($stamp) && !is_numeric($stamp)) {
    $stamp = db_date_to_timestamp($stamp);
  }
  return $stamp;
}

/**
 * Форматирует время для чтения человеками (=> НЕЛЬЗЯ использовать для БД)
 * @param int|string|Zend_Data $stamp время (можно таймштампом, можно строкой из БД, можно Zend_Date, можно строкой 'now')
 * @param int $tz Смещение часового пояса по которому показать время
 * @param bool $long_tz показывать ли часовой пояс длинным текстом (перечисляя города)
 * @return string отформатированная строка времени
 */
function formatTimestamp($stamp='now', $tz=false, $long_tz=false)
{
  $stamp = toTimestamp($stamp);
  if (empty($stamp))
    return 'нет';
  $tzoffset = $tz;
  $tz = getTimezoneNameByOffset($tz);
  if ($tz) {
    $script_tz = date_default_timezone_get();
    $tz = date_default_timezone_set($tz);
  }
  $time = strftime('%d-%m-%Y %H:%M:%S', $stamp);
  $offset = strftime('%z', $stamp);
  if ( !preg_match('@^[+-]\d{4}$@', $offset)) {
    $offset = strftime('%Z', $stamp);
  }

  if ( preg_match('@^([+-])(\d{2})(\d{2})$@', $offset, $matches) && 0==intval($matches[3])) {
    $offset = "GMT {$matches[1]}".(intval($matches[2]));
  }
  $time .= " [$offset";
  if ($long_tz) {
    $time .= ' '.getTimezoneLongNameByOffset($tzoffset);
  }
  $time .= ']';

  if ($tz) {
    date_default_timezone_set($script_tz);
  }
  return $time;
}

/**
 * Конвертирует произвольный тип в строку даты-времени формата ISO. Пытается
 * сохранить часовой пояс, если он был указан в исходном времени
 * @param string|int $time время
 * @return string
 */
function toIsoDate($time) {
  /*$time = toTimestamp($time);
  if (empty($time)) {
    return null;
  }
  return date('c', $time);*/
  if (empty($time)) {
    return null;
  }
  if ('now'===$time) {
    $time = time();
  }
  if (is_int($time) || is_float($time) || is_numeric($time)) {
    return date('c', $time);
  }
  if (class_exists('MongoDate') && $time instanceof MongoDate) {
    return date('c', toTimestamp($time));
  }
  if ( !($time instanceof Zend_Date) ) {
    $value = mb_trim($time);
    $pattern = null;
    $tz_hour = null;
    $pattern_map = array(
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+[+-]\d+$@'=>'yyyy-MM-dd HH:mm:ss.SZ',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d+$@'=>'yyyy-MM-dd HH:mm:ssZ',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$@'=>'yyyy-MM-dd HH:mm:ss.S',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@'=>'yyyy-MM-dd HH:mm:ss',
      '@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}Z$@'=>'yyyy-MM-dd HH:mm:ssZ0',
      '@^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$@'=>'yyyy-MM-ddTHH:mm:ssZZZZ',
      '@^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+[+-]\d{2}:\d{2}$@'=>'yyyy-MM-ddTHH:mm:ss.SZZZZ',
    );
    foreach ($pattern_map as $preg=>$p) {
      if (preg_match($preg, $value)) {
        $pattern = $p;
        break;
      }
    }
    if ($pattern && preg_match('@[^Z]Z$@', $pattern)) {
      $value .= '00';
    }
    if ($pattern && preg_match('@Z0$@', $pattern)) {
      $value .= '+0000';
      $pattern = substr($pattern, 0, -1);
    }
    if ($pattern && preg_match('@Z$@', $pattern)) {
      if (preg_match('@([+-]\d{2}):?\d{2}$@', $value, $matches)) {
        $tz_hour = intval($matches[1]);
        if ($tz_hour>12 || $tz_hour<-12) {
          $value = preg_replace('@\d{2}(:?\d{2})$@', '12$1', $value);
        }
      }
    }
    $value = new Zend_Date($value, $pattern);
    if ($tz_hour) {
      if ($tz_hour>12) {
        $value->subTimestamp(ONE_HOUR*($tz_hour-12));
      } elseif ($tz_hour<-12) {
        $value->subTimestamp(ONE_HOUR*($tz_hour+12));
      }
    }
  }
  return $value->getIso();
}

function formatLine($str, $len=60)
{
  $ret='';
  while (strlen($str)>$len)
  {
    $ret.=substr($str, 0 , $len)."\n";
    $str=substr($str, $len);
  }
  return $ret.$str;
}

function filterPrice($pString)
{
  if (is_float($pString))
    return $pString;
  if (is_int($pString))
    return (float)$pString;
  if (is_array($pString))
    return 0.0;

  $pString = str_replace(" ", "", $pString);
  $pString = str_replace("'", "", $pString);
  $LocaleInfo = localeconv();
  $pString = str_replace($LocaleInfo["mon_thousands_sep"] , "", $pString);
  if ( substr_count($pString, ",") > 0 && substr_count($pString, ".") > 0 )
  {
    $pString = str_replace(',', ".", $pString);
    $pos=strrpos($pString, '.');
    if (false!==$pos)
    {
      $pString=str_replace('.', '', substr($pString, 0, $pos)).substr($pString, $pos);
    }
  }
  if ( substr_count($pString, ",") ==1 )
  {
    $pString = str_replace(',', ".", $pString);
  }

  if (!preg_match('@^([+-])?[0-9]+(\.[0-9]+)?([eE][+-][0-9]+)?$@', $pString))
  {
    return false;
  }
  return (float)$pString;

  /*if (strlen($ptString) == 0) {
     return false;
  }

  $pString = str_replace(" ", "", $ptString);
  $pString = str_replace("'", "", $pString);

  if (substr_count($pString, ",") > 1)
      $pString = str_replace(",", ".", $pString);

  if (substr_count($pString, ".") > 1)
      $pString = str_replace(".", "", $pString);

  $pregResult = array();

  $commaset = strpos($pString,',');
  if ($commaset === false) {$commaset = -1;}

  $pointset = strpos($pString,'.');
  if ($pointset === false) {$pointset = -1;}

  $pregResultA = array();
  $pregResultB = array();

  if ($pointset < $commaset) {
      preg_match('#(([-]?[0-9]+(\.[0-9])?)+(,[0-9]+)?)#', $pString, $pregResultA);
  }
  preg_match('#(([-]?[0-9]+(,[0-9])?)+(\.[0-9]+)?)#', $pString, $pregResultB);
  if ((isset($pregResultA[0]) && (!isset($pregResultB[0])
          || strstr($preResultA[0],$pregResultB[0]) == 0
          || !$pointset))) {
      $numberString = $pregResultA[0];
      $numberString = str_replace('.','',$numberString);
      $numberString = str_replace(',','.',$numberString);
  }
  elseif (isset($pregResultB[0]) && (!isset($pregResultA[0])
          || strstr($pregResultB[0],$preResultA[0]) == 0
          || !$commaset)) {
      $numberString = $pregResultB[0];
      $numberString = str_replace(',','',$numberString);
  }
  else {
      return false;
  }
  $result = (float)$numberString;
  return $result;*/
}

function HumanizeSize( $bytes )
{
  $types = array( 'б', 'кб', 'Мб', 'Гб', 'Тб' );
  for( $i = 0; $bytes >= 1024 && $i < ( count( $types ) -1 ); $bytes /= 1024, $i++ );
  return ( round( $bytes, 2 ) . " " . $types[$i] );
}

function HumanizePrice($price, $always_frac=false) {
  $price = filterPrice($price);
  if ($price < 0.001 && $price> -0.001) {
    $price=0;
  }
  return ($price-floor($price)>0) ? number_format($price, 2, ',', ' ') : number_format($price, $always_frac?2:0, ',', ' ');
}

/**
 *
 * @param int $seconds — длина интервала в секундах
 * @param int $ts_since — начало интервала (таймштамп)
 * @param int $interval_orig_start — оригинальное начало интервала (таймштамп) (для этого времени не учитываются выходные)
 * @param int $interval_orig_length — оригинальная длина интервала (в секундах) (для этого времени не учитываются выходные)
 * @return string
 */
function HumanizeInterval($seconds, $ts_since=null, $interval_orig_start=null, $interval_orig_length=null)
{
  if (null!==$ts_since) {
    $begin_ts = $ts_since; // не учитываем праздники вообще
    $skip_ts = null;
    if (null!==$interval_orig_start) { // не учитываем только последний день праздников
      $skip_ts = alignTimestamp($interval_orig_start+$interval_orig_length, 'zeroday');
    }
    $end_ts = $ts_since+$seconds;
    $holidays_start = true;
    if ($skip_ts && $ts_since<$skip_ts) {
      $holidays_start = false;
    }
    for ($i=$begin_ts; $i<$end_ts; $i+=ONE_DAY) {
      if ($skip_ts && $i<$skip_ts) {
        continue;
      }
      if (!isWorkDay($i)) {
        if (!$holidays_start) {
          $seconds-=ONE_DAY;
        } else {
          $s = explode(':', date('H:i:s', $i));
          $h = ltrim($s[0], '0');
          $m = ltrim($s[1], '0');
          $s = ltrim($s[2], '0');
          $seconds = $seconds + $h*ONE_HOUR+$m*ONE_MINUTE+$s-ONE_DAY;
        }
      }
      $holidays_start = false;
    }
  }
  $str='';
  $days=(int)($seconds/ONE_DAY);
  $seconds%=ONE_DAY;
  if (0<$days)
  {
    $str.=$days.' '.declensionRus($days, 'день', 'дня', 'дней').' ';
  }
  $hours=(int)($seconds/3600);
  $seconds%=3600;
  if (0<$hours || 0==$days)
  {
    $str.=$hours.' '.declensionRus($hours, 'час', 'часа', 'часов').' ';
  }
  $mins=(int)($seconds/60);
  $seconds%=60;
  if (0<$mins && 0==$days)
  {
    $str.=$mins.' '.declensionRus($mins, 'минуту', 'минуты', 'минут').' ';
  }
  if (0<$seconds && 0==$days && 0==$hours)
  {
    $str.=$seconds.' '.declensionRus($seconds, 'секунду', 'секунды', 'секунд').' ';
  }
  return trim($str);
}

function json_encode_ext($data)
{
  $t=json_encode($data);
  while (preg_match('@"<%(.+)%>"@U', $t, $matches))
  {
    $r=json_decode('"'.$matches[1].'"');
    $t=preg_replace('@"<%(.+)%>"@U', $r, $t, 1);
  }
  return $t;
}

// Преобразования числа в числительное прописью (для документов: актов, счетов - где есть сумма прописью)
$_1_2[1]="одна ";
$_1_2[2]="две ";

$_1_19[1]="один ";
$_1_19[2]="два ";
$_1_19[3]="три ";
$_1_19[4]="четыре ";
$_1_19[5]="пять ";
$_1_19[6]="шесть ";
$_1_19[7]="семь ";
$_1_19[8]="восемь ";
$_1_19[9]="девять ";
$_1_19[10]="десять ";

$_1_19[11]="одиннацать ";
$_1_19[12]="двенадцать ";
$_1_19[13]="тринадцать ";
$_1_19[14]="четырнадцать ";
$_1_19[15]="пятнадцать ";
$_1_19[16]="шестнадцать ";
$_1_19[17]="семнадцать ";
$_1_19[18]="восемнадцать ";
$_1_19[19]="девятнадцать ";

$des[2]="двадцать ";
$des[3]="тридцать ";
$des[4]="сорок ";
$des[5]="пятьдесят ";
$des[6]="шестьдесят ";
$des[7]="семьдесят ";
$des[8]="восемьдесят ";
$des[9]="девяносто ";

$hang[1]="сто ";
$hang[2]="двести ";
$hang[3]="триста ";
$hang[4]="четыреста ";
$hang[5]="пятьсот ";
$hang[6]="шестьсот ";
$hang[7]="семьсот ";
$hang[8]="восемьсот ";
$hang[9]="девятьсот ";

$namerub[1]="рубль ";
$namerub[2]="рубля ";
$namerub[3]="рублей ";

$nametho[1]="тысяча ";
$nametho[2]="тысячи ";
$nametho[3]="тысяч ";

$namemil[1]="миллион ";
$namemil[2]="миллиона ";
$namemil[3]="миллионов ";

$namemrd[1]="миллиард ";
$namemrd[2]="миллиарда ";
$namemrd[3]="миллиардов ";

$kopeek[1]="копейка ";
$kopeek[2]="копейки ";
$kopeek[3]="копеек ";


function semantic($i,&$words,&$fem,$f){
    global $_1_2, $_1_19, $des, $hang, $namerub, $nametho, $namemil, $namemrd;
    $words="";
    $fl=0;
    if($i >= 100){
        $jkl = intval($i / 100);
        $words.=$hang[$jkl];
        $i%=100;
    }
    if($i >= 20){
        $jkl = intval($i / 10);
        $words.=$des[$jkl];
        $i%=10;
        $fl=1;
    }
    switch($i){
        case 1: $fem=1; break;
        case 2:
        case 3:
        case 4: $fem=2; break;
        default: $fem=3; break;
    }
    if( $i ){
        if( $i < 3 && $f > 0 ){
            if ( $f >= 2 ) {
                $words.=$_1_19[$i];
            }
            else {
                $words.=$_1_2[$i];
            }
        }
        else {
            $words.=$_1_19[$i];
        }
    }
}
function utf8_ucfirst($string) {
  $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
  return $string;

}

function num2str($L){
    global $_1_2, $_1_19, $des, $hang, $namerub, $nametho, $namemil, $namemrd, $kopeek;

    $s="";
    $s1="";
    $s2=" ";
    $kop=intval( ( $L*100 - intval( $L )*100 ));
    $L=intval($L);
    if($L>=1000000000){
        $many=0;
        semantic(intval($L / 1000000000),$s1,$many,3);
        if(!empty($s))
          $s.=" ".$s1.$namemrd[$many];
        else
          $s.=$s1.$namemrd[$many];
        $L%=1000000000;
    }

    if($L >= 1000000){
        $many=0;
        semantic(intval($L / 1000000),$s1,$many,2);
        if(!empty($s))
        $s.=" ".$s1.$namemil[$many];
        else
          $s.=$s1.$namemil[$many];
        $L%=1000000;
        if($L==0){
            $s.="рублей ";
        }
    }

    if($L >= 1000){
        $many=0;
        semantic(intval($L / 1000),$s1,$many,1);
        if(!empty($s))
          $s.=" ".$s1.$nametho[$many];
        else
          $s.=$s1.$nametho[$many];
        $L%=1000;
        if($L==0){
            $s.="рублей ";
        }
    }

    if($L != 0){
        $many=0;
        semantic($L,$s1,$many,0);
        $s.=$s1.$namerub[$many];
    }

    if($kop > 0){
        $many=0;
        semantic($kop,$s1,$many,1);
        $s.=$s1.$kopeek[$many];
    }
    else {
        $s.=" 00 копеек";
    }

    return trim(str_replace('  ', ' ', $s));
}

function urlencode_light($str) {
  if (empty($str)) {
    return '_';
  }
  //$str=iconv('UTF-8', 'ASCII//TRANSLIT', $str);
  if ( false===strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ) // ненавидите ли вы IE так, как ненавижу его я?
  {
    return urlencode($str);
  }
  $str=str_replace('%', '%25', $str);
  $str=str_replace(' ', '+', $str);
  $str=str_replace('"', '%22', $str);
  $str=str_replace("'", '%27', $str);
  $str=str_replace('&', '%26', $str);
  $str=str_replace('<', '%3C', $str);
  $str=str_replace('>', '%3E', $str);
  return $str;
}

function parseXml($xml)
{
  if (empty($xml))
  {
      throw new Exception('Отсутствует ХML',500);
  }
  if (false===strpos($xml, '<?xml'))
  {
      throw new Exception('Переданные данные не являются валидным ХML',500);
  }
  return loadXML($xml);
}

/**
 * Загрузка XML с контролем ошибок
 * @param string $xml Имя файла с данными или сами данные
 * @param bool $is_file Если true, то $xml считается именем файла, если false то непосредственно данными
 * @return SimpleXMLElement
 */
function loadXML($xml, $is_file=false) {
  libxml_use_internal_errors(true);
  if ($is_file) {
    $xml = simplexml_load_file($xml);
  } else {
    $xml = simplexml_load_string($xml);
  }
  if (!$xml) {
    $errors = libxml_get_errors();
    $errors_text = array();
    foreach ($errors as $error) {
      $e = '';
      switch ($error->level) {
        case LIBXML_ERR_WARNING:
          $e .= "Warning {$error->code}: ";
          break;
        case LIBXML_ERR_ERROR:
          $e .= "Error {$error->code}: ";
          break;
        case LIBXML_ERR_FATAL:
          $e .= "Fatal Error {$error->code}: ";
          break;
        default:
          $e .= "Unknown Error ({$error->level}) {$error->code}: ";
      }
      $e .= trim($error->message) . " at {$error->line}:{$error->column}";
      $errors_text[] = $e;
    }
    throw new Exception("Невозможно загрузить XML! Подробности ошибки: ".join("\n", $errors_text));
  }
  return $xml;
}

function HexToBase64($hex)
{
  return @base64_encode(pack("H*", trim($hex)));
}

function Base64ToHex($base64)
{
  return bin2hex(base64_decode($base64));
}

function XMLEscapeString($str)
{
  $str = str_replace('&', '&amp;', $str);
  $str = str_replace('<', '&lt;', $str);
  $str = str_replace('>', '&gt;', $str);
  $str = str_replace("'", '&apos;', $str);
  $str = str_replace('"', '&quot;', $str);
  return $str;
}


function safe_base64_decode($encoded, $strict=false)
{
  $decoded = "";
  for ($i=0; $i < ceil(strlen($encoded)/256); $i++) {
   $t = base64_decode(substr($encoded,$i*256,256), $strict);
   if ($strict && false===$t)
   {
     return false;
   }
   $decoded = $decoded.$t;
  }
  return $decoded;
}

function isBase64($str)
{
  $t = base64_decode(preg_replace('@\s+@m', '', $str), true);
  if ( false!==$t && !empty($t) )
  {
    return true;
  }
  return false;
}

function prepareCSVRow($data, $numbers_as_text = true) {
  $s='';
  $d = array();
  foreach($data as $val)
  {
    if ( !$numbers_as_text && (is_int($val) || is_float($val))) {
      $d[] = str_replace(',', ".", trim($val));
    } else {
      $d[] = '"'.str_replace('"', "'", trim($val)).'"';
    }
  }
  $s=join("\t", $d);
  if (''!=$s)
  {
    $s.="\r\n";
  }
  return $s;
}

function dumpCSV($headers, $rows, $encoding='windows-1251', $filename=null) {
  putDownloadHeaders($filename, "text/csv; charset=$encoding");
  $result = array();
  foreach ($headers as $v) {
    $result[]=$v;
  }
  echo iconv('UTF-8', "$encoding//TRANSLIT", prepareCSVRow($result));
  foreach ($rows as $row) {
    $result = array();
    foreach ($headers as $h=>$v) {
      $result[]=$row[$h];
    }
    echo iconv('UTF-8', "$encoding//TRANSLIT", prepareCSVRow($result));
    //ob_flush();
    //flush();
  }
  exit;
}

function escapeXML($string) {
  return htmlspecialchars($string, ENT_NOQUOTES, 'UTF-8');
}

function escapeXMLAttr($string) {
  return str_replace('"', '&quot;', $string);
}

/**
 * Преобразует английское слово в форму единственного числа
 *
 * @param string $word
 * @return string
 */
function singularize($word) {
  $len = strlen($word);
  if ($len>3) {
    $tail = substr($word,-3);
    if ('ies'==$tail) {
      return substr($word, 0, -3).'y';
    } elseif ('ses'==$tail || 'xes'==$tail) {
      return substr($word, 0, -2);
    }
  }
  if ($len>1 && 's'==substr($word,-1)) {
    return substr($word, 0, -1);
  }
  return $word;
}

function isNumericArray($array) {
  if (array_keys($array) !== range(0, count($array) - 1)) {
    return false;
  }
  return true;
}

/**
 * Возвращает XML на основе переданных данных
 * @param string $tag имя тега, в который следует завернуть результат
 * @param mixed $value данные для вывода
 * @param array $flags массив настроек. Возможны следующие значения:
 *   xmlns  — bool|array добавить к корневому элементу стандартные неймспейсы
 *            (XMLSchema и XMLSchema-instance) может быть массивом, в таком
 *            случае кроме стандартных добавятся еще и неймспейсы из массива.
 *            Имя неймспейса — ключ массива, путь к неймспейсу — значение
 *   xmltag — bool добавить к выводу XML-заголовок <?xml ... ?>
 *   type   — bool выводить типы у элементов (по умолчанию true)
 *   ns     — string неймспейс корневого элемента
 *   opts   — array дополнительные аттрибуты для корневого элемента
 *            (массив: ключ — имя аттрибута, значение — значение аттрибута)
 *   collapse_single_arrays — «схлопывать» массивы из одного элемента. Ключ этого
 *            элемента массива пойдет именем тега, а значение — значением тега.
 *   pretty_print — bool форматировать вывод для читаемости (по умолчанию false)
 *   indent — индентация строк вывода при pretty_print (по умолчанию два пробела)
 * @return string
 */
function toXML($tag, $value, $flags=array()) {
  $defaults = array('xmlns'=>false, 'xmltag'=>false, 'type'=>true,
      'collapse_single_arrays'=>false, 'pretty_print'=>false, 'indent_level'=>0,
      'indent'=>'  ');
  $flags = array_merge($defaults, $flags);
  $opts = array();
  $type = false;
  $indent_sub = false;
  $str = '';

  if (is_array($value) ) {
    foreach ($value as $k=>$v) {
      if ('@'==substr($k,0,1) && '@@value'!=$k) {
        $opts[substr($k, 1)] = "$v";
        unset($value[$k]);
      }
    }
    if (isset($value['@@value'])) {
      $value = $value['@@value'];
    } elseif ($flags['collapse_single_arrays'] && 1==count($value)) {
      $tag = array_keys($value);
      $tag = $tag[0];
      $value = array_shift($value);
    }
  }

  if (is_object($value)) {
    $value = get_object_vars($value);
  }
  if (is_array($value)) {
    $sub_flags = array_merge($flags, array('xmlns'=>false, 'xmltag'=>false, 'opts'=>false, 'ns'=>false));
    $sub_flags['indent_level']++;

    if (isNumericArray($value)) {
      $type = 'Struct';
    } else {
      $type = 'Array';
    }
    $single_tag = singularize($tag);
    $single_tag = escapeXML($single_tag);
    $indent_sub = true;
    foreach ($value as $k=>$v) {
      if ('@'==substr($k,0,1)) {
        $opts[substr($k, 1)] = "$v";
      } else {
        $str .= toXML(is_int($k)?$single_tag:$k, $v, $sub_flags);
      }
    }
  } elseif (is_bool($value)) {
    $str = $value?'1':'0';
    $type = 'boolean';
  } elseif (is_int($value)) {
    $str = $value;
    $type = 'integer';
  } elseif (is_float($value)) {
    $str = sprintf('%F', $value);
    $type = 'float';
  } elseif (is_string($value)) {
    $str = escapeXML($value);
    $type = 'string';
  } elseif (null===$value) {
    $opts['xsi:nil'] ="true";
  } else {
    $str = escapeXML("$value");
  }
  if ($flags['xmlns']) {
    $opts['xmlns:xsd'] = "http://www.w3.org/2001/XMLSchema";
    $opts['xmlns:xsi'] ="http://www.w3.org/2001/XMLSchema-instance";
    if (is_array($flags['xmlns'])) {
      foreach ($flags['xmlns'] as $k=>$v) {
        $opts["xmlns:$k"] = "$v";
      }
    }
  }
  $result = '';
  if ($flags['xmltag']) {
    $result = '<?xml version="1.0" encoding="UTF-8"?>';
    if ($flags['pretty_print']) {
      $result .= "\n";
    }
  }
  if (isset($flags['opts']) && $flags['opts']) {
    $opts = array_merge($opts, $flags['opts']);
  }
  if ($flags['type'] && $type) {
    $opts["xsi:type"] = "xsd:$type";
  }
  if (!empty($opts)) {
    $rawopts = array();
    foreach ($opts as $k=>$v) {
      $rawopts[] = escapeXMLAttr($k).'="'.  escapeXMLAttr($v).'"';
    }
    $opts = join(' ', $rawopts);
    unset($rawopts);
  } else {
    $opts = false;
  }
  if (isset($flags['ns']) && $flags['ns']) {
    $tag = "{$flags['ns']}:$tag";
  }
  $tag = escapeXML($tag);
  if ($flags['pretty_print']) {
    $indent = str_repeat($flags['indent'], $flags['indent_level']);
    $result .= $indent;
  }
  $result .= "<$tag".($opts?" $opts":'');
  if (''==$str) {
    $result .='/>';
  } else {
    if ($flags['pretty_print'] && $indent_sub) {
      $result .= ">\n{$str}{$indent}</$tag>";
    } else {
      $result .= ">$str</$tag>";
    }
  }
  if ($flags['pretty_print']) {
    $result .= "\n";
  }
  return $result;
}

function parseXmlError($error, $xml) {
  if (is_string($xml)) {
    $xml = explode("\n", $xml);
  }
  $return = $xml[$error->line - 1] . "\n";
  $return .= str_repeat('-', $error->column) . "^\n";

  switch ($error->level) {
    case LIBXML_ERR_WARNING:
      $return .= "Warning {$error->code}: ";
      break;
    case LIBXML_ERR_ERROR:
      $return .= "Error {$error->code}: ";
      break;
    case LIBXML_ERR_FATAL:
      $return .= "Fatal Error {$error->code}: ";
      break;
  }

  $return .= trim($error->message) .
    "\n  Line: {$error->line}" .
    "\n  Column: {$error->column}";

  if ($error->file) {
    $return .= "\n  File: {$error->file}";
  }

  return $return;
}

function thousandths2str($num) {
  $nul='ноль';
  $ten=array(
    array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
    array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
  );
  $a20=array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
  $tens=array(2=>'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
  $hundred=array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
  $unit=array( // Units
    array('тысячных' ,'тысячных' ,'тысячных',	 1),
    //array('целых'   ,'целых'   ,'целых'    ,0),
    array(''   ,''   ,''    ,0),
    array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
    array('миллион' ,'миллиона','миллионов' ,0),
    array('миллиард','милиарда','миллиардов',0),
  );
  //
  list($rub,$kop) = explode('.',sprintf("%016.3f", floatval($num)));

  $out = array();
  if (intval($rub)>1) {
    foreach(str_split($rub,3) as $uk=>$v) { // by 3 symbols
      if (!intval($v)) continue;
      $uk = sizeof($unit)-$uk-1; // unit key
      $gender = $unit[$uk][3];
      list($i1,$i2,$i3) = array_map('intval',str_split($v,1));
      // mega-logic
      $out[] = $hundred[$i1]; # 1xx-9xx
      if ($i2>1) $out[]= $tens[$i2].' '.$ten[$gender][$i3]; # 20-99
      else $out[]= $i2>0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
      if ($uk>1) $out[]= morph($v,$unit[$uk][0],$unit[$uk][1],$unit[$uk][2]);
    }
  }
  else if (intval($rub)==0) {
    $out[] = $nul;
  }

  if (intval($rub)==1) {
    //$out[] = 'одна целая' . ($kop > 0 ? ',' : '');
    $out[] = $kop > 0 ? 'одна целая,' : 'один';
  } else {
    $out[] = morph(intval($rub), $unit[1][0],$unit[1][1],$unit[1][2]) . ($kop > 0 ? 'целых,' : '');
  }
  if ($kop > 0) {
    $out[] = $kop.' '.morph($kop,$unit[0][0],$unit[0][1],$unit[0][2]); // kop
  }

  return trim(preg_replace('/ {2,}/', ' ', join(' ',$out)));
}

function roubles2str($num) {
  $nul='ноль';
  $ten=array(
    array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),
    array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять'),
  );
  $a20=array('десять','одиннадцать','двенадцать','тринадцать','четырнадцать' ,'пятнадцать','шестнадцать','семнадцать','восемнадцать','девятнадцать');
  $tens=array(2=>'двадцать','тридцать','сорок','пятьдесят','шестьдесят','семьдесят' ,'восемьдесят','девяносто');
  $hundred=array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот','восемьсот','девятьсот');
  $unit=array( // Units
    array('копейка' ,'копейки' ,'копеек',	 1),
    array('рубль'   ,'рубля'   ,'рублей'    ,0),
    array('тысяча'  ,'тысячи'  ,'тысяч'     ,1),
    array('миллион' ,'миллиона','миллионов' ,0),
    array('миллиард','милиарда','миллиардов',0),
  );
  //
  list($rub,$kop) = explode('.',sprintf("%015.2f", floatval($num)));
  $out = array();
  if (intval($rub)>0) {
    foreach(str_split($rub,3) as $uk=>$v) { // by 3 symbols
      if (!intval($v)) continue;
      $uk = sizeof($unit)-$uk-1; // unit key
      $gender = $unit[$uk][3];
      list($i1,$i2,$i3) = array_map('intval',str_split($v,1));
      // mega-logic
      $out[] = $hundred[$i1]; # 1xx-9xx
      if ($i2>1) $out[]= $tens[$i2].' '.$ten[$gender][$i3]; # 20-99
      else $out[]= $i2>0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
      // units without rub & kop
      if ($uk>1) $out[]= morph($v,$unit[$uk][0],$unit[$uk][1],$unit[$uk][2]);
    } //foreach
  }
  else $out[] = $nul;
  $out[] = morph(intval($rub), $unit[1][0],$unit[1][1],$unit[1][2]); // rub
  $out[] = $kop.' '.morph($kop,$unit[0][0],$unit[0][1],$unit[0][2]); // kop
  return trim(preg_replace('/ {2,}/', ' ', join(' ',$out)));
}

/**
 * Склоняем словоформу
 * @ author runcore
 */
function morph($n, $f1, $f2, $f5) {
  $n = abs(intval($n)) % 100;
  if ($n>10 && $n<20) return $f5;
  $n = $n % 10;
  if ($n>1 && $n<5) return $f2;
  if ($n==1) return $f1;
  return $f5;
}

function formatTime($date, $format='d MMMM yyyy г.') {
  if (empty($date)) {
    return '';
  }
  $date = new Zend_Date(toTimestamp($date), 'ru_RU');
  return $date->toString($format);
}


