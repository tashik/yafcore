<?php
/**
 *
 * Класс обработки rtf шаблона
 *
 * User: cyrill
 * Date: 15.04.12
 * Time: 13:12
 */
class Core_RtfTemplate
{

  private $file_data = null;
  private $tokens_form_file = array();
  private $replacements_map = array();
  private $_tag_callback;

  public static $default_units = "mm"; // default measurement units
  public static $pg_width=210; // page width (mm)
  public static $mar_left=19; // left margin width (mm)
  public static $mar_right=10; // right margin width (mm)
  /**
   *
   * Упрощенный интерфейс обработки шаблона
   *
   * @static
   * @param $in_file
   * @param $out_file
   * @param array $replacements
   * @return bool|int
   */
  public static function createFileFromTemplate($in_file,$out_file,array $replacements) {

    if (empty($in_file) || empty($out_file) || empty($replacements)) {
      return false;
    }

    $obj = new self();
    $obj->loadFile($in_file);
    $obj->setReplacements($replacements);
    $obj->getTokensFromFile();
    $obj->doReplacements();

    return $obj->saveFile($out_file);
  }

  public static function processRtfTemplate($in_data, array $replacements) {

    $obj = new self();
    $obj->file_data = $in_data;
    $obj->setReplacements($replacements);
    $obj->getTokensFromFile();
    $obj->doReplacements();

    return $obj->file_data;
  }

  public static function parseTemplate($in_data, $callback) {
    $obj = new self();
    $obj->file_data = $in_data;
    $obj->getTokensFromFile();
    $obj->_tag_callback = $callback;
    $obj->_parseTemplate();
    return $obj->file_data;
  }

  /**
   *
   * Удаление не экранированных пробелов
   *
   * @param $tag
   * @param array $quotes
   * @return array
   */
  protected function removeSpaces($tag,$quotes = array("'",'"')) {
    $depth = 0;
    for ($i=0;$i<mb_strlen($tag);$i++) {
      $chr = mb_substr($tag,$i,1);
      if (in_array($chr,$quotes)) {
        if (!$depth) {
          $depth++;
        } else {
          $depth--;
        }
      } elseif ($chr==' ' && !$depth) {
        $tag = mb_substr($tag,0,$i).mb_substr($tag,$i+1);
        $i--;
      }
    }
    return $tag;
  }

  protected function _processTag($tag) {
    //$tag = iconv('windows-1251', 'UTF-8//TRANSLIT', $tag);
    $user_func_result = call_user_func($this->_tag_callback,$tag);
    if (is_object($user_func_result) && $user_func_result instanceof Core_Template_Renderer) {
      $user_func_result->setTemplater($this);
      return $user_func_result->renderRtf();
    }
    return self::getUnicodeEntities($user_func_result, 'UTF-8');
  }

  protected function _parseTemplate() {
    if (empty($this->_tag_callback) || empty($this->tokens_form_file) || empty($this->file_data)) {
      return false;
    }

    // сортируем теги по длинне, от самых длинных. так мы исключаем возможность ошибки замены вложенных тегов
    uksort($this->tokens_form_file, create_function('$a,$b', 'return strlen($a) < strlen($b);'));
    foreach ($this->tokens_form_file as $key=>$values) {
     if (is_scalar($key)) {
       // обычные замены
       $this->file_data = str_replace($values,$this->_processTag('{'.trim($key).'}'),$this->file_data);
       //}
     }
//     else {
       // массивы
//       foreach ($this->tokens_form_file as $token_name=>$repl) {
//         if (strpos($token_name,$key)===0) {
//           $tableRows = array();
//           $columnHeaders = explode('|', strstr($token_name,'|'));
//           for ($i=1;$i<count($columnHeaders);$i++){
//             $columnHeaders[$i] = str_replace('\}','',$columnHeaders[$i]);
//             $columnHeaders[$i] = str_replace('}{','',$columnHeaders[$i]);
//             $tableRows[0][] = $this->_html_entity_decode($columnHeaders[$i]);
//           }
//           foreach ($value as $row) {
//             $tableRows[] = $row;
//           }
//           $table = $this->add_tbl($tableRows);
//           $this->file_data = str_replace($repl, $table, $this->file_data);
//         }
//       }
//     }


   }
   return true;
  }

  public function __construct() {
    // загрузка конфига
    //include dirname(__FILE__) . "/rtf_config.inc";
  }

  /**
   *
   * Проверка кода RTF на текст
   *
   * @param $s
   * @return bool
   */
  private function rtf_isPlainText($s) {
    $arrfailAt = array("*", "fonttbl", "colortbl", "datastore", "themedata");
    for ($i = 0; $i < count($arrfailAt); $i++)
        if (!empty($s[$arrfailAt[$i]])) return false;
    return true;
  }

  private function get_html_translation_table_CP1251() {
      $trans = get_html_translation_table(HTML_ENTITIES);
      $trans[chr(130)] = '&sbquo;';    // Single Low-9 Quotation Mark
      $trans[chr(131)] = '&fnof;';    // Latin Small Letter F With Hook
      $trans[chr(132)] = '&bdquo;';    // Double Low-9 Quotation Mark
      $trans[chr(133)] = '&hellip;';    // Horizontal Ellipsis
      $trans[chr(134)] = '&dagger;';    // Dagger
      $trans[chr(135)] = '&Dagger;';    // Double Dagger
      $trans[chr(136)] = '&circ;';    // Modifier Letter Circumflex Accent
      $trans[chr(137)] = '&permil;';    // Per Mille Sign
      $trans[chr(138)] = '&Scaron;';    // Latin Capital Letter S With Caron
      $trans[chr(139)] = '&lsaquo;';    // Single Left-Pointing Angle Quotation Mark
      $trans[chr(140)] = '&OElig;    ';    // Latin Capital Ligature OE
      $trans[chr(145)] = '&lsquo;';    // Left Single Quotation Mark
      $trans[chr(146)] = '&rsquo;';    // Right Single Quotation Mark
      $trans[chr(147)] = '&ldquo;';    // Left Double Quotation Mark
      $trans[chr(148)] = '&rdquo;';    // Right Double Quotation Mark
      $trans[chr(149)] = '&bull;';    // Bullet
      $trans[chr(150)] = '&ndash;';    // En Dash
      $trans[chr(151)] = '&mdash;';    // Em Dash
      $trans[chr(152)] = '&tilde;';    // Small Tilde
      $trans[chr(153)] = '&trade;';    // Trade Mark Sign
      $trans[chr(154)] = '&scaron;';    // Latin Small Letter S With Caron
      $trans[chr(155)] = '&rsaquo;';    // Single Right-Pointing Angle Quotation Mark
      $trans[chr(156)] = '&oelig;';    // Latin Small Ligature OE
      $trans[chr(159)] = '&Yuml;';    // Latin Capital Letter Y With Diaeresis
      ksort($trans);
      return $trans;
  }

  public function numeric_entities($string){
      $mapping = array();
      foreach ($this->get_html_translation_table_CP1251() as $char => $entity){
          $mapping[$entity] = iconv('windows-1251','utf-8', $char);
      }
      return str_replace(array_keys($mapping), $mapping, $string);
  }  /**
   * converts text with utf8 characters into rtf utf8 entites
   *
   * @param string $text
   */
  public function getUnicodeEntities($text, $inCharset) {

    $text = $this->numeric_entities($text);
    $text = str_replace("\\", "\\\\", $text);

    if ($inCharset != 'UTF-8' && mb_detect_encoding($text)=='ASCII') {
//        $text = html_entity_decode($text,ENT_COMPAT | ENT_HTML401,'cp1251');
        if (extension_loaded('iconv')) {
            $text = iconv($inCharset, 'UTF-8//TRANSLIT', $text);
        }
        else {
            throw new Exception('Iconv extension is not available! '
                                         . 'Activate this extension or use UTF-8 encoded texts!');
        }
    }
    $text = $this->utf8ToUnicode($text);
    return $this->unicodeToEntitiesPreservingAscii($text);
  }


  /**
   * gets unicode for each character
   * @see http://www.randomchaos.com/documents/?source=php_and_unicode
   *
   * @return array
   */
  private function utf8ToUnicode($str) {
    $unicode = array();
    $values = array();
    $lookingFor = 1;

    for ($i = 0; $i < strlen($str); $i++ ) {
      $thisValue = ord($str[$i]);

      if ($thisValue < 128) {
        $unicode[] = $thisValue;
      }
      else {
        if (count($values) == 0) {
            $lookingFor = $thisValue < 224 ? 2 : 3;
        }

        $values[] = $thisValue;

        if (count($values) == $lookingFor) {
          $number = $lookingFor == 3
                    ? (($values[0] % 16) * 4096) + (($values[1] % 64) * 64) + ($values[2] % 64)
                    : (($values[0] % 32) * 64) + ($values[1] % 64);

          $unicode[] = $number;
          $values = array();
          $lookingFor = 1;
        }
      }
    }

    return $unicode;
  }


  /**
   * converts text with utf8 characters into rtf utf8 entites preserving ascii
   *
   * @param  string $unicode
   * @return string
   */
  private function unicodeToEntitiesPreservingAscii($unicode) {
    $entities = '';
    foreach ($unicode as $value) {
      if ($value != 65279) {
        $entities .= $value > 127
                     ? '\uc0{\u' . $value . '}'
                     : chr($value);
      }
    }
    return str_replace("\n", '\line', $entities);
  }


  private function twips($num) { // great thanks to Ian M. Nordby for this function
  	//added units recognition -- assumes 1pt=1/72in exactly (IMN)...
  	if (preg_match('/^(-?[0-9]+(\.[0-9]+)?)[ ]?(mm|cm|q|kyu|in|pt|pts|picas|twips)$/i',trim($num),$regs)) {
  		$units = strtolower($regs[3]);
  		$num = (float)$regs[1];
  	}
  	else {
  		$units = self::$default_units;
  	}
  	switch ($units) { //unit type
  		case 'cm'   : $sum = round($num*567); break; //centimeters (actual ~566.929)
  		case 'mm'   : $sum = round($num*56.7); break; //millimeters (=1/10 cm)
  		case 'q'    : //alias of 'kyu'
  		case 'kyu'  : $sum = round($num*14.175); break; //Q/kyu (=1/4 mm)
  		case 'in'   : $sum = round($num*1440); break; //inches
  		case 'pt'   : //alias of 'pts' (points)
  		case 'pts'  : $sum = round($num*20); break; //pt/pts (=1/72 in)
  		case 'picas': $sum = round($num*240); break; //picas (=12 pts or 1/6 in)
  		case 'twips': $sum = round($num); break; //twips (=1/20 pt or 1/1440 in)
  	}
  	return $sum;
  } // end of function
  /**
   *
   * Создание RTF таблицы из массива
   *
   * @param array $tar — массив строк, каждая из которых массив ячеек
   * @param int $flg режим выделения: 1 — выделить первую строку, 2 — выделить первую колонку
   * @param int $brd — отрисовывать рамку
   * @param int $bld — разрешить выделение строк/колонок жирным ($flg)
   * @param int $hlt — разрешить выделение строк/колонок инверсным цветом ($flg)
   * @param array $widths — массив ширин столбцов (в процентах, если не указано — ширина раскидается поровну)
   * @return string
   */
  public function add_tbl($tar, $flg=0, $brd=1, $bld=1, $hlt=1, $widths=null) {
    $result = '';
    $width = round($this->twips(self::$pg_width) - ($this->twips(self::$mar_left) + $this->twips(self::$mar_right)));
    $columns = $widths?count($widths):count($tar[0]);
    $ftb = "{\\par\\ql";
    for ($i = 0; $i < count($tar); $i++) {
      $ttt = 0;
      $ftb.="\\trowd\\trqc\\trgaph108\\trrh0\\trleft36\r\n";
      $tmp2 = "";
      $rowprefix = "";
      for ($r = 0; $r < $columns; $r++) {
        $tmp1 = "";
        //$ttt += round($tar[0][$r] * $p);
        $v = $tar[$i][$r];
        $meta = array();
        if (is_array($v)) {
          $meta = $v;
          $v = $v['v'];
          unset($meta['v']);
        }
        if ($widths) {
          $p = round($width * $widths[$r] / 100);
        } else {
          $p = round($width / $columns);
        }

        if (isset($meta['svmerge'])) {
          $rowprefix .= "\\clvmgf";
        }
        if (isset($meta['shmerge'])) {
          $r++;
          while ($r<$columns) {
            $vv = $tar[$i][$r];
            $vmeta = array();
            if (is_array($vv)) {
              $vmeta = $vv;
            }
            if ($widths) {
              $p += round($width * $widths[$r] / 100);
            } else {
              $p += round($width / $columns);
            }
            if (isset($vmeta['ehmerge'])) {
              break;
            }
            $r++;
          }
        }
        $ttt += $p;
        if (isset($meta['evmerge'])) {
          $rowprefix .= "\\clvmrg";
        }
        if (!isset($meta['valign'])) {
          $meta['valign'] = 't';
        }
        $tmp1 .= "\\clvertal{$meta['valign']}";
        if ($hlt == 1 && !isset($meta['nohlt'])) {
          if ($flg == 1) {
            if ($i == 1) {
              $rowprefix .= "\\clcbpat8\\clshdng3000";
            }
          }
          if ($flg == 2) {
            if ($r == 0) {
              $rowprefix .= "\\clcbpat8\\clshdng3000";
            }
          }
          //if ($flg==1) { if ($i==1) { $tmp1 .= "\\b"; } }
          //if ($flg==2) { if ($r==0) { $tmp1 .= "\\b"; } }
        }

        if ($brd == 1 && !isset($meta['nobr'])) {
          $rowprefix .= (!isset($meta['notbr'])?"\\clbrdrt\\brdrs\\brdrw10 ":'').
                   (!isset($meta['nolbr'])?"\\clbrdrl\\brdrs\\brdrw10 ":'').
                   (!isset($meta['nobbr'])?"\\clbrdrb\\brdrs\\brdrw10 ":'').
                   (!isset($meta['norbr'])?"\\clbrdrr\\brdrs\\brdrw10 ":'');
        }

        $unbold = false;
        if ($bld == 1 && !isset($meta['nobld'])) {
          if (isset($meta['bold']) || ($i == 0 && $flg == 1)) {
            $tmp1.="\\b";
            $unbold = true;
          } else {
            if ($r == 0 && $flg == 2) {
              $tmp1.="\\b";
              $unbold = true;
            } else {
              //$tmp1.="\\plain";
            }
          }
        } else {
          //$tmp1.="\\plain";
        }

        $rowprefix .= "\\cltxlrtb\\cellx" . $ttt;
        if ($i == 0) {
          $cellData = $this->getUnicodeEntities($v, 'UTF-8'); //$tar[$i][$r]; WTF?
        } else {
          $cellData = $this->getUnicodeEntities($v, 'UTF-8');
        }
        if (isset($meta['align'])) {
          $cellData = "\\q{$meta['align']} {$cellData}";
        } else {
          $cellData = " $cellData";
        }
        if ($unbold) {
          $cellData .= "\b0";
        }
        $tmp2 .= "{$tmp1}\\intbl{$cellData}\\cell \\pard \r\n";
      }

      $ftb .= $rowprefix."\r\n" . $tmp2 . "\\intbl \\row \\pard\r\n";
    }
    $result .= $ftb.'}';
    return $result;
  }

  /**
   *
   * загружает файл в память
   *
   * @param $filename
   */
  public function loadFile($filename) {
    if (file_exists($filename)) {
      $this->file_data = file_get_contents($filename);
      return true;
    } else {
      throw new Exception('File '.$filename.' not exists');
    }
  }


  private function code2utf($num){
   if ($num < 128) {
    return chr($num);
   }
   if ($num < 2048) {
    return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
   }
   if ($num < 65536) {
    return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
   }
   if ($num < 2097152) {
    return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
   }
   return '';
  }
  /**
   *
   * возвращает возможные токены для замены с их RTF интерпритацией
   *
   */
  public function getTokensFromFile() {

    if (empty($this->file_data)) {
      return false;
    }
    if (!strlen($this->file_data))
        return "";

    $tokens = array();

    // Итак, самое главное при чтении данных из rtf'а - это текущее состояние
    // стека модификаторов. Начинаем мы, естественно, с пустого стека и отрицательного
    // его (стека) уровня.
    $document = "";
    $stack = array();
    $j = -1;
    $page_start = $page_end = 0;
    $plain_page_start = 0;
    $depth = 0;
    // Читаем посимвольно данные...
    for ($i = 0, $len = strlen($this->file_data); $i < $len; $i++) {
        $c = $this->file_data[$i];
        $c = "$c";

        // исходя из текущего символа выбираем, что мы с данными будем делать.
        $last_keyword = '';
        switch ($c) {
            // итак, самый важный ключ "обратный слеш"
            case "\\":
                // читаем следующий символ, чтобы понять, что нам делать дальше
                $nc = $this->file_data[$i + 1];

                // Если это другой бэкслеш, или неразрывный пробел, или обязательный
                // дефис, то мы вставляем соответствующие данные в выходной поток
                // (здесь и далее, в поток втавляем только в том случае, если перед
                // нами именно текст, а не шрифтовая вставка, к примеру).
                if ($nc=='{') {
                  $document.='{';
                  if ($depth<1) {
                    $page_start = $i;
                    $plain_page_start = strlen($document);
                    $depth = 1;
                  } else {
                    $depth++;
                  }

                } elseif ($nc=='}') {
                  $document.='}';
                  if ($depth==1) { // костыл бля
                    $page_end = $i;
                    if ($page_start<$page_end) {
                        $tokens[substr($document, $plain_page_start,-1)][] = substr($this->file_data,$page_start,$page_end-$page_start+2);
                    }
                    $depth = 0;
                  } else {
                    $depth--;
                  }
                } elseif ($nc == '\\' && $this->rtf_isPlainText($stack[$j])) {
                  $document .= '\\';
                } elseif ($nc == '~' && $this->rtf_isPlainText($stack[$j])) {
                //  $document .= ' ';
                }
//                } elseif ($nc == '_' && $this->rtf_isPlainText($stack[$j])) {
//                  $document .= '-';
//                }
                // Если перед нами символ звёздочки, то заносим информацию о нём в стек.
                if ($nc == '*') $stack[$j]["*"] = true;
                // Если же одинарная кавычка, то мы должны прочитать два следующих
                // символа, которые являются hex-ом символа, который мы должны
                // вставить в наш выходной поток.
                elseif ($nc == "'") {
                    $hex = substr($this->file_data, $i + 2, 2);
                    if ($this->rtf_isPlainText($stack[$j])) {
                       //$document .= $this->_html_entity_decode("&#".hexdec($hex).";");
                      $document .= iconv('windows-1251', 'UTF-8', chr(hexdec($hex)));
                    }
                    // Мы прочитали два лишних символа, должны сдвинуть указатель.
                    $i += 2;
                // Так перед нами буква, а это значит, что за \ идёт упраляющее слово
                // и возможно некоторый циферный параметр, которые мы должны прочитать.
                } elseif ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                    $word = "";
                    $param = null;
                    // $was_control_char = true; ????

                    // Начинаем читать символы за бэкслешем.
                    $last_keyword = '';
                    for ($k = $i + 1, $m = 0; $k < strlen($this->file_data); $k++, $m++) {
                        $nc = $this->file_data[$k];
                        // Если текущий символ буква и до этого не было никаких цифр,
                        // то мы всё ещё читаем управляющее слово, если же были цифры,
                        // то по документации мы должны остановиться - ключевое слово
                        // так или иначе закончилось.
                        if ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                            if (empty($param))
                                $word .= $nc;
                            else
                                break;
                        // Если перед нами цифра, то начинаем записывать параметр слова.
                        } elseif ($nc >= '0' && $nc <= '9')
                            $param .= $nc;
                        // Минус может быть только перед цифровым параметром, поэтому
                        // проверяем параметр на пустоту или в противном случае
                        // мы вылезли за пределы слова с параметром.
                        elseif ($nc == '-') {
                            if (empty($param))
                                $param .= $nc;
                            else
                                break;
                        // В любом другом случае - конец.
                        } else
                            break;
                    }
                    // Сдвигаем указатель на количество прочитанных нами букв/цифр.
                    $i += $m - 1;

                    // Начинаем разбираться, что же мы такое начитали. Нас интересует
                    // именно управляющее слово.
                    $toText = "";
                    switch (strtolower($word)) {
                        // Если слово "u", то параметр - это десятичное представление
                        // unicode-символа, мы должны добавить его в выход.
                        // Но мы должны учесть, что за символом может стоять его
                        // замена, в случае, если программа просмотрщик не может работать
                        // с Unicode, поэтому при наличии \ucN в стеке, мы должны откусить
                        // "лишние" N символов из исходного потока.
                        case "u":
                            $toText .= $this->code2utf(($param));
                            $ucDelta = @$stack[$j]["uc"];
                            if ($ucDelta > 0)
                                $i += $ucDelta;
                        break;
                        // Обработаем переводы строк, различные типы пробелов, а также символ
                        // табуляции.
                        case "par": case "page": case "column": case "line": case "lbr":
                            $toText .= "\n";
                        break;
                        case "emspace": case "enspace": case "qmspace":
                            $toText .= " ";
                        break;
                        case "tab": $toText .= "\t"; break;
                        // Добавим вместо соответствующих меток текущие дату или время.
                        case "chdate": $toText .= date("m.d.Y"); break;
                        case "chdpl": $toText .= date("l, j F Y"); break;
                        case "chdpa": $toText .= date("D, j M Y"); break;
                        case "chtime": $toText .= date("H:i:s"); break;
                        // Заменим некоторые спецсимволы на их html-аналоги.
                        case "emdash": $toText .= $this->_html_entity_decode("&mdash;"); break;
                        case "endash": $toText .= $this->_html_entity_decode("&ndash;"); break;
                        case "bullet": $toText .= $this->_html_entity_decode("&#149;"); break;
                        case "lquote": $toText .= $this->_html_entity_decode("&lsquo;"); break;
                        case "rquote": $toText .= $this->_html_entity_decode("&rsquo;"); break;
                        case "ldblquote": $toText .= $this->_html_entity_decode("&laquo;"); break;
                        case "rdblquote": $toText .= $this->_html_entity_decode("&raquo;"); break;
                        // Всё остальное добавим в текущий стек управляющих слов. Если у текущего
                        // слова нет параметров, то приравляем параметр true.
                        default:
                            $stack[$j][strtolower($word)] =  empty($param) ? true : $param;
                              $last_keyword = strtolower($word);
                        break;
                    }
                    // Если что-то требуется вывести в выходной поток, то выводим, если это требуется.
                    if ($this->rtf_isPlainText($stack[$j])) {
                      $document .= $toText;
                    }
                    // Если после контрольного слова идет пробел, то игнорируем его
                    if (' '===$this->file_data[$i+2]) {
                      $i++;
                    }

                }

                $i++;
            break;
            // Перед нами символ { - значит открывается новая подгруппа, поэтому мы должны завести
            // новый уровень стека с переносом значений с предыдущих уровней.
            case "{":
                array_push($stack, $stack[$j++]);
            break;
            // Закрывающаяся фигурная скобка, удаляем текущий уровень из стека. Группа закончилась.
            case "}":
                array_pop($stack);
                //$was_control_char = false; ????
                $j--;
            break;
            // Всякие ненужности отбрасываем.
            case '\0': case '\r': case '\f': case '\n':
              break;
            // Остальное, если требуется, отправляем на выход.
            default:
                if ($this->rtf_isPlainText($stack[$j]) && ''!=($c) && ord($c)!=13 && ord($c)!=10) {
                  $document .= $c;
                }
            break;
        }
    }
    //logVar($document);
    $this->tokens_form_file = $tokens;
    return $this->tokens_form_file;
  }

  /**
   *
   * Загружает массив токенов и их замен
   *
   * @param $replacement_map
   */
  public function setReplacements($replacement_map) {
    $this->replacements_map = $replacement_map;
    return true;
  }

  /**
   *
   * делает замены по файлу в памяти
   *
   */
  public function doReplacements() {
    if (empty($this->tokens_form_file) || empty($this->file_data)) {
      return false;
    }

     foreach ($this->replacements_map as $key=>$value) {
       if (is_scalar($value)) {
         // обычные замены
         $value = '{'.self::getUnicodeEntities($value, 'UTF-8').'}';
         $this->file_data = preg_replace("/\\\{.*?".$key.".*?\\\}/", $value, $this->file_data);
         if (!empty($this->tokens_form_file) && array_key_exists($key,$this->tokens_form_file)) {
           $this->file_data = str_replace($this->tokens_form_file[$key],$value,$this->file_data);
         }
       } else {
         // массивы
         foreach ($this->tokens_form_file as $token_name=>$repl) {
           if (strpos($token_name,$key)===0) {
             $tableRows = array();
             $columnHeaders = explode('|', strstr($token_name,'|'));
             for ($i=1;$i<count($columnHeaders);$i++){
               $columnHeaders[$i] = str_replace('\}','',$columnHeaders[$i]);
               $columnHeaders[$i] = str_replace('}{','',$columnHeaders[$i]);
               $tableRows[0][] = $this->_html_entity_decode($columnHeaders[$i]);
             }
             foreach ($value as $row) {
               $tableRows[] = $row;
             }
             $table = $this->add_tbl($tableRows);
             $this->file_data = str_replace($repl, $table, $this->file_data);
           }
         }
       }


     }
     return true;
  }

  /**
   *
   * сохраняет файл из памяти
   *
   * @param $filename
   */
  public function saveFile($filename) {
    if (!empty($this->file_data)) {
       return file_put_contents($filename,$this->file_data);
    } else {
      return false;
    }
  }

  protected function _html_entity_decode($entity) {
    return html_entity_decode($entity, ENT_COMPAT, 'UTF-8');
  }

}
