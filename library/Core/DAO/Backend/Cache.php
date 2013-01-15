<?php

class Core_DAO_Backend_Cache extends Core_DAO_Backend_Abstract {

  /**
   * @var Zend_Cache_Core
   */
  protected $_handle;

  public function __construct($config) {
    parent::__construct($config);

    $this->_supported_caps[] = self::CAP_AGGREGATE;

    if ($config['primary']) {
      throw new Exception("Кеш-бекенд не может быть первичным");
    }
    if (isset($config['adapter'])) {
      $this->_handle = $config['adapter'];
    } else {
      if (Zend_Registry::isRegistered('shared_cache')) {
        $this->_handle = Zend_Registry::get('shared_cache');
      } elseif (Zend_Registry::isRegistered('cache')) {
        $this->_handle = Zend_Registry::get('cache');
      }
    }
    if (!$this->_handle) {
      throw new Exception("Отсутствует кеш-хранилище");
    }
  }

  public function fetch($id) {
    return $this->_handle->load("{$this->_table_name}_{$id}");
  }

  public function save($data) {
    //$data = $this->prepareFormattedData($data, $parameters);
    return $this->_handle->save($data, "{$this->_table_name}_{$data['id']}");
  }

  public function remove($id) {
    return $this->_handle->remove("{$this->_table_name}_{$id}");
  }

  public function convertRawToType($value, $type) {
    switch ($type) {
      case 'binary':
      case 'bindata':
        return base64_decode($value);
      default:
        return parent::convertRawToType($value, $type);
    }
  }

  public function convertTypeToRaw($value, $type) {
    switch ($type) {
      case 'binary':
      case 'bindata':
        return base64_encode($value);
      default:
        return parent::convertTypeToRaw($value, $type);
    }
  }

  public function removeAll()
  {
    // TODO: Implement removeAll() method.
  }
}
