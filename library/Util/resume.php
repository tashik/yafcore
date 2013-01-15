<?php
//
// Pass a filename as an argument to the constructor to give a file out for downloading
//
// EXAMPLE:
//   new getresumable('C:/text.doc');
//
class getresumable {

  var $filename = null;
  var $fileext = null;
  var $mime_type = null;
  var $bufsize = 2048;
  var $seek_start = 0;
  var $seek_end = -1;
  var $extract_p7z = false;
  var $dlparams = null;

  public function __construct($file, $ext=false, $mime_type=false, $extract_p7z=false, $dlparams=null)
  {
    $this->extract_p7z=$extract_p7z;
    $this->dlparams=$dlparams?$dlparams:array();
    if (!isset($this->dlparams['format'])) {
      $this->dlparams['format']=null;
    }
    if (isFileReadable($file))
    {
      $this->filename = $file;
      if (empty($ext))
      {
        $this->fileext=basename($file);
      }
      $this->fileext = Core_File::cleanFilename($this->fileext);
      if ($extract_p7z)
      {
        $this->fileext=preg_replace('@((\[[0-9]+\])?\.p7z)+$@', '', $this->fileext);
      }
      if (empty($mime_type))
      {
        $p=pathinfo($this->fileext);
        $this->mime_type='application/octet-stream';
        $types=array(
          'jpg' =>'image/jpeg',
          'jpeg'=>'image/jpeg',
          'png' =>'image/png',
          'gif' =>'image/gif',
          'djvu'=>'image/vnd.djvu',
          'bmp' =>'image/bmp',
          'tiff'=>'image/tiff',
          'txt' =>'text/plain',
          'csv'=>'text/csv',
          'doc' =>'application/msword',
          'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          'odt' =>'application/vnd.oasis.opendocument.text',
          'pdf' =>'application/pdf',
          'zip' =>'application/zip',
          'rar' =>'application/x-rar',
          '7z'  =>'application/x-7z-compressed',
          'arj' =>'application/x-arj',
          'p7z' =>'application/pkcs7-mime',
        );
        $ext=strtolower(trim($p['extension']));
        if (isset($types[$ext]))
        {
          $this->mime_type=$types[$ext];
        }
      }
    }
    else
    {
      logVar($file, 'Failed attempt to load file');
      if (file_exists($file)) {
        throw new Exception("Permission Denied", 403);
      } else {
        throw new Exception("File not found", 404);
      }
    }

    $this->init();
    $this->download();
  }

  protected function init() {
    global $HTTP_SERVER_VARS;

    if ( 'json'!=$this->dlparams['format']
         && ! $this->extract_p7z
         && (isset($_SERVER['HTTP_RANGE']) || isset($HTTP_SERVER_VARS['HTTP_RANGE']))
       )
    {
      if (isset($HTTP_SERVER_VARS['HTTP_RANGE'])) $seek_range = substr($HTTP_SERVER_VARS['HTTP_RANGE'] , strlen('bytes='));
      else $seek_range = substr($_SERVER['HTTP_RANGE'] , strlen('bytes='));

      $range = explode('-',$seek_range);

      if ($range[0] > 0) $this->seek_start = intval($range[0]);
      if ($range[1] > 0) $this->seek_end = intval($range[1]);
      else $this->seek_end = -1;
    }
    else
    {
      $this->seek_start = 0;
      $this->seek_end = -1;
    }
  }

  public function EncodeHeader($str) {
    /*require_once 'phpmailer/class.phpmailer.php';
    $m = new PHPMailer();
    $m->CharSet='UTF-8';
    return $m->EncodeHeader($str, $position);*/
    if ( false!==strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') ) // ненавидите ли вы IE так, как ненавижу его я?
    {
      return '='.iconv('utf-8', 'windows-1251', $str);
    }
    if (strlen($str) == MyStrLen($str)) {
      // если нету мультибайтных (=русских) символов
      return '='.$str;
    }
    if ( false!==strpos($_SERVER['HTTP_USER_AGENT'], 'Opera/9') ) {
      return "*=utf-8''".urlencode($str);
    }
    return '="=?windows-1251?B?'.base64_encode(iconv('utf-8', 'windows-1251', $str)).'?="';
  }

  protected function header($size,$seek_start=null,$seek_end=null) {
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    if ('json'==$this->dlparams['format']) {
      header("Content-Type: application/json; charset=utf-8");
    } else {
      header("Content-Type: {$this->mime_type}");
      header('Content-Disposition: attachment; filename'.$this->EncodeHeader($this->fileext).'');
      if ( 0==$seek_start )
      {
        header('HTTP/1.0 200 OK');
      }
      else
      {
        header('HTTP/1.0 206 Partial Content');
        header('Status: 206 Partial Content');
        header("Content-Range: bytes $seek_start-$seek_end/$size");
      }
      header('Accept-Ranges: bytes');
      header("Content-Length: " . ($seek_end - $seek_start + 1));
    }
  }

  protected function download() {
    $seek = $this->seek_start;
    $bufsize = $this->bufsize;

    //do some clean up
    @ob_end_clean();
    $old_status = ignore_user_abort(true);
    @set_time_limit(0);
    if ('json'==$this->dlparams['format']) {
      $json_str = "{success: true, mimetype: ".json_encode($this->mime_type).
                  ", filename: ".json_encode($this->fileext).
                  ", encoding: 'base64'";
    }
    $size = filesize($this->filename);

    if ($this->extract_p7z) {
      $f = Core_Crypto_Signature::CheckSignatureFile($this->filename, true, false);
      if ($this->extract_p7z>1) {
        $i=1;
        while ($i < $this->extract_p7z) {
          $f = Core_Crypto_Signature::CheckSignature($f['SignedData'], true, false);
          $i++;
        }
      }
      $this->header($size, 0, strlen($f['SignedData'])-1);
      if ('json'==$this->dlparams['format']) {
        $json_str.=', length: '.strlen($f['SignedData']).', ';
        echo $json_str;
        $s = base64_encode($f['SignedData']);
        echo "encodedlength: ".strlen($s).', data: "'.$s;
        echo '"}';
      } else {
        echo $f['SignedData'];
      }
    } else {
      if ($seek > ($size - 1)) $seek = 0;

      $res = fopen($this->filename,'rb');
      if ($seek) fseek($res , $seek);
      if ($this->seek_end < $seek) $this->seek_end = $size-1;

      $this->header($size,$seek,$this->seek_end); //always use the last seek
      $size = $this->seek_end - $seek + 1;

      if ('json'==$this->dlparams['format']) {
        $json_str.=', length: '.$size.', data: "';
        echo $json_str;
        if ($bufsize%3) {
          $bufsize -= $bufsize%3;
        }
      }
      $enclen = 0;

      while (!(connection_aborted() || connection_status() == 1) && $size > 0)
      {
        $data = null;
        if ($size < $bufsize) {
          $data = fread($res , $size);
        }
        else {
          $data = fread($res , $bufsize);
        }
        if ('json'==$this->dlparams['format']) {
          $t = base64_encode($data);
          $enclen += strlen($t);
          echo $t;
        } else {
          echo $data;
        }

        $size -= $bufsize;
        flush();
      }
      fclose($res);
      if ('json'==$this->dlparams['format']) {
        echo '", encodedlength: '.$enclen.'}';
      }
    }

    ignore_user_abort($old_status);
    set_time_limit(ini_get('max_execution_time'));

    return true;
  }
}
