<?php

function printURL()
{
  $humnurl=false;
  if (isset($_SERVER["SERVER_SOFTWARE"]) &&
      false !== strpos($_SERVER["SERVER_SOFTWARE"], 'Apache')
     )
  {
    $humnurl=true;
  }
  $args=func_get_args();
  $ret='';
  foreach($args as $val)
  {
    $ret.='/'.$val;
  }
  if (!$humnurl)
  {
    $ret='/index.php'.$ret;
  }
  return $ret;
}
