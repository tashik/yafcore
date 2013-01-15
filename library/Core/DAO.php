<?php

abstract class Core_DAO extends Core_Observable {

  protected  $_backends;
  private $_properties = array();

  // Конфигурация бекендов (будет передана в Core_DAO_Backend_Abstract для инициализации)
  // Порядок бекендов важен: более ранним отображается первичный бекенд, остальные вторичны (кеши)
  protected $_backends_config = array(
    array(
      'type' => 'db',
      'table' => null
    )
  );

  protected $_data = array();
  protected $_depends_data = array(); // Данные зависимостей сюда складываются объекты зависимостей
  protected $_parameters = array();
  protected $_dirty = array();        // Признак "грязности" если объект грязный то будет сохранен сюда складываются измененые свойства
  protected $_parent = null;

  /**
   * Конфиг зависимостей объекта в формате массива с элементами вида
   *   'пропертя' => array(параметры)
   * Параметры:
   *   class — класс объекта зависимости
   *   loader — функция-загрузчик объекта, принимающая id хозяина (можно не указывать,
   *            тогда будет использоваться типовой loadByOwner)
   *   opts — дополнительные опции к лоадеру
   *   single — true если зависимый объект может быть только один
   *   aggregate — аггрегируемая зависимость (будет включаться в бекенды с
   *               поддержкой аггрегации в составе объекта)
   * @var array
   */
  protected $_dependencies = array();

  public function __construct($id=null) {
    $this->_setupBackend();
    $this->_initProperties();
    if ($id) {
      if (!$this->_load($id)) {
        throw new Exception("Cannot find object $id");
      }
    }
  }

  private function _initProperties() {
    if (!isset($this->_backends[0])) {
      throw new Exception("Отсутствуют бекенды DAO");
    }
    $properties = $this->_backends[0]->getProperties();
    if (!$properties) {
      $properties = array_keys($this->_parameters);
    }
    $this->_properties = $properties;
    foreach ($this->_dependencies as $dep=>$config) {
      if (isset($config['aggregate']) && $config['aggregate']) {
        $this->_properties[] = $dep; // добавляем в _properties только агрегированные зависимости
        if (isset($config['single']) && $config['single']) {
          $this->_depends_data[$dep] = null;
        } else {
          $this->_depends_data[$dep] = array();
        }
      }
    }
    return $this;
  }

  private function _setupBackend() {
    $this->_backends = array();
    $primary = true;
    $primary_table = false;
    foreach ($this->_backends_config as $cfg) {
      $cfg['primary'] = $primary;
      if (!$primary && !isset($cfg['table'])) {
        $cfg['table'] = $primary_table;
      }
      $this->_backends[] = Core_DAO_Backend_Abstract::factory($cfg);
      if ($primary) {
        $primary_table = $cfg['table'];
        $primary = false;
      }
    }
  }

  /**
   * Сохранить объект в базе.
   * Евенты:
   *   loaded: вызывается после загрузки данных из базы, параметры:
   *     0 ($data) — загруженные данные
   *     результат: обновленные данные для загрузки (если null/false, то остаются как были)
   *     результат фейла: фейл загрузки
   * @param integer|string $id
   * @return boolean статус загрузки
   */
  protected function _load($id) {
    // Обходим бекенды начиная с самого последнего (т.е. быстрого)
    for ($i=count($this->_backends)-1; $i>=0; $i--) {
      $data = $this->_backends[$i]->fetch($id);
      if ($data) {
        $status = $this->_loadData($data, $this->_backends[$i]);
        if ($status && $i>0 && $i<count($this->_backends)-1) {
          $this->_updateCache();
        }
        return $status;
      }
    }
    return false;
  }

  /**
   * Загружает в объект данные из массива (голые данные бекенда)
   * @param array $data
   * @param Core_DAO_Backend_Abstract $backend
   * @return boolean статус загрузки
   */
  protected function _loadData($data, $backend) {
    /*foreach ($this->_dependencies as $dep=>$config) {
      if (isset($data[$dep])) {
        $class = $config['class'];
        $obj = new $class();
        $obj->_parent = $this;
        $obj->_loadData($data[$dep], $backend);

        $this->_depends_data[$dep][] = $obj;

        unset($data[$dep]);
      }
      if (!isset($this->_depends_data[$dep])) {
        $this->_depends_data[$dep] = array();
      }
    }*/

    $data = $this->_prepareFormattedData($data, $backend);
    $result = $this->fireEvent('beforeload', new Core_Event($data));
    if ($result->isFailed()) {
      return false;
    }
    if ($result->getResult()) {
      $data = $result->getResult();
    }

    $this->_setData($data);
    $this->_dirty = array(); // после загрузки мы уже чистые
    return true;
  }

  /**
   * Загрузка зависимости по родителю
   * @param Core_DAO $o
   * @param int|string $parent_id
   * @param string $field
   * @return array of Core_DAO
   */
  public static function  loadByParent($o, $parent_id, $field) {
    $data = $o->findByCriteria(array('select'=>array($field=>$parent_id), 'limit'=>false));
    $objects = array();
    foreach ($data as $d) {
      if ($o->_loadData($d, $o->_backends[0])) {
        $objects[] = $o;
      }
    }
    return $objects;
  }

  /**
   * Подготавливает данные к тому, чтобы сохранить их в бекенде
   * (подставляет дефолтные значения, делает приведение типов)
   * @param array $data
   * @param Core_DAO_Backend_Abstract $backend
   * @return array
   */
  protected function _prepareRawData($data, $backend) {
    foreach ($this->_parameters as $property=>$parameters) {
      if (isset($parameters['type']) &&!empty($data[$property])) {
        $data[$property] = $backend->convertTypeToRaw($data[$property], $parameters['type']);
      }
    }
    foreach ($this->_dependencies as $dep=>$config) {
      if (!isset($data[$dep])) {
        continue;
      }
      $cls = new $config['class'];
      if (isset($config['single']) && $config['single']) {
        $data[$dep] = $cls->_prepareRawData($data[$dep], $backend);
      } else {
        foreach ($data[$dep] as $k=>$v) {
          $data[$dep][$k] = $cls->_prepareRawData($data[$dep][$k], $backend);
        }
      }
    }
    return $data;
  }

  /**
   * Подготавливает данные к тому, чтобы заполнить ими экземпляр
   * (подставляет дефолтные значения, делает обратное приведение типов)
   * @param array $data
   * @param Core_DAO_Backend_Abstract $backend
   * @return array
   */
  protected function _prepareFormattedData($data, $backend) {
    foreach ($this->_parameters as $property=>$parameters) {
      if (!isset($data[$property])) {
        $data[$property] = isset($parameters['default'])?$parameters['default']:null;
      }
      if (isset($parameters['type'])) {
        $data[$property] = $backend->convertRawToType($data[$property], $parameters['type']);
      }
    }
    foreach ($this->_dependencies as $dep=>$config) {
      if (!isset($data[$dep]) ||empty($data[$dep])) {
        continue;
      }
      $cls = new $config['class'];
      if (isset($config['single']) && $config['single']) {
        $data[$dep] = $cls->_prepareFormattedData($data[$dep], $backend);
      } else {
        foreach ($data[$dep] as $k=>$v) {
          $data[$dep][$k] = $cls->_prepareFormattedData($data[$dep][$k], $backend);
        }
      }
    }
    return $data;
  }

  /**
   * Конструирует навание метода из названия поля
   * @param string $property
   * @return string
   */
  protected function _getMethodByProperty($property)
  {
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $property)));
  }

  /**
   * Получает название поля из названия метода
   * @param string $method
   * @return string
   */
  protected function _getPropertyByMethod($method)
  {
    $property = preg_replace('@([A-Z]+)@', '_$1', $method);
    return ltrim(strtolower($property), '_');
  }

  private function _constructDependency($depconfig, $data) {
    $class = $depconfig['class'];
    if(isset($depconfig['single']) && $depconfig['single']) {
      if(is_array($data)) {
        $obj = new $class();
        if (isset($depconfig['aggregate']) && $depconfig['aggregate']) {
          $obj->_parent = $this;
        }
        return $obj->_setData($data);
      }
    } else {
      if (isset($data[0]) && is_array($data[0])) {
        $objs = array();
        foreach($data as $r) {
          $obj = new $class();
          if (isset($depconfig['aggregate']) && $depconfig['aggregate']) {
            $obj->_parent = $this;
          }
          $objs[] = $obj->_setData($r);
        }
        return $objs;
      }
    }
    return $data;
  }

  /**
   * Заполнение экземпляра данными
   * @param array $data
   * @return Core_DAO
   */
  public function _setData($data) {
    foreach ($data as $property=>$value) {
      if (array_key_exists($property, $this->_parameters)) {
        $this->_setValue($property, $value);
      } elseif (array_key_exists($property, $this->_dependencies)) {
        $depconfig = $this->_dependencies[$property];
        $this->_setValue($property, $this->_constructDependency($depconfig, $value));
      }
    }
    return $this;
  }

  /**
   * Провалидировать объект
   * Евенты:
   *   validate: вызывается перед валидацией,
   *     параметры: нет
   *     результат: игнорируется
   *     результат фейла: ошибка валидации
   * @return \Core_DAO
   */
  public function validate() {
    $result = $this->fireEvent('validate', new Core_Event());
    if ($result->isFailed()) {
      throw new ResponseException('Ошибка валидации');
    }
    foreach ($this->_parameters as $property=>$parameters) {
      if (isset($parameters['validators'])) {
        $method = 'get'.$this->_getMethodByProperty($property);
        $this->_validateProperty($this->$method(), $property);
      }
    }
    return $this;
  }

  /**
   * Валидация проперти
   * @param mixed $value новое значение проперти
   * @param string $property имя проперти
   * @throws ResponseException
   */
  private function _validateProperty($value, $property) {
    if (   isset($this->_parameters[$property])
        && isset($this->_parameters[$property]['validators']))
    {
      $p = $this->_parameters[$property];
      $params = isset($p['params'])?$p['params']:array();
      $n = isset($p['pseudo'])?$p['pseudo']:$p['name'];
      $validator = new Core_DataValidation();
      $validator->validateData($p['validators'], $value, $params);
      if ( count($validator->getErrors()) )
      {
        $text = str_replace('%fieldname%', $n, join("<br/>\n", $validator->getErrors()));
        throw new ResponseException("Неправильный ввод: $n '$value':<br/>\n$text", 400);
      }
    }
  }

  /**
   * Установка значения поля экземплара
   * @param $property имя поля
   * @param $value присваеваемое значение
   * @throws Exception
   */
  protected function _setValue($property, $value) {
    //logVar($value, "setting $property");
    if (isset($this->_dependencies[$property])) {
      $depconfig = $this->_dependencies[$property];
      if(isset($depconfig['single']) && $depconfig['single']) {
        $this->_depends_data[$property] = $value;
      } else {
        if (empty($value)) { //todo: Нужна проверка хранения массивов. Возможно костыль
          $this->_depends_data[$property] = array();
          return;
        }
        $this->_depends_data[$property] = (is_array($value)&&isset($value[0]))?$value:array($value);
      }

    } else {
      if (!in_array($property, $this->_properties)) {
        throw new Exception("Нет такой проперти: $property");
      }
      if (!array_key_exists($property, $this->_data) || $this->_data[$property] !== $value) {
        $this->_dirty[$property] = true;
        $this->_data[$property] = $value;
        if ($this->_parent) {
          $this->_parent->_dirty[get_class($this)] = true;
        }
      }
    }
  }

  /**
   * Получение значения поля экземпляра
   * @param $property имя поля
   * @return null
   * @throws Exception
   */
  protected function _getValue($property) {
    //logVar($this->_data, "getting $property");
    if (isset($this->_dependencies[$property])) {
      if (!isset($this->_depends_data[$property])) {
        $depconfig = $this->_dependencies[$property];
        if(!isset($depconfig['class'])) {
          throw new Exception('Неправильно сконфигурирована зависимость '.$property.' - не определен соответствующий класс');
        }
        $class = $depconfig['class'];
        // Подгрузка аггрегированных зависимостей (которые лежат в свойствах у папы)
        if($depconfig['aggregate']) {
          $this->_depends_data[$property] = ($depconfig['single']) ? null: array();
          if(array_key_exists($property, $this->_data)) {
            $dep_value = $this->_data[$property];
            if(isset($depconfig['single']) && $depconfig['single']) {
              $this->_depends_data[$property] = $dep_value;
            } else {
              if(!empty($dep_value)) {
                if(!isset($dep_value[0])) {
                  $dep_value = array($dep_value);
                }
                $this->_depends_data[$property] = $dep_value;
              }
            }
          }
        } else {
          $params = array();
          // Подгрузка неаггрегированных зависимостей
          if (!isset($depconfig['loader'])) {
            $depconfig['loader'] = 'loadByParent';
            $name = $this->_getPropertyByMethod(preg_replace('@Model_@', '', get_class($this)));
            $depconfig['opts'] = "{$name}_id";
            $params[] = new $class();
          }
          $loader = $depconfig['loader'];
          $fn = "$class::$loader";
          $params[] = $this->getId();
          if (isset($depconfig['opts']) && !empty($depconfig['opts']) && is_array($depconfig['opts'])) {
            $params = array_merge($params, $depconfig['opts']);
          }
          $dep_data = call_user_func_array($fn, $params);
          if(isset($depconfig['single'])){
            if($depconfig['single']) {
              $dep_data = $dep_data[0];
            }
          }
          $this->_depends_data[$property] = $dep_data;
        }
      }
      $prop = $this->_depends_data[$property];
      return $prop;
    }
    if (!array_key_exists($property, $this->_data)) {
      return isset($this->_parameters[$property]['default'])?$this->_parameters[$property]['default']:null;
    }
    return $this->_data[$property];
  }

  /**
   * Генерилка сеттеров-геттеров
   * @param string $fname - имя метода
   * @param array $arguments - параметры
   * @return null|void
   * @throws Exception
   */
  public function __call($fname, $arguments) {
    if (preg_match('@^(set|get)(.+)$@i', $fname, $matches))
    {
      $property = $this->_getPropertyByMethod($matches[2]);
      if ('set'==strtolower($matches[1]))
      {
        if (count($arguments)<1)
        {
          throw new Exception("Set what? (in {$fname})");
        }
        $value = $arguments[0];
        return $this->_setValue($property, $value);
      } else
      {
        return $this->_getValue($property);
      }
    }
    throw new Exception('Invalid method call: '.$fname);
  }

  /**
   * Преобразование данных экземпляра в массив
   * @param bool $recursive включить данные зависимостей
   * @return array
   */
  protected function _getDataAsArray($recursive = false) {
    $data = array();
    // Подгружаем только данные модели
    foreach ($this->_properties as $property) {
      $method = 'get'.$this->_getMethodByProperty($property);
      $data[$property] = $this->$method();
    }
    // Подгружаем еще и зависимости
    if ($recursive) {
      foreach ($this->_dependencies as $dep=>$config) {
        if(isset($config['single']) && $config['single']) {
          $data[$dep] = null;
        } else {
          $data[$dep] = array();
        }
        if (!isset($this->_depends_data[$dep])) {
          continue;
        }
        if(isset($config['single']) && $config['single']) {
            $data[$dep] = $this->_depends_data[$dep]->_getDataAsArray($recursive);
        } else {
          foreach ($this->_depends_data[$dep] as $dependency) {
            if(!empty($dependency)) {
              $data[$dep][] = $dependency->_getDataAsArray($recursive);
              /*foreach($dependency as $depobj) { // ajh
                $data[$dep][] = $depobj->_getDataAsArray($recursive);
              }*/
            }
          }
        }
      }
    }
    return $data;
  }

  /**
   * Получение данных экземпляра в виде массива
   * @return array
   */
  public function toArray($recursive=false) {
    return $this->_getDataAsArray($recursive);
  }

  /**
   * Актуализация кэша
   */
  protected function _updateCache() {
    if ($this->_parent) {
      $this->_saveData(true);
    } else {
      $this->_saveData(true);
    }
  }

  // проверить детей на грязность return true/false
  // проверяет детей в зависимостях
  private function checkDirtyChild(){
    if (isset($this->_depends_data)){
      foreach($this->_depends_data as $depObj){
        if(count($depObj->_dirty)>0){
          return true; // дети грязные
        }
      }
    }
    return false;
  }

  /**
   * Сохранение данных экземпляра
   * @param bool $ignore_primary - сохранять только в кэш
   * @return Core_DAO
   * @throws Exception
   */
  private function _saveData($ignore_primary=false) {
    if (empty($this->_dirty)) {
      // ничего не менялось — нет смысла что-либо делать
      if(!$this->checkDirtyChild()){
        return $this;
      }
    }
    $this->validate();
    $data = $this->_getDataAsArray(false);
    $result = $this->fireEvent('beforeSave', new Core_Event($data));
    if ($result->isFailed()) {
      return $this;
    }
    if ($result->getResult()) {
      $data = $result->getResult();
    }
    $primary_ok = false;

    /* @var $backend Core_DAO_Backend_Abstract */
    foreach ($this->_backends as $backend) {
      if ($ignore_primary && !$primary_ok) {
        $primary_ok = true;
        $id = $this->getId();
        if (!$id) {
          throw new Exception("Невозможно закешировать несохраненный объект");
        }
        continue;
      }
      $backend_data = $data;
      if ($backend->getCapability(Core_DAO_Backend_Abstract::CAP_AGGREGATE)) {
        foreach ($this->_dependencies as $dep=>$config) {
          if ($config['aggregate']) {
            $backend_data[$dep] = $this->_getValue($dep);
            if (isset($config['single']) && $config['single']) {
              if(!empty($backend_data[$dep])) {
                $backend_data[$dep] = $backend_data[$dep]->_getDataAsArray(true);
              }
            } else {
              if(!empty($backend_data[$dep])) {
                foreach ($backend_data[$dep] as $k=>$v)  {
                  if(!empty($v)) {
                    $backend_data[$dep][$k] = $v->_getDataAsArray(true);
                  }
                }
              }
            }
          }
        }
        $id = $backend->save($this->_prepareRawData($backend_data, $backend));
      } else {
        if ($data['id'] && $backend->getCapability(Core_DAO_Backend_Abstract::CAP_PARTIAL_UPDATE)) {
          $backend_data = array();
          foreach ($this->_dirty as $prop=>$config) {
            if (array_key_exists($prop, $data)) {
              $backend_data[$prop] = $data[$prop];
            }
          }
          $id = $backend->update($data['id'], $this->_prepareRawData($backend_data, $backend));
        } else {
          $id = $backend->save($this->_prepareRawData($backend_data, $backend));
        }
      }
      if (!$id && !$primary_ok) {
        // не удалась вставка в первичную базу — это фатальная ошибка
        throw new Exception("Ошибка сохранения объекта в первичной базе");
      } elseif (!$id) {
        // не удалась вставка во вторичную базу (в кеш) — удаляем эту запись из базы
        $backend->remove($data['id']);
        continue;
      }
      if (!$primary_ok) {
        // это была вставка в первичную базу
        $primary_ok = true;
        if (!isset($data['id']) || !$data['id']) {
          $data['id'] = $id;
          $this->setId($id);
        }
      }
    }
    $this->fireEvent('afterSave', $data);
    if (!$ignore_primary) {
      // сохранились => чистенькие
      $this->_dirty = array();
    }
    return $this;
  }

  /**
   * Сохранить объект в базе.
   * Евенты:
   *   beforeSave: вызывается перед сохранением, параметры:
   *     0 ($data) — данные для сохранения
   *     результат: обновленные данные для сохранения (если null/false, то остаются как были)
   *     результат фейла: тихий отказ от сохранения.
   *   afterSave: вызывается после сохранения, параметры
   *     0 ($data) — сохраненные данные
   *     результат: игнорируется
   *     результат фейла: игнорируется
   * @return \Core_DAO
   * @throws Exception
   */
  public function save() {
    if ($this->_parent) {
      $this->_parent->save();
    } else {
      $this->_saveData();
    }
  }

  /**
   * Поиск по критериям
   * @param array $params
   *  select - ассоциативный массив критериев или готовый Zend_Db_Select
   *  bind - бинды к запросу в случае Zend_Db_Select
   *  sort - критерии сортировки, массив вида array(array('sort'=>'Имя поля для сортировки', 'dir'=>'Направление сортировки'))
   *  limit - количество записей (по умолчанию берется из 700-interface.ini interface->linePerPage, если это не то, что нужно, следует передавать ключ all=>true)
   *  start - отступ, по умолчанию 0
   *  count - считать общее число записей или нет (по умолчанию нет), если установлено true,
   *          то будет возвращен массив вида array(данные, число_записей),
   *          иначе просто данные
   * @return array
   */
  public function findByCriteria($params=array()) {
    // нельзя использовать другие бекенды, т.к. они кеши, и в них может не быть данных
    /* @var $backend Core_DAO_Backend_Abstract */
    $backend = $this->_backends[0];
    $default_params = array(
      'limit'=>getConfigValue('interface->linesPerPage'),
      'start'=>0,
      'count'=>false,
      'fields'=>array('*')
    );
    $params = array_merge($default_params, $params);
    if (isset($params['select']) && is_array($params['select'])) {
      $params['select'] = $this->_prepareRawData($params['select'], $backend);
    }

    return $backend->findAll($params);
  }

  public function remove($id) {
    foreach ($this->_backends as $backend) {
      $backend->remove($id);
    }
  }

  public function removeAll() {
    foreach ($this->_backends as $backend) {
      $backend->removeAll();
    }
  }


}
