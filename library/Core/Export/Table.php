<?php

/**
 * Класс для экспорта табличных данных клиентам
 *
 * Пример использования:
 *
 * $headers = array('Заголовок 1', 'Заголовок 2');
 * $writer = Core_Export_Table::factory(array('name'=>'data', 'headers'=>$headers));
 * ....
 * $row = array('Ячейка 1', 'Ячейка 2');
 * $writer->addRow($row);
 * ....
 * $writer->finalize();
 */

abstract class Core_Export_Table {
  protected $_options;
  protected $_rows;

  protected function __construct($options) {
    $this->_options = $options;
    if (isset($options['name'])) {
      $this->initDownload($options['name']);
    }
    if (isset($options['headers'])) {
      $this->setHeaders($options['headers']);
    }
  }

  /**
   * Инициализация экспорта
   * @param string|array $backend тип бекенда. На данный момент CSV или XLS. Если false то выберет
   *   бекенд автоматом (XLS если есть соответствующие классы, или CSV как фаллбек, если нету)
   *   Параметр опциональный, его можно не указывать
   * @param array $options настройки бекенда. Общие для всех бекендов:
   *   backend — тип бекенда (в случае если $options указывается первым параметром)
   *   name — автоматически вызвать initDownload(name)
   *   headers — автоматически вызвать setHeaders(headers)
   *   file — имя файла для сохранения результатов, вместо вывода их в поток [false]
   *   остальные настройки — см. соответствующие бекенды
   * @return Core_Export_Table бекенд вывода
   */
  public static function factory($backend=false, $options=array()) {
    if (is_array($backend)) {
      $options = $backend;
      $backend = isset($options['backend'])?$options['backend']:false;
    }
    if (false===$backend || 'XLS'===$backend) {
      @include_once "Spreadsheet/Excel/Writer.php";
    }
    if (false===$backend) {
      $backend = class_exists('Spreadsheet_Excel_Writer')?'XLS':'CSV';
    }
    $class = "Core_Export_Table_$backend";
    if (!class_exists($class)) {
      throw new Exception("Bad backend $backend");
    }
    return new $class($options);
  }

  /**
   * Вывести HTTP заголовки для начала скачивания файла
   * @param string $name имя файла (расширение НЕ указывать, оно будет установлено бекендом автоматически)
   */
  abstract public function initDownload($name=false);

  /**
   * Инициализация вывода, установка заголовков
   * @param array $rows массив с заголовками колонок. Может быть как простым, так и ассоциативным
   */
  public function setHeaders($rows) {
    if ($this->_rows) {
      throw new Exception("Headers already initialized");
    }
    $this->_rows = $rows;
  }

  abstract protected function _addRow($row);

  /**
   * Вывести строку данных
   * @param array $row массив с данными строки. Ключи массива должны соответствовать ключам заголовков при вызове initRows
   */
  public function addRow($row) {
    $raw_row = array();
    foreach ($this->_rows as $key=>$header) {
      $raw_row[] = $row[$key];
    }
    $this->_addRow($raw_row);
  }

  /**
   * Вывести таблицу
   * @param array $rows массив строк (см. addRow)
   */
  public function addTable($rows) {
    foreach ($rows as $row) {
      $this->addRow($row);
    }
    $this->finalize();
  }

  /**
   * Завершить вывод. Вызывать обязательно! XLS бекенд именно тут и делает всю работу!
   */
  public function finalize() {
  }

  /**
   * Получить предпочтительное расширение для файла
   */
  abstract public function getExtension();
}
