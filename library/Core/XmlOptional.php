<?php
class Core_XmlOptional extends Core_XmlParser
{
  /**
   * @param SimpleXMLElement $xml
   */
  public function optional($xml) {
    $optionalPlaceholder = (string)$xml["optionalPlaceholder"];
    $optionalPlaceholderValue = $this->parsePlaceholder($optionalPlaceholder, true);
    if (!$optionalPlaceholderValue) {
      $this->removeAllChilds($xml);
    }
  }
}
