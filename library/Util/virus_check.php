<?php

function virusCheck($filename)
{
  return true; // TODO: Выпилить при комите
  $avscanner='/usr/bin/clamdscan';
  $config = Zend_Registry::get('config');
  if ( isset($config->general->av_scanner) )
  {
    $avscanner=$config->general->av_scanner;
  }

  $avproc=new Model_Process($avscanner); // TODO: сделать настраиваемым

  $status=$avproc->Execute($filename);
  if (1===$status)
  {
    return false;
  }
  return true;
}