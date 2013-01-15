<?php

/**
 * Класс подключения к MongoDB. Конфигурация указывается в параметре
 * resources->mongo. Доступны следующие параметры:
 *   * host — хост монги. В хосте может быть указан порт через ':', также это
 *     может быть список хостов через запятую. Обязательный параметр.
 *   * port — порт монги. Если порт указан в host, или в host несколько серверов,
 *     то параметр не указывать. Также можно не указывать если порт дефолтный.
 *   * dbname — база монги. Обязательный параметр.
 *   * params — массив дополнительных параметров, не обязателен. Параметры:
 *     * username — имя пользователя
 *     * password — пароль
 *     см. также параметр $options в документации на Mongo::__construct.
 */
class Core_MongoDb {
  protected static $_mongo_handle = null;
  protected static $_db_handle = null;
  protected static $_error = null;

  /**
   * Получить инстанс MongoDB в соответствии с конфигом. Возвращает false
   * в случае, если инстанс получить невозможно. Детали ошибки можно получить
   * через функцию getError()
   * @param bool $throw если true, кинуть эксцепшн с ошибкой вместо возвращения false.
   * @return MongoDB
   */
  public static function getInstance($throw = true) {
    if (null===self::$_db_handle) {
      try {
        if (!class_exists('Mongo')) {
          throw new Exception('Отсутствует модуль Mongo');
        }
        $cfg = getConfigValue('resources->mongo');
        if (!$cfg) {
          throw new Exception('Доступ к MongoDB отключен конфигурацией');
        }
        if (!isset($cfg['host'])) {
          throw new Exception('Отсутствует параметр host в конфигурации MongoDB');
        }
        if (!isset($cfg['dbname'])) {
          throw new Exception('Отсутствует параметр dbname в конфигурации MongoDB');
        }
        $url = 'mongodb://'.$cfg['host'];
        if (isset($cfg['port'])) {
          $url .= ':'.$cfg['port'];
        }
        $url .= '/'.$cfg['dbname'];
        $opts = array('connect'=>true, 'db'=>$cfg['dbname']);
        if (isset($cfg['params'])) {
          $opts = array_merge($cfg['params'], $cfg);
        }
        self::$_mongo_handle = new Mongo($url, $opts);
        self::$_db_handle = self::$_mongo_handle->selectDB($cfg['dbname']);
      } catch (Exception $e) {
        self::$_error = $e;
        self::$_db_handle = false;
        if ($throw) {
          throw $e;
        }
      }
    } elseif (false===self::$_db_handle && $throw) {
      if (!self::$_error) {
        self::$_error = new Exception('Ошибка инициализации MongoDB');
      }
      throw self::$_error;
    }
    return self::$_db_handle;
  }

  /**
   * Возвращает инстанс подключения к MongoDB
   * @return Mongo
   */
  public static function getMongoHandle() {
    if (!self::$_db_handle) {
      self::getInstance(true);
    }
    return self::$_mongo_handle;
  }

  /**
   * Получить детали ошибки инициализации. Возвращает null если все хорошо
   * @return Exception
   */
  public static function getError() {
    return self::$_error;
  }
}
