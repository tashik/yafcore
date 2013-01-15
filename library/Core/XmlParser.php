<?php
/**
 * Базовый класс, реализующий функционал шаблонизатора Xml для формирования уведомлений
 */
class Core_XmlParser
{
  /**
   * Константы, хранящие имена вспомогательных узлов
   */
  const CHOICE_NODE_NAME='choice';
  const REPEAT_NODE_NAME='repeat';
  const OPTIONAL_NODE_NAME='optional';

  protected  $sendMailIfError = false;
  protected $developerMail = 'patrinat@gmail.com';

  /**
   * Массив маркеров, которыми могут быть помечены плейсхолдеры.
   * С помощью этих маркеров может быть принято решение о том, парсить ли этот плейсхолдер или не трогать его.
   * @var array
   */
  protected $markers = array('#','@', '$');

  /**
   * Массив маркеров. Плейсхолдеры, помеченные маркерами из массива парситься не будут.
   * Если массив пустой, то парсим все плейсхолдеры.
   * @var array
   */
  protected $excludeMarkers = array();

  /**
   * Данные, с помощью которых заполняются плейсхолдеры. Имеет вид
   * 'key' => $value, где key - корневой ключ плейсхолдера.
   * Например, если в xml, есть плейсхолдер %doc.author.name%, то data должен содержать элемент с ключом 'doc'
   * @var array
   */
  protected $data;

  /**
   * Карта, классов, которые учавствуют в разборе узлов-помощников
   * @var array
   */
  protected $helpersMap = array( );


  /**
   * @param $data
   * @param $excludeMarkers
   */
  function __construct($data, $excludeMarkers=array()) {
    $this->data = $data;
    $this->excludeMarkers = $excludeMarkers;
  }

  /**
   * Парсит xml, заполняя плейсхолдеры данными.
   * @param SimpleXMLElement $xml
   * @param bool $returnString
   * @param bool $needHelpersProcess - нужно ли работать с вспомогательными узлами
   * @return mixed
   */
  public function parse($xml, $returnString=true, $fromRepeat=false) {
    $this->replaceNodeValueIfNeed($xml);
    $nodeName = $xml->getName();
    if ($this->helperNodeDetect($nodeName) ) {
      $helperClassName = $this->helpersMap[$nodeName];
      $helper = new $helperClassName($this->data);
      $helper->$nodeName($xml);
    } else if ($nodeName!=='helper') {
      $this->replacePlaceholdersInAttrs($xml);
    }
    foreach ($xml->children() as $childXml) {
      $this->parse($childXml);
    }

    if (!$returnString) {
      return $xml;
    }

    $xmlStr = $xml->asXml();

    return $xmlStr;
  }


  /**
   * @param SimpleXMLElement $xml
   */
  protected function replaceNodeValueIfNeed($xml) {
    if (!count($xml->children())) {
      $nodeValue = (string)$xml;
      if ($this->isPlaceholder($nodeValue)) {
        $newNodeValue = $this->parsePlaceholder($nodeValue);
        $xml[0] = $newNodeValue;
      }
    }
  }

  /**
   * @param String $nodeName
   * @return bool
   */
  protected function helperNodeDetect($nodeName) {
    if (self::CHOICE_NODE_NAME === $nodeName ||
      self::REPEAT_NODE_NAME === $nodeName ||
      self::OPTIONAL_NODE_NAME === $nodeName) {
      return true;
    }
    return false;
  }

  /**
   * Заменяет все значения плейсхолдерных атрибутов на реальные данные
   * @param $xml
   */
  protected function replacePlaceholdersInAttrs($xml) {
    foreach ($xml->attributes() as $name => $attr) {
      $attrStr = (string)$attr;
      if ($this->isPlaceholder($attrStr)) {
        $replacement = $this->parsePlaceholder($attrStr);
        $xml->attributes()->$name = $replacement;
      }
    }
  }


  /**
   * Получение значения плейсхолдера.
   * @param $placeholder
   * @param bool $allowReturnIterable
   * @return object|string
   * @throws Model_Exception_PlaceholderNotFoundException
   * @throws Model_Exception_ParsePlaceholderException
   */
  public   function parsePlaceholder($placeholder, $allowReturnIterable=false) {
    $required = false;
    if (false!==strpos($placeholder,'!')) {
      $required = true;
    }
    $format = '';
    if (false!==strpos($placeholder,':')) {
      $placeholderFormatInfo = explode(":",$placeholder);
      $format = $this->clearHelperSymbolsFromPlaceholder($placeholderFormatInfo[1]);
      $placeholder = $placeholderFormatInfo[0];
    }
    $placeholder = $this->clearHelperSymbolsFromPlaceholder($placeholder);
    $placeholderArray = explode(".", $placeholder);
    $value = $this->fillPlaceholder($placeholderArray, $this->data);

    if ($required &&  !$value) {
      $this->sendErrorMailOrLogMessage("Плейсхолдер $placeholder не найден!");
      throw new Model_Exception_PlaceholderNotFoundException("Плейсхолдер $placeholder не найден !");
    }

    if ($this->isIterable($value)) {
      if ( $allowReturnIterable) {
        return $value;
      }
      $this->sendErrorMailOrLogMessage("Неверное значение распарсенного плейсхолдера $placeholder - ожидали скаляр, получили - ". var_export($value, true));
      throw new Model_Exception_ParsePlaceholderException("Неверное значение распарсенного плейсхолдера $placeholder - ожидали скаляр");
    } else  if(is_object($value)) {
      $this->sendErrorMailOrLogMessage("Неверное значение распарсенного плейсхолдера $placeholder - ожидали скаляр, получили - ". var_export($value, true));
      throw new Model_Exception_ParsePlaceholderException("Неверное значение распарсенного плейсхолдера $placeholder - ожидали скаляр");

    }

    if ($format==='date' && $value) {
      return date("d.m.Y",strtotime($value));
    }

    if ($format==='time' && $value) {
      return date("H.i.s",strtotime($value));
    }
    return $value;

  }

  /**
   * @param String $placeholder
   * @return mixed
   */
  public function clearHelperSymbolsFromPlaceholder($placeholder) {
    $placeholder =  str_replace(array('%', '!'), "", $placeholder);
    $placeholder = str_replace($this->markers, "", $placeholder);
    return $placeholder;
  }

  /**
   * Рекурсивное нахождение значения плейсхолдера
   * @param $placeholderArray - массив, к котором каждый элемент, это часть сложного плейсхолдера.
   *  Например, если плейсхолдер был doc.author.name, то массив будет array('doc', 'author', 'name')
   * @param Array $data - данные для заполнения плейсхолдера
   * @return object|string
   * @throws Model_Exception_ParsePlaceholderException
   */
  protected  function fillPlaceholder($placeholderArray, $data) {
    $modelKey = $placeholderArray[0];
    $model = $data[$modelKey];

    if (count($placeholderArray) > 1 && !$model) {
      return $model;
    }

    if (count($placeholderArray) > 1 && !is_object($model)) {
      $this->sendErrorMailOrLogMessage("Неверное значение распарсенного плейсхолдера ".implode('.',$placeholderArray). " - ожидали объект, получили - ". var_export($model, true));
      throw new Model_Exception_ParsePlaceholderException("Неверное значение распарсенного плейсхолдера ".implode('.',$placeholderArray). " - ожидали объект, получили что-то другое ");

    }

    if (1==count($placeholderArray)) {
      return $model;
    }

    $newPlaceholderArray = array_slice($placeholderArray,1);
    $newModelKey = $newPlaceholderArray[0];
    $getter = "get".ucfirst($newModelKey);
    $newModel = $model->$getter();

    if (!$newModel) {
      return $newModel;
    }

    return $this->fillPlaceholder($newPlaceholderArray, array($newModelKey=>$newModel));
  }

  /**
   * @param SimpleXMLElement $xml
   */
  public  function removeAllChilds($xml) {
    $domArray = array();
    foreach ($xml->children() as $child) {
      $domArray[] = dom_import_simplexml($child);
    }
    foreach ($domArray as $dom) {
      $dom->parentNode->removeChild($dom);
    }
  }

  /**
   * @param SimpleXMLElement $xml
   * @param SimpleXMLElement $choice
   */
  public  function insertChild($xml, $choice) {
    $xmlDom = dom_import_simplexml($xml);
    $xmlDom->appendChild(dom_import_simplexml($choice));
  }


  /**
   * @param String $attrStr
   * @return bool
   */
  protected  function isPlaceholder($attrStr) {
    if (false===strpos($attrStr, '%')) {
      return false;
    }

    foreach($this->excludeMarkers as $marker) {
      if (false!==strpos($attrStr, $marker)) {
        return false;
      }
    }
    return true;

  }

  protected  function isIterable($var) {
    return ((is_array($var) && count($var) ) || ($var instanceof Traversable && iterator_count($var)));
  }


  /**
   * Отправляет разработчику email С ошибкой, если это включено в настройках
   * Если выключено, то просто логирует сообщение.
   * @param String $message
   */
  protected  function sendErrorMailOrLogMessage($message) {
//    $message.= ' Данные, по которым заполняем  - '. var_export($this->data,true);
    $message.= ' Данные, по которым заполняем  - '. print_r($this->data,true);
    if ($this->sendMailIfError) {
      $mail = new Model_Mail();
      $mail->setTo($this->developerMail);
      $mail->setMessage(array(
        'subject' => 'Невозможно сформировать xml для документа',
        'message' => ($message)
      ));
      $mail->send();
    } else {
      Core_Debug::log($message,Zend_Log::ERR);
    }
  }






}
