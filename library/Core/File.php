<?php

class Core_File
{
  protected $_owner_id=null;
  protected $_path_template=null;
  protected $_container=null;
  protected $_data=array();

  // _container — класс, в который этот объект включается переменной
  // должен тдт иметь функцию _saveFile(array $file_data) для каллбека сохранения
  // состояния файла, или обрабатывать результат addFile().
  // Для того чтобы контейнер наследовался от Core_Mapper, и включал исполение файлов

  // id файлов тут не хранится, обеспечивается только прямая работа с файлами

  public function __construct($owner_id, $container, $path_template='<idhash>/<date>/<id>/')
  {
    $options = array('path_template'=>$path_template);
    $this->_owner_id=intval($owner_id);
    if (is_array($container)) {
      $options = array_merge($options, $container);
    } else {
      $this->_container=$container;
    }
    $this->_path_template = $options['path_template'];
    $this->_data = $options;
  }

  protected function _getOwnerId()
  {
    return $this->_owner_id;
  }

  protected function _getDate()
  {
    return isset($this->_data['date'])
             ? date('Y-m-d', toTimestamp($this->_data['date']))
             : 'unknown';
  }

  public function setDate($date) {
    $this->_data['date'] = $date;
  }

  public function getDate() {
    return $this->_getDate();
  }

  public function setOwnerId($id)
  {
    $this->_owner_id=$id;
  }

  private static function _parsePathTemplate($template, $data) {
    $owner_id = isset($data['owner_id'])?$data['owner_id']:null;
    $date = isset($data['date_parsed'])?$data['date_parsed']:null;
    //logVar($data, $template);
    if(!empty($owner_id)) {
      $idhash=sprintf('%02d', $owner_id % 100);
      $path=str_replace('<idhash>', $idhash, $template);
      $path=str_replace('<class>', $data['class'], $path);

      if (false!==strpos($path, '<date>') && !$date) {
        throw new Exception('Отсутствует дата у файла!');
      }
      $path=str_replace('<date>', $date, $path);
      $path=str_replace('<id>', $owner_id, $path);
    } else {
      $path = $template;
      $path=str_replace('<date>', $date, $path);
      $path=str_replace('<id>', $owner_id, $path);
    }
    return $path;
  }

  private function makeDataPath($filename = false)
  {
    $data = $this->_data;
    $data['owner_id'] = $this->_getOwnerId();
    $data['date_parsed'] = $this->_getDate();
    $template = $this->_path_template;
    if (!is_array($template)) {
      $template = array($template);
    }
    $prefix = APPLICATION_PATH.'/../data/';
    if ($filename) {
      foreach ($template as $tpl) {
        $path_raw = self::_parsePathTemplate($tpl, $data);
        $path = realpath($prefix.$path_raw);
        //logVar($path, "Checking existance $path_raw");
        if (file_exists("$path/$filename") && is_readable("$path/$filename")) {
          return $path;
        }
      }
    }
    $path = $prefix.self::_parsePathTemplate($template[0], $data);
    $realpath = realpath($path);
    return $realpath?$realpath:$path;
  }

  /*private function makeDataPath()
  {
    $id=$this->_getOwnerId();
    if(!empty($id)) {
      $idhash=sprintf('%02d', $id % 100);
      $path=str_replace('<idhash>', $idhash, $this->_path_template);
      $path=str_replace('<class>', $this->_data['class'], $path);

      if (false!==strpos($path, '<date>') && (!isset($this->_data['date']) || !$this->_data['date'])) {
        throw new Exception('Отсутствует дата у файла!');
      }
      $path=str_replace('<date>', $this->_getDate(), $path);
      $path=str_replace('<id>', $id, $path);
    } else {
      $path = $this->_path_template;
      $path=str_replace('<date>', $this->_getDate(), $path);
      $path=str_replace('<id>', $id, $path);
    }
    $path=APPLICATION_PATH.'/../data/'.$path.'/';
    $path_c=realpath($path);
    return $path_c?$path_c:$path;
  }*/

  public function getDataPath($filename = false)
  {
    $path=$this->makeDataPath($filename);

    makeSavePath($path);
    return realpath($path);
  }

  public function checkFile($filename)
  {
    if ( !file_exists($filename) || !is_readable($filename) )
    {
      //logVar($filename, 'Core_File::checkFile');
      throw new Exception('Не могу прочесть файл');
    }
    if (!is_file($filename))
    {
      //logVar($filename, 'Core_File::checkFile');
      throw new Exception('Тут подсунули что-то не то');
    }
    return $this;
  }

  public function addFile($filename, $suggested_name=false, $extra_data=null, $keep=false)
  {
    $id=$this->_getOwnerId();
    $this->checkFile($filename);
    if (empty($suggested_name))
    {
      $suggested_name=$filename;
    }
    $fname=sanitizeFileName(basename(trim($suggested_name)));
    $store=$this->getDataPath();

    $name=suggestUniqueName($fname, $store);
    $size = @filesize($filename);
    $hash=Core_Crypto_Hash::File($filename);
    if (false===$hash)
    {
      throw new Exception('Не получилось подсчитать контрольную сумму файла '.basename($filename),405);
    }
    $full_name=$store.'/'.$name;
    $result=false;
    if ($keep)
    {
      $result=@copy($filename, $full_name);
    }
    else
    {
      $result=@rename($filename, $full_name);
    }
    if ( !$result )
    {
      throw new Exception('Не могу переместить файл');
    }
    $data=array(
      'owner_id'=>$id,
      'filename'=>$name,
      'data'=>$extra_data,
      'hash'=>$hash,
      'size'=>$size,
    );
    if ( !empty($this->_container) )
    {
      return $this->_container->_saveFile($data);
    }
    return $data;
  }

  public function getFilePath($filename)
  {
    return $this->makeDataPath($filename).'/'.$filename;
  }

  public function download($filename, $extract_p7z=false, $dlparams=null)
  {
    require_once 'Util/resume.php';
    if ('/' != $filename[0])
    {

      $filename = $this->getFilePath($filename);
    }
    new getresumable($filename, false, false, $extract_p7z, $dlparams);
    exit;
  }

  public function delete($filename)
  {
    return @unlink($this->getFilePath($filename));
  }

  public function getFileSize($filename)
  {
    $path=$this->makeDataPath($filename).'/'.$filename;
    if ( file_exists($path) && is_file($path) && is_readable($path) )
    {
      return @filesize($path);
    }
    return false;
  }

  public static function cleanFilename($name) {
    return preg_replace('@\[[0-9]*\]@', '', $name);
  }
}

