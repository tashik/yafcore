<?php
class Core_Template_Renderer_Table extends Core_Template_Renderer {
  protected $_data;
  protected $_meta;

  public function __construct($headers, $data) {
    $this->_data = array();
    $this->_meta = array();
    $t = array();
    foreach ($headers as $pseudo=>$meta) {
      if (!is_array($meta)) {
        $meta = array('name'=>$meta);
      }
      $t[] = isset($meta['name'])?$meta['name']:' ';
      $this->_meta[] = $meta;
    }
    $this->_data[] = $t;
    if (is_object($data) && method_exists($data, 'toArray')) {
      $data = $data->toArray();
    }
    foreach ($data as $row) {
      $t = array();
      foreach ($headers as $pseudo=>$meta) {
        $t[] = isset($row[$pseudo])?$row[$pseudo]:'';
      }
      $this->_data[] = $t;
    }
  }

  public function renderRtf() {
    /* @var $templater Core_RtfTemplate */
    $templater = $this->getTemplater();
    $widths = array();
    $unknown_width = 0;
    $sum_width = 0;
    foreach ($this->_meta as $meta) {
      if (isset($meta['width'])) {
        $w = filterPrice($meta['width']);
        $sum_width += $w;
        $widths[] = $w;
      } else {
        $widths[] = null;
        $unknown_width++;
      }
    }
    if ($sum_width>100) {
      // нахуячили с размерами
      //throw new ResponseException("Сумма ширин столбцов таблицы более 100%, невозможно отобразить таблицу");
    }
    if ($unknown_width>0) {
      foreach ($widths as $k=>$v) {
        if (is_null($v)) {
          $widths[$k] = (100-$sum_width)/$unknown_width;
        }
      }
    }
    //logVar($widths, 'widths');
    return $templater->add_tbl($this->_data, 1, 1, 1, 0, $widths);
  }

  public function renderText() {
    $t = array();
    foreach ($this->_data as $row) {
      $t[] = join('|', $row);
    }
    return join("\n", $t);
  }

  public function renderHtml() {
    $meta = $this->_meta;
    $data = $this->_data;


    $html = '<table width="100%" class="invoicePreview-body" cellspacing="0" cellpadding="0">';
    $aligns = array('c'=>'center', 'l'=>'left', 'r'=>'right');
    foreach($data as $trow) {
      $html .= '<tr>';
      for($i=0; $i<count($trow); $i++) {
        $tcell = $trow[$i];
        if (!is_array($tcell)) {
          $tcell = array('v'=>$tcell);
        }
        if ( (isset($tcell['ehmerge']) && $tcell['ehmerge']==1) || (isset($tcell['evmerge']) && $tcell['evmerge']==1)) {
          continue;
        }
        $cell_width = $meta[$i]['width'];
        $html .= '<td width="'.$cell_width.'%"';

        if (isset($tcell['svmerge']) && $tcell['svmerge']==1) {
          $html .= ' rowspan="2"';
        }
        if (isset($tcell['shmerge']) && $tcell['shmerge']==1) {
          $html .= ' colspan="2"';
        }
        if (isset($tcell['align']) && !empty($tcell['align'])) {
          $html .= ' align="'.$aligns[$tcell['align']].'"';
        } else {
          if($i>0) {
            $html .= ' align="right"';
          }
        }
        $html .='>'.nl2br(isset($tcell['v'])?$tcell['v']:'').'</td>';
      }
      $html.='</tr>';
    }
    $html.= '</table>';
    logVar($html, 'table');
    return $html;
  }
}

function table($headers, $data) {
  return new Core_Template_Renderer_Table($headers, $data);
}
