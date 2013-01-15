<?php
abstract class Core_Template_Renderer {
  protected $_templater = null;
  abstract public function renderRtf();
  abstract public function renderText();

  public function setTemplater($t) {
    $this->_templater = $t;
  }
  public function getTemplater() {
    return $this->_templater;
  }
}
