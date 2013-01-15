<?php

function Core_Crypto_Bootstrap()
{
  $cmd='/opt/cprocsp/bin/ia32/cryptcp';
  $cmd_csp='/opt/cprocsp/bin/ia32/csptestf';
  $key='79b21f5af20bc4978ada90204d475a4419cb3690d87e8cc20b9c483699aa512ac3c8af0a7b79ee27ca2f58a1830fefad714d4a2e8bf7e7af912f4b2466b3d9cd';
  if (class_exists('Zend_Registry') && Zend_Registry::isRegistered('config'))
  {
    $c = Zend_Registry::get('config');
    /*$config=array('crypto'=>array());
    if ( isset($c->crypto->cryptcp_command) )
      $config['crypto']['cryptcp_command']=$c->crypto->cryptcp_command;
    if ( isset($c->crypto->server_key) )
      $config['crypto']['server_key']=$c->crypto->server_key;
    if ( isset($c->crypto->fake_crypt) || isset($c->fake_crypt) )
      $config['crypto']['fake_crypt']=1;
    if ( isset($c->crypto->nochain) )
      $config['crypto']['nochain']=$c->crypto->nochain;
    if ( isset($c->crypto->norev) )
      $config['crypto']['norev']=$c->crypto->norev;*/
    $config = $c->toArray();
  }
  else
  {
    global $CONFIG;
    $config = $CONFIG;
  }

  if ( isset($config['crypto']['cryptcp_command']) )
  {
    $cmd=$config['crypto']['cryptcp_command'];
  }
  if ( isset($config['crypto']['csptestf_command']) )
  {
    $cmd_csp=$config['crypto']['csptestf_command'];
  }
  if ( isset($config['crypto']['server_key']) )
  {
    $key=$config['crypto']['server_key'];
  }
  if ( isset($config['crypto']['fake_crypt']) && 1==$config['crypto']['fake_crypt'] )
  {
    define('FAKE_CRYPTCP', 1);
  }
  if ( isset($config['crypto']['mode']) )
  {
    define('CRYPTO_MODE', $config['crypto']['mode']);
  } elseif ( isset($config['crypto']['eds_mode']) ) {
    define('CRYPTO_MODE', $config['crypto']['eds_mode']);
  } else {
    define('CRYPTO_MODE', 'none');
  }
  if ( isset($config['crypto']['nochain']) && $config['crypto']['nochain'])
  {
    define('CRYPTCP_NO_CHAIN', 1);
  }
  if ( isset($config['crypto']['norev']) && $config['crypto']['norev'])
  {
    define('CRYPTCP_NO_REV', 1);
  }

  if ( isset($config['crypto']['sign_server']) && $config['crypto']['sign_server'])
  {
    define('SIGNING_SERVER', $config['crypto']['sign_server']);
  }

  DEFINE('CRYPTCP_COMMAND', $cmd);
  DEFINE('CSPTESTF_COMMAND', $cmd_csp);
  DEFINE('SERVER_KEY', $key);
  DEFINE('HASH_COMMAND', CRYPTCP_COMMAND.' -hash');
  DEFINE('HASH_VALUE', '@^\[ReturnCode: 0\]$@'); // регэксп теста успешного завершения cryptcp

  if ( isset($config['crypto']['check_eds_profile']) && $config['crypto']['check_eds_profile']) {
    DEFINE('CHECK_EDS_PROFILE', 1);
  }

  if (!function_exists('isBase64'))
  {
    require_once APPLICATION_PATH.'/../library/Util/string.php';
  }

  define('OID_CONTRACTOR', 'Специалист с правом подписи договора');
  define('OID_AUTHORIZED', 'Уполномоченный специалист');
  define('OID_ADMIN', 'Администратор организации');
  define('OID_SUPPLIER', 'Заявитель');
  define('OID_IP', 'Индивидуальный предприниматель');
  define('OID_FIZ', 'Физическое лицо');
  define('OID_UR', 'Юридическое лицо');
  define('OID_ETP', 'Использование на электронных площадок отобранных для проведения процедур в электронной форме');
}

Core_Crypto_Bootstrap();
