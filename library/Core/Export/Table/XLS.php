<?php

/**
 * Бекенд для вывода данных в XLS. Параметры бекенда:
 *   highlight_headers — выделять жирным заголовки столбцов [true]
 *   file — имя файла для сохранения результатов, вместо вывода их в поток [false]
 *
 * Для работы бекенда необходим PEAR пакет Spreadsheet_Excel_Writer
 */

class Core_Export_Table_XLS extends Core_Export_Table {

  protected $_data = array();
  protected $_xls;
  protected $_sheet;
  protected $_rowcnt = 0;
  protected $_formats = array();

  protected function __construct($options) {
    $options = array_merge(array('highlight_headers'=>true, 'file'=>false), $options);
    parent::__construct($options);
  }

  public function getExtension() {
    return 'xls';
  }

  public function initDownload($name=false) {
    if ($name) {
      putDownloadHeaders("{$name}.xls", "application/vnd.ms-excel");
    }
  }

  public function setHeaders($rows) {
    parent::setHeaders($rows);
    $this->_xls = new Spreadsheet_Excel_Writer($this->_options['file']?$this->_options['file']:'');
    $this->_xls->setVersion(8);
    $this->_sheet = $this->_xls->addWorksheet('Data');
    $this->_sheet->setInputEncoding('utf-8');
    $row = array();
    foreach ($rows as $v) {
      $row[]=$v;
    }
    $this->_dumpRow($row, array('bold'=>$this->_options['highlight_headers']));
  }

  protected function _dumpRow($row, $format=false) {
    $fmt = null;
    if ($format) {
      $format_key = json_encode($format);
      if (!isset($this->_formats[$format_key])) {
        $fmt = $this->_xls->addFormat($format);
        $this->_formats[$format_key] = $fmt;
      } else {
        $fmt = $this->_formats[$format_key];
      }
    }
    //$this->_sheet->writeRow($this->_rowcnt, $colcnt, $row, $fmt);
    $colcnt = 0;
    foreach ($row as $cell) {
      if (is_bool($cell)) {
        $cell = $cell?'Да':'Нет';
      }
      if (is_int($cell) || is_float($cell)) {
        $this->_sheet->writeNumber($this->_rowcnt, $colcnt, $cell, $fmt);
      } else {
        $this->_sheet->write($this->_rowcnt, $colcnt, $cell, $fmt);
      }
      $colcnt++;
    }
    $this->_rowcnt++;
  }

  protected function _addRow($row) {
    $this->_dumpRow($row);
  }

  public function finalize() {
    parent::finalize();
    $this->_xls->close();
    $this->_xls = null;
    $this->_sheet = null;
    $this->_formats = null;
  }
}
