<?php
if(!function_exists('checkdnsrr')){
  function checkdnsrr($host, $type=''){
    if(!empty($host)){
      $type = (empty($type)) ? 'MX' : $type;
      exec('nslookup -type='.$type.' '.escapeshellcmd($host), $result);
      $it = new ArrayIterator($result);
      foreach(new RegexIterator($it, '~^'.$host.'~', RegexIterator::GET_MATCH) as $result){
        if($result){
          return true;
        }
      }
    }
    return false;
  }
}

/**
Validate an email address.
Provide email address (raw input)
Returns true if the email address has the email
address format and the domain exists.
*/
function validEmail($email)
{
   $isValid = true;
   $atIndex = strrpos($email, "@");
   if (is_bool($atIndex) && !$atIndex)
   {
      $isValid = false;
   }
   else
   {
      $domain = substr($email, $atIndex+1);
      $local = substr($email, 0, $atIndex);
      $localLen = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64)
      {
         // local part length exceeded
         return "$local не похоже на допустимое имя";
      }
      else if ($domainLen < 1 || $domainLen > 255)
      {
         // domain part length exceeded
         return "$domain не похоже на допустимое имя домена";
      }
      else if ($local[0] == '.' || $local[$localLen-1] == '.')
      {
         // local part starts or ends with '.'
        return "имя домена $domain начинается или заканчивается точкой";
      }
      else if (preg_match('/\\.\\./', $local))
      {
         // local part has two consecutive dots
         return "имя $local содержит две точки подряд";
      }
      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
      {
         // character not valid in domain part
        return "имя домена $domain содержит недопустимые символы";
      }
      else if (preg_match('/\\.\\./', $domain))
      {
         // domain part has two consecutive dots
         return "имя домена $domain содержит две точки подряд";
      }
      else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
                 str_replace("\\\\","",$local)))
      {
         // character not valid in local part unless
         // local part is quoted
         if (!preg_match('/^"(\\\\"|[^"])+"$/',
             str_replace("\\\\","",$local)))
         {
            return "имя $local содержит недопустимые символы";
         }
      }
      if ($isValid && !defined('SKIP_MAIL_DOMAIN_CHECK') && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
      {
         // domain not found in DNS
         return "Домен $domain не существует";
      }
   }
   return $isValid;
}
