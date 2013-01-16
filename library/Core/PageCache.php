<?php

require_once 'Util/http.php';

class Core_PageCache {

  private static $_cache = null;

  static protected $cacheable_uris = array(
    '/'=>ONE_MINUTE
  );

  static public function display($uri) {
    return;
    $ttl = isset(self::$cacheable_uris[$uri])?self::$cacheable_uris[$uri]:null;
    if (!$ttl) {
      return;
    }
    $cache = self::_getCache();
    $cache_key = self::_getCacheKey();
    $page = $cache->load($cache_key);
    if ($page) {
      header('X-Cache: Hit');
      putCacheableContents($page['data'], $page['time'], false, $ttl, $page);
      exit;
    }
  }

  static public function save($uri, $data=null, $headers = null, $response_code = null) {
    return;
    $ttl = isset(self::$cacheable_uris[$uri])?self::$cacheable_uris[$uri]:null;
    if (!$ttl) {
      return;
    }
    $cache = self::_getCache();
    $cache_key = self::_getCacheKey();
    if (null==$data) {
      $fc = Zend_Controller_Front::getInstance();
      if (!$fc) {
        return;
      }
      $response = $fc->getResponse();
      if (!$response) {
        return;
      }
      $data = $response->getBody();
      $headers_sent = $response->getHeaders();
      $headers = array();
      foreach ($headers_sent as $h) {
        if (!preg_match('@^(X-)|(Set-)@', $h['name'])) {
          $headers[] = "{$h['name']}: {$h['value']}";
        }
      }
      $response_code = $response->getHttpResponseCode();
    }
    if (strlen($data)>1024*1024) {
      return;
    }
    $data = array(
      'headers' => $headers,
      'data'  => $data,
      'md5'   => md5($data),
      'time'  => time(),
    );
    if (function_exists('gzencode')) {
      $data['gzdata'] = base64_encode(gzencode($data['data'], 9));
    }
    if (isset($response_code)) {
      $data['code'] = $response_code;
    }
    $page = $cache->save($data, $cache_key, array(), $ttl);
  }

  /**
   * @return Zend_Cache_Core
   */
  static private function _getCache() {
    /* @var $cache Zend_Cache_Core */
    if (!self::$_cache) {
      if (isRegistered('shared_cache')) {
        self::$_cache = getRegistryItem('shared_cache');
      } else {
        self::$_cache = getRegistryItem('cache');
      }
    }
    return self::$_cache;
  }

  static private function _getCacheKey($uri = null) {
    if (null===$uri) {
      $uri = $_SERVER['REQUEST_URI'];
    }
    return 'page_'.APPLICATION_ENV.'_'.md5("$uri");
  }
}
