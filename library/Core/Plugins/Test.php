<?php

/**
 * Тестовый плагин для тестирования подсистемы евентов
 */
class Core_Plugins_Test
{
  public function __construct() {
   addListener('something_happened', array($this, 'onSomethinHappened'));
  }

  public function onSomethingHappened($param1, $param2) {
    logVar("Something happened!");
  }

}

