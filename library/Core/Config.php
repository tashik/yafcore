<?php

class Core_Config extends Zend_Config_Ini {
  public function __construct($filename, $section = null, $options = false) {
    if (!file_exists($filename) || !is_readable($filename)) {
      throw new Zend_Config_Exception("Config file $filename is not exists or not readable");
    }
    $file = $this->_processFile($filename);
    $fname = tempnam(sys_get_temp_dir(), 'config_');
    try {
      $file = join('', $file);
      $r = file_put_contents($fname, $file);
      if (false===$r || $r!=  strlen($file)) {
        throw new Zend_Config_Exception("Error saving temporary config $fname");
      }
      parent::__construct($fname, $section, $options);
    } catch (Exception $e) {
      unlink($fname);
      throw $e;
    }
    unlink($fname);
  }

  protected function _processFile($filename) {
    $file = file($filename);
    $basepath = pathinfo($filename);
    $basepath = isset($basepath['dirname'])?$basepath['dirname']:'./';
    if (false === $file) {
      throw new Zend_Config_Exception("Cannot read config file $filename");
    }
    $tmpconfig = array();
    foreach ($file as $line) {
      if (preg_match('@\s*[;#]include\((.+)\)@', $line, $matches)) {
        $tmpconfig[] = "; $line";
        $fname = $matches[1];
        $fname = preg_replace('@^[/]+@', '', $fname);
        $fname = preg_replace_callback('@\$\{([a-z0-9_]+)\}@i', array($this, '_parseVariable'), $fname);
        $fname = "$basepath/$fname";
        if (preg_match('@/$@', $fname) && !file_exists("$fname")) {
          continue;
        }
        $include_files = array();
        if (is_dir($fname)) {
          $dir = dir("$fname");
          while (false!==($f=$dir->read())) {
            if (preg_match('@^[^.#].*\.ini$@',$f)) {
              $include_files[] = "$fname/$f";
            }
          }
          $dir->close();
        } else {
          $include_files[] = "$fname";
        }
        sort($include_files);
        foreach ($include_files as $fname) {
          $tmpconfig[] = "; included file $fname\n";
          $tmpconfig = array_merge($tmpconfig, $this->_processFile($fname));
          $tmpconfig[] = "\n";
        }
      } else {
        $tmpconfig[] = $line;
      }
    }
    return $tmpconfig;
  }

  public function _parseVariable($matches) {
    if (defined($matches[1])) {
      return constant($matches[1]);
    }
    return '';
  }
}
