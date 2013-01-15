<?php
/**
 * Финальный класс, реализующий функционал шаблонизатора xml для формирования уведомлений
 */
class Core_XmlTemplate extends Core_XmlParser
{
  protected $helpersMap = array(
    'choice' => 'Core_XmlChoice',
    'repeat' => 'Core_XmlRepeat',
    'optional' => 'Core_XmlOptional',
  );

  public static function parseFile($fileName, array $variables, array $excludeMarkers = array()) {
    $xml  = simplexml_load_file($fileName);
    if (!$xml) {
      return null;
    }
    $processor = new Core_XmlTemplate($variables,$excludeMarkers);
    return $processor->process($xml);
  }

  /**
   * Шаблонизация Xml.
   * @param SimpleXMLElement $xml
   * @return mixed
   */
  public function process($xml) {
    $xmlStr = $this->parse($xml);
    $xmlWithoutHelperNodes = $this->removeHelperNodes($xmlStr);
    $xml = $this->changeEncoding($xmlWithoutHelperNodes);
    $xml = preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/sm', "\n", $xml);
    return $xml;
  }

  private  function changeEncoding($xmlWithoutHelperNodes) {
    $xml = iconv('UTF-8', 'windows-1251', $xmlWithoutHelperNodes);
    $xml = str_replace('UTF-8', 'windows-1251', $xml);
    return $xml;
  }


  /**
   * @param String $xml
   * @return mixed
   */
  private function removeHelperNodes($xml) {
    $replacement = array('@<choice.*>@Usi',
      '@</choice>@Usi',
      '@<repeat.*>@Usi',
      '@</repeat>@Usi',
      '@<optiona.*>@Usi',
      '@</optional>@Usi',
      '@<helper.*>@Usi',
      '@</helper>@Usi',
      '@<!--.*-->@Usi',
    );
    $xml =  preg_replace($replacement,"",$xml);

    return $xml;
  }

  /**
   * @param String $xml
   * @return mixed
   */
  private function replaceEncoding($xml) {
    return str_replace('encoding="UTF-8"', 'encoding = "windows-1251"', $xml);
  }

}
