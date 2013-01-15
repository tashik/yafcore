<?php

/**
 * Бекенд для вывода данных в CSV. Параметры бекенда:
 *   excel — выводить так, чтобы Ексель без вопросов подхватывал как будто это XLS [true]
 *   encoding — кодировка вывода [windows-1251]
 *   auto_flush — автоматически флюшить вывод каждые 100 строк [true]
 *   numbers_as_text — выводить числа также как строки (т.е. с десятичным разделителем согласно локали),
 *                     иначе десятичный разделитель всегда точка [true]
 *   file — имя файла для сохранения результатов, вместо вывода их в поток [false]
 */
class Core_Export_Table_CSV extends Core_Export_Table {

  protected $_rowcnt = 0;
  protected $_file = null;

  protected function __construct($options) {
    $options = array_merge(array('excel'=>true, 'encoding'=>'windows-1251', 'numbers_as_text'=>true,
                                 'auto_flush'=>true, 'file'=>false),
                           $options);
    parent::__construct($options);
  }

  public function getExtension() {
    return $this->_options['excel']?'xls':'csv';
  }

  public function initDownload($name=false) {
    if ($name) {
      $ext = $this->getExtension();
      putDownloadHeaders("{$name}.{$ext}", "text/csv; ; charset={$this->_options['encoding']}");
    }
  }

  protected function _dumpRow($row) {
    if (strcasecmp('UTF-8', $this->_options['encoding'])) {
      $row = iconv('UTF-8', "{$this->_options['encoding']}//TRANSLIT", $row);
    }
    if ($this->_file) {
      fwrite($this->_file, $row);
    } else {
      echo $row;
    }
    if ( !(++$this->_rowcnt % 100) ) {
      ob_end_flush();
      flush();
    }
  }

  public function setHeaders($rows) {
    parent::setHeaders($rows);

    if ($this->_options['file']) {
      $this->_file = fopen($this->_options['file'], 'wb');
      if (!$this->_file) {
        throw new Exception("Невозможно открыть файл {$this->_options['file']} на запись");
      }
    }

    $result = array();
    foreach ($rows as $v) {
      $result[]=$v;
    }
    $this->_addRow($result);
  }

  protected function _addRow($row) {
    $row = prepareCSVRow($row, $this->_options['numbers_as_text']);
    $this->_dumpRow($row);
  }

  public function finalize() {
    parent::finalize();
    if ($this->_file) {
      fclose($this->_file);
      $this->_file = null;
    }
  }
}
