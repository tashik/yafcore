<?php

class Core_DAO_Backend_Db extends Core_DAO_Backend_Abstract {

  /**
   * Хендл базы данных
   * @var Zend_Db_Adapter_Abstract
   */
  protected $_handle;

  /**
   * Таблица
   * @var Zend_Db_Table
   */
  protected $_table;
  protected $_fetch_query;
  protected $_id_property;

  public function __construct($config) {
    if (!$config['primary']) {
      throw new Exception("БД-бекенд может быть только первичным");
    }
    parent::__construct($config);

    $this->_supported_caps[] = self::CAP_PARTIAL_UPDATE;
    $this->_supported_caps[] = self::CAP_SEARCH_ADVANCED;

    $this->_handle = isset($config['adapter'])?$config['adapter']:getDbInstance();
    $this->_id_property = isset($config['id_property'])?$this->_handle->quoteIdentifier($config['id_property']):'id';
  }

  /**
   * Получить объект таблицы
   * @return Zend_Db_Table
   */
  protected function _getTable() {
    if (!$this->_table) {
      if (!isset(self::$_cache["table_{$this->_table_name}"])) {
        self::$_cache["table_{$this->_table_name}"] = new Zend_Db_Table(array(
          Zend_Db_Table::NAME => $this->_table_name,
          Zend_Db_Table::ADAPTER => $this->_handle,
        ));
      }
      $this->_table = self::$_cache["table_{$this->_table_name}"];
    }
    return $this->_table;
  }

  /**
   * Получить селект выборки объекта по id
   * @return Zend_Db_Select
   */
  protected function _getFetchQuery() {
    if (!$this->_fetch_query) {
      $fetch_query = "fetch_{$this->_table_name}";
      if (!isset(self::$_cache[$fetch_query])) {
        self::$_cache[$fetch_query] = $this->_handle->select()
          ->from($this->_table_name)
          ->where("{$this->_id_property}=:id")
          ->limit(1);
      }
      $this->_fetch_query = self::$_cache[$fetch_query];
    }
    return $this->_fetch_query;
  }

  public function fetch($id) {
    return $this->_handle->fetchRow($this->_getFetchQuery(), array('id'=>$id), Zend_Db::FETCH_ASSOC);
  }

  public function fetchBy($field, $value) {
    $select = $this->_handle->select()
        ->from($this->_table_name)
        ->where($this->_handle->quoteIdentifier($field)."=:v");
    return $this->_handle->fetchAll($select, array('v'=>$value), Zend_Db::FETCH_ASSOC);
  }

  protected function select($fields=null) {
    return $this->_handle->select()->from($this->_table_name, $fields);
  }

  protected function _whereId($id) {
    return $this->_handle->quoteInto("{$this->_id_property}=?", $id);
  }

  public function save($data) {
    $table = $this->_getTable();
    if (isset($data['id']) && $data['id']) {
      $id = $data['id'];
      unset($data['id']);
      if (!$table->update($data, $this->_whereId($id))) {
        return false;
      }
    } else {
      $id = $table->insert($data);
    }
    return $id;
  }

  public function remove($id) {
    return $this->_getTable()->delete($this->_whereId($id));
  }

  public function getProperties() {
    return $this->_getTable()->info(Zend_Db_Table::COLS);
  }

  public function update($id, $data) {
    $table = $this->_getTable();
    if (!$id) {
      throw new Exception("Невозможно частичное обновление не сохраненной записи");
    }
    if (!$table->update($data, $this->_whereId($id))) {
      return false;
    }
    return $id;
  }

  protected function _findAdvanced(Core_DAO_Select $criteria) {
    /* @var $select Zend_Db_Select */
    $select = $criteria->select;
    if ( !($select instanceof Zend_Db_Select)) {
      throw new Exception("Некорректные параметры выборки");
    }
    if ($criteria->limit) {
      $select->limit($criteria->limit, $criteria->start);
    }
    $start = intval($criteria->start);
    $limit = intval($criteria->limit);
    if ($criteria->sort) {
      $sort = array();
      foreach ($select->sort as $s) {
        $sort[] = $this->_handle->quoteIdentifier($s['sort']).' '.('asc'==strtolower($s)||'1'==$s)?'ASC':'DESC';
      }
      $select->order($sort);
    }
    if ($criteria->count) {
      if ($criteria->bind) {
        $select->bind($criteria->bind);
      }
      return getPagerData($select, $start, $limit,
                          isset($criteria->truncate)?$criteria->truncate:true,
                          isset($criteria->skip_count)?$criteria->skip_count:false);
    }
    if ($start || $limit) {
      $select->limit($limit, $start);
    }
    return $this->_handle->fetchAll($select, $criteria->bind, Zend_Db::FETCH_ASSOC);
  }

  public function convertTypeToRaw($value, $type) {
    switch ($type) {
      case 'bool':
        return $value?1:0;
      case 'array':
        return serialize(toArray($value));
      default:
        return parent::convertTypeToRaw($value, $type);
    }
  }

  public function convertRawToType($value, $type) {
    switch ($type) {
      case 'array':
        return toArray(unserialize("$value"));
      default:
        return parent::convertRawToType($value, $type);
    }
  }

  public function removeAll()
  {
     return $this->_getTable()->delete('1=1');
  }
}
