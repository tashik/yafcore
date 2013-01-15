<?php

class Core_Template
{
  // Уведомление о регистрации клиента потребителя услуг
  const REGISTER_CLIENT = 'REGISTER_CLIENT';

  // Восстановление пароля для входа в личный кабинет
  const RESTORE_PASSWORD = 'RESTORE_PASSWORD';

  // Уведомление о принятии заявки на регистрацию оператора ЭДО
  const REGISTER_PARTPOINT_RECEIVED = 'REGISTER_PARTPOINT_RECEIVED';
  // Уведомление об одобрении заявки на регистрацию оператора ЭДО
  const REGISTER_OPERATOR_ACCEPTED = 'REGISTER_OPERATOR_ACCEPTED';
  // Уведомление об отклонении заявки на регистрацию оператора ЭДО
  const REGISTER_OPERATOR_REJECTED = 'REGISTER_OPERATOR_REJECTED';

  // Экранное уведомление о неустойке при отмене заявки
  const PENALTY_MESSAGE_SCREEN = 'PENALTY_MESSAGE_SCREEN';

  // Уведомление о подтверждении заявки выбора тарифа
  const TARIFF_APPROVED = 'TARIFF_APPROVED';
  // Уведомление о подтверждении оплаты счета
  const PAYMENT_RECEIPT = 'PAYMENT_RECEIPT';
  // Уведомление о выставлении счета
  const BILL_DONE = 'BILL_DONE';

  // Сообщение контрагенту
  const CONTRAGENT_MESSAGE = 'CONTRAGENT_MESSAGE';

  // Извещение о подписании документа
  const DOC_SIGN_MESSAGE = 'DOC_SIGN_MESSAGE';

  //Текст, показывающийся в диалоге подписания.
  const DOC_SIGN_TEXT = 'DOC_SIGN_TEXT';

  //Текст, показывающийся в диалоге "удовлетворить запрос на уточнение".
  const CORRECTION_REQUEST_TEXT = 'CORRECTION_REQUEST_TEXT';


  //Уведомление "Подтверждение даты поступления документа"
  const DOC_RECEPTION_CONFIRMATION_MESSAGE = 'DOC_RECEPTION_CONFIRMATION_MESSAGE';

  //Уведомление "Подтверждение даты отправки документа"
  const DOC_SEND_CONFIRMATION_MESSAGE = 'DOC_SEND_CONFIRMATION_MESSAGE';

  //Уведомление о получении документа
  const DOC_RECEIVED_CONFIRMATION_MESSAGE = 'DOC_RECEIVED_CONFIRMATION_MESSAGE';

  //Подтверждение оператора о получении извещения о получении документа
  const DOC_CONFIRMATION_MESSAGE = 'DOC_CONFIRMATION_MESSAGE';

  const DOC_REJECTION_MESSAGE = 'DOC_REJECTION_MESSAGE';

  // Приглашение в систему
  const INVITATION_MESSAGE = 'INVITATION_MESSAGE';

  //'Извещение о получении подтверждения даты получения от оператора',     // направляется продавцом оператору 2.7
  const DOC_RECEPTION_CONFIRMED_MESSAGE = 'DOC_RECEPTION_CONFIRMED_MESSAGE';

  //'Извещение о получении подтверждения даты отправки от оператора',      // направляется покупателем оператору 2.12
  const DOC_SEND_CONFIRMED_MESSAGE = 'DOC_SEND_CONFIRMED_MESSAGE';

  //'Извещение о получении документа',                                     // направляется покупателем продавцу через оператора 2.9
  const DOC_RECEIVED_MESSAGE = 'DOC_RECEIVED_MESSAGE';

  //'Подтверждение покупателя о получении подтверждения оператора',        // направляется покупателем оператору 2.13
  const DOC_CONFIRMED_MESSAGE = 'DOC_CONFIRMED_MESSAGE';

  //'Уведомление об уточнении документа',
  const DOC_CORRECTION_REQUESTED_MESSAGE = 'DOC_CORRECTION_REQUESTED_MESSAGE';

  //'Подтверждение продавца о получении уведомления об уточнении документа',
  const DOC_CORRECTION_CONFIRMED_MESSAGE = 'DOC_CORRECTION_CONFIRMED_MESSAGE';

  const INVOICE_PRINTFORM = 'INVOICE_PRINTFORM'; // печатная форма счета-фактуры

  const INVOICE_PRINTFORM_PDF = 'INVOICE_PRINTFORM_PDF'; // pdf форма счета-фактуры



  public static $tariff_approved = array(
      20 => 'TARIFF_APPROVED_MASTER',
      21 => 'TARIFF_APPROVED_SPEC',
      22 => 'TARIFF_APPROVED_PROF');

  const TEMPLATE_TYPE_PLAIN=1;
  const TEMPLATE_TYPE_RTF=2;
  const TEMPLATE_TYPE_HTML=3;

  protected $_data; // данные для подстановки
  private $_vcnt;

  /**
   * Процессит шаблон, заполняя его значениями
   * @param string $template шаблон
   * @param array $data значения, которые следует заполнить в шаблоне. Формат
   * array('ключ'=>'значение'), соответственно в шаблоне все вхождения «{ключ}» будут
   * заменены на «значение»
   * @param array $opts Опции замены:
   *   type: тип шаблона self::TEMPLATE_TYPE_PLAIN — плейнтекст,
   *                     self::TEMPLATE_TYPE_RTF ртф;
   *   math: если true, то парсится в расширенном режиме: поддерживаются теги вида {calc: «выражение»},
   *         где «выражение» — логическое или математическое выражение, может включать в себя:
   *           * другие теги, кроме «{calc:...}». Нескалярные типы (объекты, массивы, ресурсы), а также були и
   *             NULL’ы будут переданы в парсер с сохранением своего типа, посему следует учитывать это
   *             в используемых операциях.
   *             Остальные (строковые и числовые) теги будут подставлены как есть, не забывайте пробелы,
   *             если не надо чтобы они «склеивались».
   *           * Значения, и теги следует брать в кавычки, если требуется чтобы они были восприняты как строки.
   *             Переменные внутри строк «"» парсятся, внутри строк «'» нет, см. Model_Math за подробностями.
   *             Сложные теги (нескаляры, були, null’ы) передаются через механизм переменных, что следует учитывать,
   *             Поэтому будут вылезать строки вида «$var1» — значит надо использовать строки в двойных кавычках.
   *           * формат выражения в соответствии с возможностями Model_Math.
   * @return string заполненный шаблон
   */
  static public function process($template, $data, $opts=array())
  {
    $default_opts = array('type'=>self::TEMPLATE_TYPE_PLAIN, 'math' => false);
    if (!is_array($opts)) {
      $opts = array('type' => $opts);
    }
    $opts = array_merge($default_opts, $opts);
    $processors_map = array(
      self::TEMPLATE_TYPE_PLAIN => 'Plain',
      self::TEMPLATE_TYPE_HTML => 'HTML',
      self::TEMPLATE_TYPE_RTF => 'RTF',
    );
    $processor = "_process{$processors_map[$opts['type']]}";
    if (!$opts['math']) {
      // простой парсинг через str_replace
      return self::$processor($template, $data);
    }
    $p = new self($data);
    // Прелоадим поддержку таблиц
    class_exists('Core_Template_Renderer_Table');
    $processor = "{$processor}Complex";
    return $p->$processor($template);
  }

  protected function __construct($data) {
    $this->_data = $data;
    //logVar($data, 'Значения для тегов');
  }

  protected function _preProcessTag($template, $data, $math=false) {
    $variables = array();
    if (!$this->_vcnt) {
      $this->_vcnt = 1;
    }
    foreach ($data as $key => $value) {
      $varname = false;
      if (is_array($value)) {
        $t = array();
        foreach ($value as $k=>$v) {
          $t["{$key}.{$k}"] = $v;
        }
        list($template, $vars) = $this->_preProcessTag($template, $t, $math);
        $variables = array_merge($variables, $vars);
      }
      if (!is_scalar($value) || is_null($value) || is_bool($value)) {
        $varname = "\$var{$this->_vcnt}";
        $variables[$varname] = $value;
        $value = $varname;
      } elseif (is_string($value) && $math) {
        //$value = str_replace(array('\\', '"', "'"), array('\\\\', '\\"', "\\'"), $value);
        $value = addslashes($value);
      }

      $template = str_ireplace("{{$key}}", $value, $template, $count);
      if ($varname) {
        if ($count) {
          $this->_vcnt++;
        } else {
          unset($variables[$varname]);
        }
      }
    }
    return array($template, $variables);
  }

  // Парсер тегов
  public function _parseTag($tag) {
    //logVar($tag, 'Процессим теги');
    // заменяем вложенные теги
    $this->_vcnt = null;
    $math = false;
    if (preg_match('@^\{calc:(.+)\}$@', $tag, $m)){
      $tag = $m[1];
      $math = true;
    } elseif (preg_match('@^\{\[(.+)\]\}$@', $tag, $m)){
      $tag = $m[1];
      $math = true;
    }
    list($tag2, $vars) = $this->_preProcessTag($tag, $this->_data, $math);
    if ($math) {
      //try {
        $tag3 = Model_Math::calc($tag2, $vars);
      //} catch (Exception $e) {
      //  logVar($this->_data, "Ошибка обработки: $tag -> $tag2 -> $tag3");
      //  throw $e;
      //}
      if (is_bool($tag3)) {
        $tag3 = $tag3?'Да':'Нет';
      }
    } else {
      $tag3 = $tag2;
    }
    //logStr("$tag -> $tag2 -> $tag3");
    if (is_object($tag3) && $tag3 instanceof Core_Template_Renderer) {
      return $tag3;
    }
    return "$tag3";
  }

  protected function _processRTFComplex($template) {
    return Core_RtfTemplate::parseTemplate($template, array($this, '_parseTag'));
  }

  protected function _processPlainComplex($template) {
    $out = '';
    $in_tag = 0;
    $tag = '';
    for ($i=0; $i<strlen($template); $i++) {
      $ch = $template[$i];
      if (!$in_tag) {
        if ('{'!=$ch) {
          $out .= $ch;
        } else {
          $in_tag++;
          $tag .= $ch;
        }
      } else {
        $tag .= $ch;
        if ('{'==$ch) {
          $in_tag++;
        } elseif ('}'==$ch) {
          $in_tag--;
          if (!$in_tag) {
            $out .= $this->_parseTag($tag);
            $tag = '';
          }
        }
      }
    }
    return $out;
  }

  protected function _processHtmlComplex($template) {
    $out = '';
    $in_tag = 0;
    $tag = '';
    for ($i=0; $i<strlen($template); $i++) {
      $ch = $template[$i];
      if (!$in_tag) {
        if ('{'!=$ch) {
          $out .= $ch;
        } else {
          $in_tag++;
          $tag .= $ch;
        }
      } else {
        $tag .= $ch;
        if ('{'==$ch) {
          $in_tag++;
        } elseif ('}'==$ch) {
          $in_tag--;
          if (!$in_tag) {
            $tagContent = $this->_parseTag($tag);
            if(is_object($tagContent) && $tagContent instanceof Core_Template_Renderer_Table) {
              $tagContent = $tagContent->renderHtml();
            }
            $out .= $tagContent;
            $tag = '';
          }
        }
      }
    }
    return $out;
  }

  protected static function _processPlain($template, $data) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        $value = @join('|', $value);
      } elseif (is_object($value)) {
        if (method_exists($value, '__toString')) {
          $value = $value->__toString();
        } else {
          continue;
        }
      }
      $template = str_ireplace('{' . $key . '}', $value, $template);
    }
    return $template;
  }

  protected static function _processRtf($template, $data) {
    return Core_RtfTemplate::processRtfTemplate($template, $data);
  }

  static public function findTemplate($template_id) {
    $db=getDbInstance();
    $db->setFetchMode(Zend_Db::FETCH_ASSOC);

    $fieldname = 'code';

    if (is_numeric($template_id)) {
      $fieldname = 'id';
    }
    $select = $db->select()->from('list_templates');
    $select->where($fieldname . ' = ?', $template_id);
    $stmt = $db->query($select);
    $template = $stmt->fetchAll();
    if (empty($template)) {
      throw new Exception("Нет шаблона для сообщения $template_id");
    }
    list($k, $template) = each($template);
    return $template;
  }

  static public function dbProcess($template_id, $data = array(), $opts=array()) {
    $template = self::findTemplate($template_id);
    $title = $template['name'];
    if (!$data) {
      $data = array();
    }
    $data['site_url'] = getConfigValue('general->site_url', 'http://www.roseltorg.ru');
    $format = (isset($template['format']))?$template['format']:self::TEMPLATE_TYPE_PLAIN;

    if (self::TEMPLATE_TYPE_RTF==$format) {
      $template = self::_processRtf($template['content'], $data);
    } else {
      $template = preg_replace('@<br\s?/>@u', "\n", $template['content']);
    }
    foreach ($data as $key => $value) {
      $title = str_ireplace('%' . $key . '%', $value, $title);
      if (self::TEMPLATE_TYPE_PLAIN==$format) {
        $template = str_ireplace('%' . $key . '%', $value, $template);
      }
    }
    return array('subject' => $title, 'message' => $template, 'format' => $format);
  }

  public static function generateRTF($template, $keep_newlines=true, $page_orientation='portrait') {
    $rtf = new Core_Report_RTF(APPLICATION_PATH . '/../library/Util/rtf_config.inc', $page_orientation);
    if (!$keep_newlines) {
      $txt = str_replace('<br />' ,'', $template);
    } else {
      $txt = nl2br($template);
      $txt = str_replace('<br />', '<br>', $txt);
    }
    $rtf->parce_HTML(iconv('UTF-8', 'WINDOWS-1251', $txt));
    return $rtf->get_rtf();
  }
}
