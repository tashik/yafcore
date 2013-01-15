<?php
require_once "Spreadsheet/Excel/Writer.php";

class Core_XLS
{
  public static function sendArray($array, $filename='file.xls') {
    $xls = new Spreadsheet_Excel_Writer();
    $xls->setVersion(8);
    $xls->send($filename);
    $sheet =& $xls->addWorksheet('Data');
    $sheet->setInputEncoding('utf-8');
    $rowcnt = 0;
    foreach ($array as $row) {
      $colcnt = 0;
      foreach ($row as $cell) {
        $sheet->write($rowcnt, $colcnt, $cell);
        $colcnt++;
      }
      $rowcnt++;
    }
    $xls->close();
    exit;
  }
}
