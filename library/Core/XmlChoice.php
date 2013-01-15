<?php
/**
 * Класс-хелпер для выбора одного из нескольких узлов
 */
class Core_XmlChoice extends Core_XmlParser
{


  /**
   * Выбирает один узел из нескольких возможных.
   * @param SimpleXMLElement $xml
   */
  public function choice($xml) {
    if (1==$xml->count()) {
      return; //если у нас в выборе 1 элемент, значит и выбирать ничего не нужно
    }
    list($attrNameExist, $placeholder) = $this->findPlaceholderAndAttrNameForChoice($xml);
    if (!$attrNameExist) {
      $this->sendErrorMailOrLogMessage("Недостаточно данных, чтобы сделать выбор. Xml:\n {$xml->asXML()}");
      throw new Model_Exception_DeficiencyDataException("Недостаточно данных, чтобы сделать выбор");
    }
    $choice = $this->findChoiceNode($xml, $attrNameExist, $placeholder);
    $this->removeAllChilds($xml);
    $this->insertChild($xml, $choice[0]);

  }

  /**
   * @param SimpleXMLElement $xml
   * @return array
   */
  protected  function findPlaceholderAndAttrNameForChoice($xml) {
    $choiceAttrInfo = (string)$xml["choiceAttrInfo"];
    $attrInfoArray = explode(";", $choiceAttrInfo);
    $attrNameExist = "";
    foreach ($attrInfoArray as $nameAndValue) {
      $nameAndValueArray = explode(":", $nameAndValue, 2);
      $name = $nameAndValueArray[0];
      $placeHolder = $nameAndValueArray[1];
      $value = $this->parsePlaceholder($placeHolder);
      if ($value) {
        $attrNameExist = $name;
        break;
      }
    }
    return array($attrNameExist, $placeHolder);
  }

  /**
   * @param SimpleXMLElement $xml
   * @param String $attrNameExist
   * @param String $placeHolder
   * @return mixed
   */
  private function findChoiceNode($xml, $attrNameExist, $placeHolder) {
    foreach ($xml->children() as $childName => $child) {
      $pathForRoot = "//*[@$attrNameExist=\"$placeHolder\"]";
      $childStr = $child->asXml();
      $childBuf = simplexml_load_string($childStr);
      $nodeWithChoiceAttrInRoot = $childBuf->xpath($pathForRoot);
      if (!empty($nodeWithChoiceAttrInRoot)) {
        $choice = $child;
        break;
      }
    }
    return $choice;
  }




}
