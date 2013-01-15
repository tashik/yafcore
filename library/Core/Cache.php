<?php

/**
 * Кешер объектов. Объекты хранятся по своим id’ам в отдельных пулах
 * (т.е. в каждом пуле иды объектов независимы)
 * Имя пула должно быть именем модели.
 * Цель кешера: исключение дублирования инстансов моделей
 * Также может применяться как кеш общего назначения.
 * Кеш умирает вместе с http запросом, а у каждого запроса свой кеш.
 */
class Core_Cache {
  protected static $_cache = array();

  /**
   * Достать объект из кеша
   * @param mixed $type пул кешера
   * @param int $id идентификатор объекта
   * @return mixed значение из кеша или null если кеш пуст
   */
  public static function get($type, $id) {
    return isset(self::$_cache[$type][$id])?self::$_cache[$type][$id]:null;
  }

  /**
   * Положить объект в кеш
   * @param mixed $type пул кешера
   * @param int $id идентификатор объекта
   * @param mixed $value объект
   * @return mixed $value
   */
  public static function set($type, $id, $value) {
    if (!isset(self::$_cache[$type])) {
      self::$_cache[$type] = array();
    }
    self::$_cache[$type][$id] = $value;
    return $value;
  }

  /**
   * Очистить кеш
   * @param mixed $type пул кешера, если true то чистит вообще весь кеш
   * @param int $id идентификатор объекта (если null то чистит весь пул)
   */
  public static  function clean($type, $id=null) {
    if (true===$type) {
      self::$_cache = array();
    } if (null===$id) {
      self::$_cache[$type] = array();
    } else {
      unset(self::$_cache[$type][$id]);
    }
  }
}

