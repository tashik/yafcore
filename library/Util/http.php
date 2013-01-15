<?php

/**
 * function returns a timestamp parsed from HTTP date compartible format
 * @param string $timestring
 * @return int timestamp
 */
function GetHTTPDate($timestring)
{
  $timestamp=strtotime($timestring);
  $curtime=time();
  $years50=60*60*24*365*50;
  while ( $timestamp > $curtime+$years50 ) //date is after,
                                           // than 50 years in future
  {
    $timestamp -= $years50;
  }
  return $timestamp;
}

/**
 * formats timestamp to HTTP date compartible format
 * @param int $timestamp
 * @return string
 */
function SetHTTPDate($timestamp)
{
  $date = gmdate("D, d M Y H:i:s",$timestamp);
  if ( 0==strlen($date) )
  {
    $date=gmdate("D, d M Y H:i:s",time());
  }
  return $date." GMT";
}

function putCacheableContents($contents, $mtime=false, $is_file=false, $expiration=86400, $extra_data=array()) {
  header('Cache-Control:');
  header('Pragma:');
  header('Vary:');

  if (!$is_file) {
    $etag = '"'.(isset($extra_data['md5'])?$extra_data['md5']:md5($contents)).'"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $etag==$_SERVER['HTTP_IF_NONE_MATCH']) {
      header('HTTP/1.0 304 Not Modified');
      exit;
    }
  }
  if ($mtime) {
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && GetHTTPDate($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=$mtime) {
      header('HTTP/1.0 304 Not Modified');
      exit;
    }
    header('Last-Modified: '.SetHTTPDate($mtime));
    header('Expires: '.SetHTTPDate($mtime+$expiration));
  }
  if ($is_file) {
    $etag = '"'.(isset($extra_data['md5'])?$extra_data['md5']:md5_file($contents)).'"';
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $etag==$_SERVER['HTTP_IF_NONE_MATCH']) {
      header('HTTP/1.0 304 Not Modified');
      exit;
    }
  }
  header('Content-Length: '.($is_file?filesize($contents):strlen($contents)));
  header("Etag: $etag");
  header("Accept-Ranges: bytes");
  if (isset($extra_data['headers']) && is_array($extra_data['headers']) ) {
    $code = isset($extra_data['code'])?$extra_data['code']:null;
    foreach ($extra_data['headers'] as $header) {
      header("$header", true, $code);
    }
  }

  if ($is_file) {
    readfile($contents);
  } else {
    if (isset($extra_data['gzdata'])
        && isset($_SERVER['HTTP_ACCEPT_ENCODING'])
        && false!==strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')
       )
    {
      header('Content-Encoding: gzip');
      $contents = base64_decode($extra_data['gzdata']);
    }
    echo $contents;
  }
  exit;
}

function parseRequestParams($params) {
  $params = explode('/', $params);
  $ret = array();
  for ($i=0; $i<count($params); $i+=2) {
    $ret[$params[$i]] = $params[$i+1];
  }
  return $ret;
}

if (!function_exists('getContentType')) {

function getContentType($filename) {
  if (function_exists('finfo_open')) {
    $r = finfo_open();
    return finfo_file($r, $filename, FILEINFO_MIME_TYPE);
  } else {
    return @mime_content_type($protocol->getFilePath());
  }
}

}

function return404() {
  header('HTTP/1.0 404 Not Found');
  echo "Not Found";
  exit;
}

function return403() {
  header('HTTP/1.0 403 Forbidden');
  echo "Forbidden";
  exit;
}
