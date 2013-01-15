<?php
/**
 * Класс-хелпер для "размножения" узла
 */
class Core_XmlRepeat extends Core_XmlParser
{
  protected $helpersMap = array(
    'choice' => 'Core_XmlChoice',
    'repeat' => 'Core_XmlRepeat',
    'optional' => 'Core_XmlOptional',
  );

  /**
   * "Размножает" узел
   * @param SimpleXMLElement $xml
   */
  public function repeat($xml) {
    $repeatPlaceHolder = (string)$xml["repeatPlaceholder"];
    $allowZeroRepeats  = (int)$xml["allowZeroRepeats"];
    $repeatPlaceHolderValue = $this->parsePlaceholder($repeatPlaceHolder, true);
    $nodeForRepeat = $xml->children()->{0};

    $repeatPlaceHolder = $this->clearHelperSymbolsFromPlaceholder($repeatPlaceHolder);
    $this->replaceRepeatingPlaceholders($nodeForRepeat,$repeatPlaceHolder);
    $this->removeAllChilds($xml);
    if ((!is_array($repeatPlaceHolderValue) || !count($repeatPlaceHolderValue)) && $allowZeroRepeats) {
      return;
    }
    $this->doRepeat($repeatPlaceHolderValue, $nodeForRepeat, $xml);
  }

  /**
   * @param $repeatPlaceHolderValue
   * @param $nodeForRepeat
   * @param SimpleXMLElement $xml
   * @throws Model_Exception_ParsePlaceholderException
   */
  protected  function doRepeat($repeatPlaceHolderValue, $nodeForRepeat, $xml) {
    if ($this->isIterable($repeatPlaceHolderValue)) {
      foreach ($repeatPlaceHolderValue as $value) {
        $this->data['repeat'] = $value;
        $append = dom_import_simplexml($nodeForRepeat);
        $nodeForInsert = simplexml_import_dom($append->cloneNode(true));
        $nodeForInsert = $this->parse($nodeForInsert, false);
        $this->insertChild($xml, $nodeForInsert);

        unset($this->data['repeat']);
      }
    } else {
      $this->sendErrorMailOrLogMessage("Неверное значение распарсенного плейсхолдера - ожидали iterable, получили - ". var_export($repeatPlaceHolderValue, true));
      throw new Model_Exception_ParsePlaceholderException("Неверное значение распарсенного плейсхолдера - ожидали iterable, получили что-то другое");

    }
  }

  /**
   * @param SimpleXMLElement $xml
   * @param $repeatingPlaceHolder
   */
  private function replaceRepeatingPlaceholders($xml, $repeatingPlaceHolder) {
    $this->replaceNodeValue($xml, $repeatingPlaceHolder);
    foreach ($xml->attributes() as $name => $attr) {
      $attrStr = (string)$attr;
      if ($this->isRepeatingPlaceholder($attrStr,$repeatingPlaceHolder) ) {
        $replacement = $this->replaceRepeatingPlaceholder($repeatingPlaceHolder,$attrStr);
        $xml->attributes()->$name = $replacement;
      }
    }

    foreach ($xml->children() as $childXml) {
      $this->replaceRepeatingPlaceholders($childXml, $repeatingPlaceHolder);
    }



  }

  /**
   * @param SimpleXMLElement $xml
   * @param String $repeatingPlaceHolder
   */
  protected  function replaceNodeValue($xml, $repeatingPlaceHolder) {
    if (!count($xml->children())) {
      $nodeValue = (string)$xml;
      if ($this->isRepeatingPlaceholder($nodeValue, $repeatingPlaceHolder)) {
        $newNodeValue = $this->replaceRepeatingPlaceholder($repeatingPlaceHolder, $nodeValue);
        $xml[0] = $newNodeValue;
      }
    }
  }

  /**
   * @param $repeatingPlaceHolder
   * @param $nodeValue
   * @return mixed
   */
  protected function replaceRepeatingPlaceholder($repeatingPlaceHolder, $nodeValue) {
    return str_replace($repeatingPlaceHolder, "repeat", $nodeValue);
  }

  /**
   * @param $nodeValue
   * @param $repeatingPlaceHolder
   * @return bool
   */
  protected function isRepeatingPlaceholder($nodeValue, $repeatingPlaceHolder) {
    return $this->isPlaceholder($nodeValue) && false !== strpos($nodeValue, $repeatingPlaceHolder);
  }
}
