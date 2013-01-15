<?php

function sanitizeFileName($name)
{
  if (!is_utf8($name)) {
    $name = iconv('windows-1251', 'UTF-8//IGNORE', $name);
  }
  $name=strtr($name, '=;<>:?!`@#$%^&*{}\\/" ',
                     '_______________________');
  preg_match('@^(.*)(\.(?:[^.]*))?$@', $name, $matches);
  $name = $matches[1];
  $ext = isset($matches[2])?$matches[2]:'';
  while (strlen($name)>200)
  {
    $name=mb_substr($name, $name, mb_strlen($name)-1);
  }
  while (strlen($ext)>10)
  {
    $ext=mb_substr($ext, $ext, mb_strlen($ext)-1);
  }
  return $name.$ext;
}

/*function mb_basename($name)
{
  if (preg_match('@[/\\\\]([^/\\\\]*)$@', $name, $matches))
  {
    return $matches[1];
  }
  return $name;
}*/

function suggestUniqueName($fname, $store)
{
  $clean_name = preg_replace('@\[[0-9]*\]@', '', $fname);
  $name_data=pathinfo($clean_name);
  $name = $name_data['filename'];
  if ( ''==$name || preg_match('/^\./', $name) )
  {
    $name='file'.$name;
  }
  while (strlen($name)>200)
  {
    $name=mb_substr($name, $name, mb_strlen($name)-1);
  }
  if (preg_match('@^(.+)\.([a-z0-9]{3,4}(?:\.[a-z0-9]{3,4})*)$@iU', $name, $matches)) {
    $name_data['extension'] = $matches[2].'.'.$name_data['extension'];
    $name = $matches[1];
  }
  $ext=isset($name_data['extension'])?('.'.$name_data['extension']):'';
  $append='';
  $i=0;
  while ( true )
  {
    if (is_array($store))
    {
      $found=false;
      foreach($store as $path)
      {
        if (file_exists($path.'/'.$name.$append.$ext))
        {
          $found=true;
          break;
        }
      }
      if (!$found)
      {
        break;
      }
    }
    else
    {
      if (!file_exists($store.'/'.$name.$append.$ext))
      {
        break;
      }
    }
    $i++;
    $append='['.$i.']';
  }
  $name.=$append.$ext;
  return sanitizeFileName($name);
}

function checkFilename($filename) {
  if ( 0 !== strpos($filename, '.') ) {
    if ( false === strpos($filename, '/') ) {
      return true;
    }
  }
  return false;
}
