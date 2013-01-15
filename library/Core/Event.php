<?php

/**
 * Класс событий.
 */

class Core_Event {
  public $parameters = array();
  public $result = null;
  public $stop_processing = false;
  public $failed = false;

  /**
   * Все аргументы будут параметрами евента
   */
  public function __construct() {
    $this->parameters = func_get_args();
  }

  /**
   * Результат обработки события
   * @return mixed результат
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Установить результат обработки, и запретить дальнейшую обработку события
   * @param mixed $result
   * @return \Core_Event
   */
  public function setResult($result) {
    $this->result = $result;
    return $this->stopProcessing();
  }

  /**
   * Установить статус ошибки обработки, и запретить дальнейшую обработку события
   * @return \Core_Event
   */
  public function fail() {
    $this->failed = true;
    return $this->stopProcessing();
  }

  /**
   * Получить статус обработки
   * @return bool true если была ошибка, false если нет
   */
  public function isFailed() {
    return $this->failed;
  }

  /**
   * Получить параметры события
   * @return array массив параметров
   */
  public function getParameters() {
    return $this->parameters;
  }

  /**
   * запретить дальнейшую обработку события
   * @return \Core_Event
   */
  public function stopProcessing() {
    $this->stop_processing = true;
    return $this;
  }
}
