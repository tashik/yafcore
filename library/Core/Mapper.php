<?php

abstract class Core_Mapper
{
  protected $_properties=null; // дабы дергать базу только один раз
  protected $_methods_map=null;
  // protected $_dbClass;
  // protected $_logTable;
  protected $_logFields=null;
  protected $_dbTable=null;
  protected $_tmp_draft=array();
  protected $_lazy_validate=false;
  protected $from_draft=false;
  public $DbSkip;

  protected function _initProperties()
  {
    //if (null===$this->_properties)
    {
      $t = $this->getDbTable()->info('cols');
      $this->_properties=array();
      $this->_methods_map=array();
      foreach ($t as $property=>$value)
      {
        $this->_properties[]=strtolower($value);
        $method=$this->getMethodByProperty($value);
        $this->_methods_map[$method]=strtolower($value);
        if (isset($this->_parameters[$property]) &&
            isset($this->_parameters[$property]['synonim']) )
        {
          $method=strtolower($this->_parameters[$property]['synonim']);
          $this->_methods_map[$method]=strtolower($value);
        }
      }
      foreach ($this->_parameters as $param=>$value)
      {
        if (isset($value['depends']) && $value['depends'])
        {
          //$this->_properties[]=strtolower($param);
          $method=$this->getMethodByProperty($param);
          $this->_methods_map[$method]=strtolower($param);
        }
        if (isset($value['timestamp']) && $value['timestamp']) {
          $this->_parameters[$param]['type']='timestamp';
        }
        if (isset($value['serialize']) && $value['serialize']) {
          $this->_parameters[$param]['type']='serialize';
        }
      }
    }
    //logVar($this->_properties, "{$this->_dbClass} properties:");
    //logVar($this->_methods_map, "{$this->_dbClass} methods_map:");
  }

  protected function __construct(array $options = null)
  {
    $this->_logFields=array('user_id'=>'user', 'field'=>'field', 'date'=>'date',
                            'from'=>'from', 'to'=>'to', 'record_id'=>'id', 'comment'=>'comment');
    $this->DbSkip=array();
    $this->_initProperties();
    if (isset($this->_parameters))
    {
      foreach($this->_parameters as $k=>$p)
      {
        if (isset($p['draft']))
        {
          if (!in_array($k, $this->_properties))
          {
            //logVar($p, get_class($this)."::construct ($k)");
            //logVar($this->_properties, get_class($this)."::construct properties");
            throw new Exception('Внутренняя ошибка: некуда сохранять черновик', 500);
          }
          $this->_parameters['draft_property']=$k;
          $this->_parameters[$k]['nolog']=true;
          unset($this->_parameters[$k]['draftable']);
          unset($this->_parameters[$k]['validators']);
          unset($this->_parameters[$k]['serialize']);
          unset($this->_parameters[$k]['type']);
        }
      }
    }
    foreach ($this->_properties as $property)
    {
      if ( isset($this->_parameters['draft_property'])
           && $this->_parameters['draft_property']!=$property )
      {
        $method_name='set'.str_replace('_', '', $property);
        $this->$method_name($this->DbSkip, false, false);
      }
    }

    if (is_array($options))
    {
      $this->setOptions($options);
    }
  }

  public function getMethodByProperty($property)
  {
    return strtolower(str_replace('_', '', $property));
  }

  public function setDbTable($dbTable)
  {
    if (is_string($dbTable))
    {
      $dbTable = new $dbTable();
    }
    if (!$dbTable instanceof Zend_Db_Table_Abstract)
    {
      throw new Exception('Invalid table data gateway provided');
    }
    $this->_dbTable = $dbTable;
    return $this;
  }

  /**
   *
   * @return Zend_Db_Table_Abstract
   */
  public function getDbTable()
  {
    if (null === $this->_dbTable)
    {
      $this->setDbTable($this->_dbClass);
    }
    return $this->_dbTable;
  }

  /*private function getPropertyName($property, $pvalue)
  {
    $name=null;
    if (is_string($property))
    {
      $name=$property;
    } else if (is_string($pvalue))
    {
      $name=$pvalue;
    } else if (is_array($pvalue) && isset($pvalue['name']))
    {
      $name=$pvalue['name'];
    }
    if (empty($name))
    {
      throw new Exception("Bad property definition!");
    }
    return $name;
  }*/

  protected function _getObjectProperty($property, $object)
  {
    $value=null;
    if (is_array($object))
    {
      $value=isset($object[$property])?$object[$property]:null;
    }
    else
    {
      //$value=property_exists($object, $property)?$object->$property:null;
      $value=isset($object->$property)?$object->$property:null;

    }
    return $value;
  }

  public static function convertRawToType($value, $type) {
    if (empty($type)) {
      return $value;
    }
    switch ($type)
    {
      case 'serialize':
        $value=unserialize($value);
        break;
      case 'timestamp':
        if (empty($value))
          $value=null;
        /*else
          $value=db_date_to_timestamp($value);*/
        break;
      case 'price':
        $value=filterPrice($value);
        if (empty($value))
          $value=0;
        break;
      case 'bool':
        $value=!!$value;
        break;
      case 'isodate':
        $value = toIsoDate($value);
        break;
      case 'address':
        $value=new Model_Address($value);
        break;
      default:
        throw new Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  protected function convertTypeToRaw($value, $type) {
    switch ($type)
    {
      case 'serialize':
        $_value=serialize($value);
        break;
      case 'timestamp':
        if (empty($value))
          $value=null;
        else
          //$value=db_timestamp_to_date($value);
          $value=date('c', toTimestamp ($value));
        break;
      case 'price':
        $value = filterPrice($value);
        if (empty($value))
          $value=0;
        break;
      case 'bool':
        $value=$value?'TRUE':'FALSE';
        break;
      case 'isodate':
        break;
      case 'address':
        if (!is_a($value, 'Model_Address')) {
          $value = new Model_Address($value);
        }
        //unserialize($var_value);
        $value = $value->getRawData();
        break;
      default:
        throw new Exception("Внутренняя ошибка: неподдерживаемый тип $type");
    }
    return $value;
  }

  protected function _convertParameterFromDb($name, $value) {
    if (isset($this->_parameters[$name]['type']) && !empty($this->_parameters[$name]['type'])) {
      return $this->convertRawToType($value, $this->_parameters[$name]['type']);
    }
    return $value;
  }

  protected function _setData($data) {
    //logVar($data, 'data for save');
    if ( !is_array($data) ) return false;
    foreach ($data as $k=>$v) {
      if (in_array($k, $this->_properties)) {
        $method = 'set'.$this->getMethodByProperty($k);
        //logVar($method, 'method for set');
        $this->$method($v);
      }
    }
  }

  protected function fill($data, $validate=true, $allow_draft=true, $fromDraft=false)
  {
    foreach ($this->_properties as $name)
    {
      $value=$this->_getObjectProperty($name, $data);

      if ( isset($this->_parameters['draft_property']) &&
           $name==$this->_parameters['draft_property']
         )
      {
        $value=unserialize($value);
        if (!is_array($value)) {
          $value=array();
        }
        if(is_array($this->_parameters)) {
          foreach ($this->_parameters as $k=>$v) {
            if (isset($this->_parameters[$k]['type'])) {
              switch ($this->_parameters[$k]['type']) {
                case 'address':
                  $value[$k]=new Model_Address($value[$k]);
                  break;
              }
            }
          }
        }
        $this->_tmp_draft=$value;
      }
      else
      {
        if (isset($this->_parameters[$name]['type']))
        {
          $value = self::convertRawToType($value, $this->_parameters[$name]['type']);
        }
        $method_name='set'.str_replace('_', '', $name);
        $this->$method_name($value, $validate, $allow_draft);
      }
    }
    if ($fromDraft)
    {
      $this->acceptDraft(false, false);
    }
    return $this;
  }

  protected function loadInternal($id, $fromDraft=false)
  {
    if (is_int($id) || is_string($id))
    {
      $result=$this->getDbTable()->find($id);
      if (0 == count($result))
      {
        logVar($result, "Failed ".get_class($this)." find($id)");
        return null;
      }
      $row = $result->current();
    }
    else
    {
      $row=$id;
    }
    /*logVar($this->_dbTable, 'db table');
    logVar($fromDraft, 'loadInternal draft');*/
    $this->from_draft = $fromDraft;
    return $this->fill($row, false, false, $fromDraft);
  }

  /*public function load($id)
  {
    return $this->loadInternal($id);
  }*/

  protected function _setValue($name, $value, $validate=true, $allow_draft=true, $allow_call=true)
  {
    $valname=strtolower($name);
    if ('_'==$valname[0])
    {
      $valname=substr($valname, 1);
    }
    $method = 'set' . $valname;
    if ( !method_exists($this, $method) || !$allow_call)
    {
      if ( in_array($valname, $this->_properties) )
      {

        if ($validate && !$this->_lazy_validate)
        {
          $this->_validateProperty($value, $valname);
        }
        if ( $allow_draft
             && $this->from_draft
             && isset($this->_parameters)
             && isset($this->_parameters['draft_property'])
             && isset($this->_parameters[$valname])
             && isset($this->_parameters[$valname]['draftable'])
           )
        {
          //$draft_name='_'.$this->_parameters['draft_property'];
          /* Проверка не даст загружать модели, надо что-то придумать или
           * переткнуть это в сохранение. Но в сохранение не очень кошерно —
           * будет падать слишком поздно, хрен найдешь где ставилось поле
          if (isset($this->_parameters[$valname]['require_role']) &&
              $this->_parameters[$valname]['require_role'] &&
              $this->_tmp_draft[$valname]!=$value
             )
          {
            checkPrivileges($this->_parameters[$valname]['require_role'], false);
          }*/
          $this->_tmp_draft[$valname]=$value;
        }
        else
        {
          $var_name='_'.$valname;
          /* Аналогично, см. выше
          if (isset($this->_parameters[$valname]['require_role']) &&
              $this->_parameters[$valname]['require_role'] &&
              $this->$var_name!=$value
             )
          {
            checkPrivileges($this->_parameters[$valname]['require_role'], false);
          }*/
          $this->$var_name=$value;
        }
        return $this;
      }
      throw new Exception('Invalid property: '.$name);
    }
    return $this->$method($value);
  }

  public function isDraftable()
  {
    return isset($this->_parameters['draft_property']);
  }

  public function isDrafted()
  {
    return $this->from_draft;
  }

  public function acceptDraft($validate=true, $final=true)
  {
    if (!$this->isDraftable())
    {
      throw new Exception('Этот объект не поддерживает черновики');
    }

    if ($final &&  $this->from_draft)
    {
      throw new Exception('Внутренняя ошибка: объект загружен как черновой, обновление невозможно', 500);
    }
    //$draft_name=$this->_parameters['draft_property'];
    //$this->$draft_name;

    if (!is_array($this->_tmp_draft))
    {
      return;
    }
    foreach($this->_tmp_draft as $var=>$value)
    {
      if (isset($this->_parameters[$var]['draftable']))
      {
        $this->_setValue($var, $value, $validate, false,true);
      }
    }
    //$this->$draft_name=array();
    if ($final)
    {
      $this->from_draft=false;
      //$this->_tmp_draft=array();
      $this->loadDependencities(false);

      foreach($this->_parameters as $prop=>$val)
      {
        if ( !is_array($val) || !isset($val['depends']) )
        {
          continue;
        }
        $data=$this->$prop;
        if ( is_array($data) && !empty($data) )
        {
          foreach ($data as $dep)
          {
            if (method_exists($dep, 'isDraftable') && $dep->isDraftable())
            {
              $dep->acceptDraft();
            }
          }
        }
      }
      $this->setDraft(null);
      $this->save();
    }
    //logVar($this, get_class($this)." accept draft");
    //logVar($this->_tmp_draft, get_class($this)." tmp draft");
  }

  public function clearDraft() {
    if (!isset($this->_parameters['draft_property']))
    {
      throw new Exception('Этот объект не поддерживает черновики');
    }
    if ($this->from_draft)
    {
      throw new Exception('Объект загружен как черновой, очистка черновика невозможна');
    }
    $this->_tmp_draft = null;
  }

  public function fillDraft()
  {
    if (!isset($this->_parameters['draft_property']))
    {
      throw new Exception('Этот объект не поддерживает черновики');
    }
    $draft_name=$this->_parameters['draft_property'];
    //$draft=$this->$draft_name;
    $draft=$this->_tmp_draft;
    foreach($this->_properties as $property)
    {
      if ( !isset($draft[$property]) )
      {
        $draft[$property]=$this->_getValue($property);
      }
    }
    return $draft;
  }

  public function __set($name, $value)
  {
    return $this->_setValue($name, $value, false, false, false);
  }

  protected function _getValue($name)
  {
    $valname=strtolower($name);
    if ('_'==$valname[0])
    {
      $valname=substr($valname, 1);
    }
    $method = 'get' . $valname;
    if ( !method_exists($this, $method))
    {
      if ( isset($this->_parameters[$valname]['depends']) && $this->_parameters[$valname]['depends'] )
      {
         // автоматом загружаем зависимость
        if ($valname!='draft_property' && null==$this->$valname )
        {
          $this->loadDependencity($valname, $this->from_draft);
        }
        return isset($this->$valname)?$this->$valname:null;
      }
      else if ( in_array($valname, $this->_properties) )
      {
        if ( $this->from_draft
             && isset($this->_parameters)
             && isset($this->_parameters['draft_property'])
             && isset($this->_parameters[$valname])
             && isset($this->_parameters[$valname]['draftable'])
             && isset($this->_tmp_draft[$valname])
           )
        {
          if ( (!isset($this->_parameters[$valname]['type']) || 'address'!=$this->_parameters[$valname]['type']) &&
              !( ($this->_tmp_draft[$valname] instanceof Model_Address) && $this->_tmp_draft[$valname]->is_empty())
             )
          {
            //logVar($this->_tmp_draft[$valname], 'Address (not empty)');
            return $this->_tmp_draft[$valname];
          }
        }
        $var_name='_'.$valname;
        return isset($this->$var_name)?$this->$var_name:null;
      }
      throw new Exception('Invalid property: '.$name);
    }
    return $this->$method();
  }

  public function __get($name)
  {
    return $this->_getValue($name);
  }

  public function __call($fname, $arguments)
  {
    if (preg_match('@^(set|get)(.+)$@i', $fname, $matches))
    {
      $name=strtolower($matches[2]);
      if ( isset($this->_methods_map[$name]) )
      {
        $property=$this->_methods_map[$name];
        if ('set'==strtolower($matches[1]))
        {
          if (count($arguments)<1)
          {
            throw new Exception("Set what? (in {$fname})");
          }
          $value=$arguments[0];
          $validation=true;
          $allow_draft=true;
          if ( isset($arguments[1]) && false===$arguments[1] )
          {
            $validation=false;
          }
          if ( isset($arguments[2]) && false===$arguments[2] )
          {
            $allow_draft=false;
          }
          return $this->_setValue($property, $value, $validation, $allow_draft);
        } else
        {
          return $this->_getValue($property);
        }
      }
    }
    throw new Exception('Invalid method call: '.$fname);
  }

  /**
   * Магические статические методы (работают только в PHP 5.3+!):
   *   fetchFieldByOtherField($v) — возвращает значение в ячейке Field у строчки, у которой в OtherField стоит значение $v
   *     Имена ячеек конвертируются в нижний регистр, а перед каждым блоком заглавных букв, ставится «_». Первая
   *     буква имени всегда считается как в нижнем регистре (поэтому нельзя получить имя ячейки, начинающееся с «_»)
   *     Функция всегда вызывает запрос в базу, поэтому выбирать следует только по тем колонкам, по которым есть индексы,
   *     также следует учитывать что будут проигнорированны не сохраненные в базу данные инстанциированных объектов
   *     Пример: $registry_number = Model_Procedure::fetchRegistryNumberById($some_id)
   * @param string $fname имя вызываемого метода
   * @param array $arguments аргументы
   * @return mixed
   */
  public static function __callStatic($fname, $arguments) {
    if (preg_match('@^fetch([a-zA-Z0-9]+)By([a-zA-Z0-9]+)$@i', $fname, $matches)) {
      $field_fetch = MyStrToLower(preg_replace('@([A-Z]+)@', '_$1', lcfirst($matches[1])));
      $field_by    = MyStrToLower(preg_replace('@([A-Z]+)@', '_$1', lcfirst($matches[2])));
      $db = getDbInstance();
      $className = get_called_class();
      $model = new $className(null);
      $table = $model->getDbTable();
      $table = $table->info('name');
      $select = $db->select()
                   ->from($table, array($field_fetch))
                   ->where("\"$field_by\"=?", $arguments[0]);
      return $db->fetchOne($select);
    }
    throw new Exception('Invalid method call: '.$fname);
  }

  public function setOptions(array $options)
  {
    $methods = get_class_methods($this);
    foreach ($options as $key => $value)
    {
      $method = 'set' . ucfirst($key);
      if (in_array($method, $methods))
      {
        $this->$method($value);
      }
    }
    return $this;
  }

  public function delete($from_draft=false, $recursive=true)
  {
    DbTransaction::start();
    try {
      $id = (int)$this->getPrimaryKeyValue();
      if ($id <= 0)
      {
        return;
      }
      if ($recursive)
      {
        $this->loadDependencities($this->from_draft);
        foreach ($this->_parameters as $prop=>$val)
        {
          if ( !is_array($val) || !isset($val['depends']) || empty($this->$prop) || !is_array($this->$prop))
          {
            continue;
          }
          foreach ($this->$prop as $k=>$dep)
          {
            if (method_exists($dep, 'delete'))
            {
              $dep->delete($from_draft, $recursive);
              if(isset($this->$prop) && ! empty($this->$prop)) {
                $tmp = & $this->$prop;
                unset($tmp[$k]);
              }
            }
          }
        }
      }
      $new_data = array();
      $pkey = $this->_getPrimaryKey();
      if ($from_draft)
      {
        $old_data['actual']=$this->getActual();
        $old_data['actual']=true;
        $this->_logChanges($new_data, $old_data, 'Черновое удаление записи');
        $this->getDbTable()->update(array('actual'=>'FALSE', 'date_removed'=>date('c')), array("$pkey = ?" => $id));
      }
      else
      {
        $old_data[$pkey]=$this->getPrimaryKeyValue();
        $old_data[$pkey]='';
        $this->_logChanges($new_data, $old_data, 'Удаление записи');
        $this->getDbTable()->delete(array("$pkey = ?" => $id));
      }
      DbTransaction::commit();
    } catch (Exception $e)
    {
      DbTransaction::rollback();
      throw $e;
    }
  }

  protected function _logChanges($new_data, $old_data, $comment=false)
  {
    if ( !property_exists($this, '_logTable') || empty($this->_logTable) )
    {
      return false;
    }

    $log_table=$this->_logTable;
    $date=db_timestamp_to_date(time());
    $table=$this->getDbTable();
    $id=$this->getPrimaryKeyValue();

    if (empty($new_data)) {
      return false;
    }

    foreach ($new_data as $field=>$new_value)
    {
      if (isset($this->_parameters)
          && isset($this->_parameters[$field])
          && (isset($this->_parameters[$field]['nolog']) )
         )
      {
        continue;
      }
      $new_value=to_string($new_value);
      $old_value=to_string($this->_getObjectProperty($field, $old_data));
      if ( $old_value != $new_value
           && !('FALSE'==$new_value && 0==$old_value) // фикс спец. случаев чтобы не попадало в логи
           && !('TRUE'== $new_value && 1==$old_value)
         )
      {
        $log_data=array();
        foreach ($this->_logFields as $logfield=>$logvalue)
        {
          switch ($logvalue)
          {
          case 'user':
            $log_data[$logfield]=getActiveUser();
            break;
          case 'field':
            $log_data[$logfield]=$field;
            break;
          case 'table':
            $log_data[$logfield]=$table->info('name');
            break;
          case 'date':
            $log_data[$logfield]=$date;
            break;
          case 'from':
            $log_data[$logfield]=$old_value;
            break;
          case 'to':
            $log_data[$logfield]=$new_value;
            break;
          case 'id':
            $log_data[$logfield]=$id;
            break;
          case 'comment':
            if ($comment)
            {
              $log_data[$logfield]=$comment;
            }
            break;
          default:
            $log_data[$logfield]=$logvalue;
            break;
          }
        }
        $this->getDbTable()->getAdapter()->insert($log_table, $log_data);
      }
    }
  }

  public function save($comment=false)
  {
    if (method_exists($this, 'validateSelf')) {
      $this->validateSelf();
    }
    try {
      DbTransaction::start();
      $data=array();
      foreach ($this->_properties as $name)
      {
        $var_name='_'.$name;
        if ($this->from_draft && isset($this->_parameters[$name]['draftable']) && $this->_parameters[$name]['draftable'])
        {
          continue;
        }
        $var_value=$this->$var_name;
        if ($this->_lazy_validate) {
          $this->_validateProperty($var_value, $name);
        }
        if (isset($this->_parameters[$name]['type']) && !empty($this->_parameters[$name]['type']))
        {
          $var_value = $this->convertTypeToRaw($var_value, $this->_parameters[$name]['type']);
        }
        if (isset($this->_parameters[$name]['nullify']) && $this->_parameters[$name]['nullify'] && ''==$var_value)
        {
          $var_value=null;
        }
        if ( !is_array($var_value) &&
             !(isset($this->_parameters['draft_property']) && $name==$this->_parameters['draft_property']))
        {
          if (is_bool($var_value))
          {
            $data[$name]=$var_value?'TRUE':'FALSE';
          }
          else
          {
            $data[$name]=$var_value;
          }
        }
        else
        {
          //logVar($name, get_class($this).'::fieldname');

          if ( isset($this->_parameters['draft_property']) &&
                 $name==$this->_parameters['draft_property']
               )
          {
            if (empty($this->_tmp_draft))
            {
              $data[$name]=null;
            }
            else
            {
              $draft = $this->_tmp_draft;
              foreach($this->_parameters as $k=>$v) {
                if (isset($v['type']) && !empty($v['type']) && isset($draft[$k]))
                {
                  switch ($v['type']) {
                    case 'address':
                      $vv = $draft[$k];
                      if (!is_a($vv, 'Model_Address')) {
                        $vv = new Model_Address($vv);
                      }
                      $draft[$k]=$vv->getRawData();
                      break;
                  }
                }
              }
              $data[$name]=serialize($draft);
            }
          }
          elseif (!empty($var_value))
          {
            {
              logVar($var_value, "Сохранение проперти $name");
              throw new Exception("Не могу сохранить array[".count($var_value)."]");
            }
          }
        }
      }
      $id=null;
      if (isset($data['id']))
      {
        $id=$data['id'];
      }
      //logVar($this->_tmp_draft, get_class($this).'::save draft');
//      logVar($data, get_class($this).'::save data');
      //logVar($this->_properties, get_class($this).'::save properties');

      if (empty($id))
      {
        unset($data['id']);
        //logVar($data, get_class($this).'::save data (new row)');
        $inserted_id = $this->getDbTable()->insert($data);
        $this->_id=$inserted_id;
      }
      else
      {
        //logVar($data, get_class($this).'::save data (update row)');
        $result=$this->getDbTable()->find($id);
        if (0 == count($result))
        {
          $inserted_id = $this->getDbTable()->insert($data);
          $this->_id=$inserted_id;
        }
        else
        {
          $row = $result->current();
          $this->_id = $id;
          $this->_logChanges($data, $row, $comment);
          $this->getDbTable()->update($data, array('id = ?' => $id));
        }
      }
      DbTransaction::commit();
    } catch (Exception $e)
    {
      DbTransaction::rollback();
      throw $e;
    }
  }

  /*
   * Превратить объекты в массиве $data в id этих объектов
   */
  static protected function clearIds($data)
  {
    foreach ($data as $key=>$val)
    {
      if (is_object($val))
      {
        $data[$key]=$val->getPrimaryKeyValue();
      }
    }
    return $data;
  }

  /**
   * Обновляем зависимость $field: удаляем все зависимые объекты, id которых нет в массиве $records
   */
  public function updateDependencity($field, $records, $from_draft=false)
  {
    //logVar($field, 'field name');
    //logVar($this->from_draft, 'draft');
    if (empty($field))
    {
      logCurrentTrace('Внутренняя ошибка: пустая пропертя');
      return;
    }
    if (!isset($this->_parameters[$field]['depends']))
    {
      //logVar($field, get_class($this).'::checkDependencities($field)');
      throw new Exception('Внутренняя ошибка: нет такой зависимости');
    }
    if (!isset($this->$field))
    {
      $this->loadDependencity($field, $from_draft);
    }
    //logVar($this->$field, get_class($this)."->updateDependency($field, [".join(', ', $records)."]) old values");
    foreach($this->$field as $key=>$dep)
    {
      $id = $dep->getPrimaryKeyValue();
      if ( !in_array($id, $records) )
      {
        $dep->delete($from_draft);
        $tmp = & $this->$field;
        unset($tmp[$key]);
      }
    }
  }

  protected function loadDependencity($prop, $from_draft=null)
  {
    if ( !isset($this->_parameters[$prop]['depends']) || !($this->_id))
    {
      return;
    }
    if (null===$from_draft)
    {
      $from_draft=$this->from_draft;
    }
    if ( !is_array($this->_parameters[$prop]['depends']) )
    {
      //logVar($this->_parameters[$prop]['depends'], get_class($this).'::loadDependencity');
      throw new Exception('Внутренняя ошибка', 500);
    }
    if ( empty($this->_parameters[$prop]['depends']) )
    {
      return;
    }

    $id = $this->getPrimaryKeyValue();
    if (!$id) {
      return;
    }
    $args = array();
    if (isset($this->_parameters[$prop]['depends']['args'])) {
      $args = $this->_parameters[$prop]['depends']['args'];
      unset($this->_parameters[$prop]['depends']['args']);
    }
    foreach($this->_parameters[$prop]['depends'] as $class=>$loader)
    {
      if (!class_exists($class)) {
        continue;
      }
      //Должно было быть:
      //$this->$prop=$class::$loader($this->_id);
      //Но такую конструкцию PHP не осиливает, поэтому изврат уже в пути
      $fname="$class::$loader";
      $this->$prop=call_user_func($fname, $id, $from_draft, $args);
      if (is_array($this->$prop))
      {
        foreach($this->$prop as $dep)
        {
          if (method_exists($dep, 'loadDependencities'))
          {
            $dep->loadDependencities($this->from_draft);
          }
        }
      }
    }
  }

  protected function _getPrimaryKey() {
    $key = $this->getDbTable()->info('primary');
    $key = array_shift($key);
    return $key;
  }

  protected function _getPrimaryKeyFn() {
    return $fn = 'get'.$this->getMethodByProperty($this->_getPrimaryKey());
  }

  public function getPrimaryKeyValue() {
    $fn = $this->_getPrimaryKeyFn();
    return $this->$fn();
  }

  public function loadDependencities($from_draft=null)
  {
    if ( !isset($this->_parameters) || !($this->getPrimaryKeyValue()))
    {
      return false;
    }
    if (null===$from_draft)
    {
      $from_draft=$this->from_draft;
    }
    foreach($this->_parameters as $prop=>$val)
    {
      if ( !is_array($val) || !isset($val['depends']) )
      {
        continue;
      }
      $data=$this->$prop;
      if (!empty($data))
      {
        continue;
      }
      /*logVar($this->_dbTable, 'db table');
      logVar($from_draft, 'loadDependencity draft');*/
      $this->loadDependencity($prop, $from_draft);
    }
  }

  public function humanizeValue($name, $value) {
    if (isset($this->_parameters[$name]['type'])) {
      $name = $this->_parameters[$name]['type'];
    }
    if (null===$value) {
      $value='';
    }
    switch ($name){
      case 'timestamp':
        return formatTimestamp($value);
      case 'price':
        $value = filterPrice($value);
        if (empty($value))
          $value=0;
        return HumanizePrice($value);
      case 'bool':
        return $value?'Да':'Нет';
        break;
      case 'address':
        if (!is_a($value, 'Model_Address') && is_array($value)) {
          $value = new Model_Address($value);
        }
        if (is_a($value, 'Model_Address')) {
          return  $value->__toString();
        }
      default:
        return $value;
    }
  }

  /**
   *
   * @param bool $collapsed указывает как возвращать результат.
   *  false - возвращать как есть в таблице, т.е. по отдельности строки изменений
   *  результат - массив, каждая строка которого строка лога
   *  true - возвращать только изменения между самым начальным значением и самым конечным
   *  результат - массив array('property'=>array('from'=>$from, 'to'=>$to), ...)
   * @param <type> $from_time
   * @param <type> $to_time
   * @return <type>
   */
  public function readLog($collapsed=false, $from_time=null, $to_time=null, $deps=false)
  {
    if ( !property_exists($this, '_logTable') || empty($this->_logTable) )
    {
      return false;
    }

    $db=getRegistryItem('db');
    $log_table=$this->_logTable;
    $select=$db->select()->from($log_table);
    $date_field=array_search('date', $this->_logFields);
    $id_field=array_search('id', $this->_logFields);
    $select=$select->where("$id_field = ?", $this->getPrimaryKeyValue());
    if ($from_time!==null)
    {
      if (is_int($from_time))
      {
        $from_time=db_timestamp_to_date($from_time);
      }
      $select=$select->where("$date_field >= ?", $from_time);
    }
    if ($to_time!==null)
    {
      if (is_int($to_time))
      {
        $to_time=db_timestamp_to_date($to_time);
      }
      $select=$select->where("$date_field <= ?", $to_time);
    }
    $select=$select->order("$date_field ASC");
    $rows = $db->fetchAll($select, array(), Zend_Db::FETCH_CLASS);
    $log_data=array();
    $field_field=array_search('field', $this->_logFields);
    $from_field=array_search('from', $this->_logFields);
    $to_field=array_search('to', $this->_logFields);
    $date_field=array_search('date', $this->_logFields);

    if ($collapsed)
    {
      foreach ($rows as $row)
      {
        if (!isset($log_data[$row->$field_field]))
        {
          $log_data[$row->$field_field]=array('from'=>$this->humanizeValue($row->$field_field, $row->$from_field));
        }
        $log_data[$row->$field_field]['to']=$this->humanizeValue($row->$field_field, $row->$to_field);
        $log_data[$row->$field_field]['id']=$row->$id_field;
        $log_data[$row->$field_field]['date']=$row->$date_field;
        $field_pseudo=$row->$field_field;
        if (isset($this->_parameters[$row->$field_field]['pseudo']))
        {
          $field_pseudo=$this->_parameters[$row->$field_field]['pseudo'];
        }
        $log_data[$row->$field_field]['pseudo']=$field_pseudo;
      }
    }
    else
    {
      foreach ($rows as $row)
      {
        $field_pseudo=$row->$field_field;
        if (isset($this->_parameters[$row->$field_field]['pseudo']))
        {
          $field_pseudo=$this->_parameters[$row->$field_field]['pseudo'];
        }
        $data = array (
          'field'=>$row->$field_field,
          'date'=>$row->$date_field,
          'pseudo'=>$field_pseudo,
          'from'=>$this->humanizeValue($row->$field_field, $row->$from_field),
          'to'=>$this->humanizeValue($row->$field_field, $row->$to_field),
          'user_id'=>$row->user_id
        );
        // @BUG необходимо использовать параметр nolog вместо такого костыля
        if(!in_array($row->field, array('date_added', 'date_last_update', 'added_by', 'updated_by',
                                        'draft', 'procedure_actual_object')))
        {
          $log_data[]=$data;//$row->toArray();
        }
      }
    }
    if ($deps)
    {
      $this->loadDependencities();
      foreach($this->_parameters as $prop=>$val)
      {
        if ( !is_array($val) || !isset($val['depends']) )
        {
          continue;
        }
        $data=$this->$prop;
        if (empty($data))
        {
          continue;
        }
        $deps_log=array();
        foreach($data as $key=>$dep)
        {
          if (method_exists($dep, 'readLog'))
          {
            $t = $dep->readLog($collapsed, $from_time, $to_time, $deps);
            if (is_array($t)) {
              $deps_log[$dep->getPrimaryKeyValue()]=$t;
            }
          }
        }
        if (!empty($deps_log))
        {
          $log_data[$prop]=array('depends'=>$deps_log);
        }
      }
    }
    return $log_data;
  }

  public function rollbackByLog($to_time) {
    $changed_properties = array();
    $history = $this->readLog(true, $to_time, null, false, false);
    foreach ($history as $property=>$log) {
      $method = 'set'.$this->getMethodByProperty($property);
      $value = $this->_convertParameterFromDb($property, $log['from']);
      $this->$method($value, false);
      $changed_properties[$property] = $log['date']?$log['date']:true;
    }
    return $changed_properties;
  }

  // Создать класс $class и заполнить его данными $rows
  static protected function constructByRows($rows, $class)
  {
    $data=array();
    /*if (empty($class))
    {
      $class=get_class();
    }*/
    foreach ($rows as $row)
    {
      $model=new $class(null);
      $model->fill($row, false, false);
      $data[]=$model;
    }
    return $data;
  }

  // выдаем все проперти в xml
  public function toXml($item_name=null)
  {
    $data=array();
    $params=array();
    foreach ($this->_properties as $name)
    {
      $var_name='_'.$name;
      $var_value=$this->$var_name;
      if ( !is_array($this->$var_name)
           && !(isset($this->_parameters['draft_property']) && $name==$this->_parameters['draft_property'])
           && !(isset($this->_parameters[$name]['xml_ignore']) && !$this->_parameters[$name]['xml_ignore'])
         )
      {
        if (is_bool($var_value))
        {
          $var_value=$var_value?'TRUE':'FALSE';
        }
        if (isset($this->_parameters[$name]['xml_attr']) && $this->_parameters[$name]['xml_attr'])
        {
          $param[$name]=$var_value;
        }
        else
        {
          $data[$name]=$var_value;
        }
      }
    }
    $s='';
    foreach ($data as $var=>$val)
    {
      $var=strtolower($var);
      if (false!==strpos($val, '<') )
      {
        $val="<![CDATA[$val]]>";
      }
      $s.="<$var>$val</$var>";
    }
    if (empty($item_name))
    {
      $item_name=strtolower(get_class($this));
    }
    if (!empty($param))
    {
      $p='';
      foreach($param as $var=>$val)
      {
        $val=str_replace('"', '&quot;', $val);
        $val=str_replace("'", '&apos;', $val);
        $p.=" $var=\"$val\"";
      }
    }
    return "<{$item_name}{$p}>{$s}</{$item_name}>";
  }

  // Преобразовываем проперти в массив
  public function toArray($recursive=false)
  {
    $data=array();
    foreach ($this->_properties as $name)
    {
      if ( !(isset($this->_parameters['draft_property']) && $name==$this->_parameters['draft_property']) )
      {
        $method='get'.$this->getMethodByProperty($name);
        $var_value=$this->$method();
        $data[$name]=$var_value;
      }
    }
    if ($recursive)
    {
      foreach ($this->_parameters as $valname => $value)
      {
        if (isset($value['depends']) && $value['depends'])
        {
          $data[$valname]=array();
           // автоматом загружаем зависимость
          if (null==$this->$valname && $valname!='draft_property')
          {
            $this->loadDependencity($valname, $this->from_draft);
          }
          if($valname!='draft_property') {
            foreach($this->$valname as $val)
            {

              if(is_object($val)) {
                $data[$valname][]=$val->toArray(true);
              } else {
                logVar($val, 'val '.$valname.' not an object');
                $data[$valname][]=array();
              }
            }
          }
        }
      }
    }
    return $data;
  }

  protected function _validate($data, $name, $validators, $params=array())
  {
    self::_validateStatic($data, $name, $validators, $params);
  }

  protected function _validateProperty($value, $property) {
    if (isset($this->_parameters)
        && isset($this->_parameters[$property])
        && isset($this->_parameters[$property]['validators']))
    {
      $p=$this->_parameters[$property];
      //$validator = new Core_DataValidation();
      $params=isset($p['params'])?$p['params']:array();
      if ( is_array($value) && empty($value) ) {
        $v=null;
      } else {
        $v=$value;
        if ( !is_int($value) && !is_float($value) && !is_null($value) )
        {
          $v=(string)$value;
        }
      }
      $n=isset($p['pseudo'])?$p['pseudo']:$p['name'];
      $this->_validate($v, $n, $p['validators'], $params);
    }
  }

  static protected function _validateStatic($data, $name, $validators, $params=array())
  {
    $validator = new Core_DataValidation();
    $validator->validateData($validators, $data, $params);
    if ( count($validator->getErrors()) )
    {
      $text=join("<br/>\n", $validator->getErrors());
      $text=str_replace('%fieldname%', $name, $text);
      throw new ResponseException("Неправильный ввод: $name '$data':<br/>\n$text", 400);
      //throw new Exception("Неправильный ввод: $name '$data'", 400);
    }
  }

  public function listProperties() {
    return $this->_properties;
  }
  public function listParameters() {
    return $this->_parameters;
  }

  public function findBy($params) {
    $table = $this->getDbTable();
    $select = $table->select();
    foreach ($params as $name=>$value) {
      if (in_array($name, $this->_properties)) {
        $select->where("$name = ?", $value);
      }
    }
    $limits = createSortLimitFromPost($params);
    if ( !empty($limits['order']) ) {
      $select->order($limits['order']);
    }
    if ( !empty($limit['limit']) ) {
      $select->limit($limits['limit'], $limits['offset']);
    }
    //logVar($select->__toString());
    return $table->fetchAll($select, Zend_Db::FETCH_ASSOC);
    //makeFetchSelector($select, $params, $modes);
  }


  public function hasProperty($p) {
    return in_array($p, $this->_properties);
  }



  /**
   * Возвращает объект таблицы для использования в других методах.
   * Призвана заменить метод _getDbTable(), существующий отдельно в каждой модели.
   * WARN: php 5.2 uncompatibable!
   * До перехода на 5.3 отключена.
   * @access protected
   * @return Zend_Db_Table
   */
  /*
  static protected function _getDbTable()
  {
    $className = get_called_class();
    $model = new $className(null);
    return $model->getDbTable();
  } // _getDbTable
  */


  /**
   * Загрузка из БД по критерию и создание объектов модели.
   *
   * @param Array $params
   * @param Boolean $asArray (Optional) Возвращать массив вместо объектов модели.
   * На данный момент всегда true, реализация на моделях в fill()
   * @param Boolean $getCount возвращать число записей. Если установлено в true,
   * то вернет массив вида ($rows, $count), где $rows — записи. Если false, то
   * вернет просто $rows
   * @return Array
   */
  static public function loadLike($params, $asArray = true, $getCount = false)
  {
    $className = get_called_class();

    if ( !is_array($params) )
      throw new Exception("Passed parametres is not an array");

    $defaults = array(
      'where'   => null,
      'limit'   => null,
      'skip'    => 0,
      'sort'    => null,
      'dir'     => 'ASC',
      'columns' => null,
      'deps'    => array(),
      'parents' => array()
    );
    $params = array_merge( $defaults, array_intersect_key($params, $defaults) );

    extract($params, EXTR_OVERWRITE);

    $db = getDbInstance();
    //$table = $className::_getDbTable();
    // PHP 5.2 fix:
    //$table = call_user_func( array($className, '_getDbTable') );
    $model = new $className(null);
    $table = $model->getDbTable();

    //$select = $table->select();

    $select = $db->select();

    if ( !empty($columns) && is_array($columns) ) {
      $select->from( array( $table->info('name') => $table->info('name') ), $columns);
    } else {
      $select->from($table->info('name'));
    }

    // WARN: temporarily disabled
    if ( !empty($deps) && is_array($deps) ) {
      $tableDeps = $table->getDependentTables();
      //getRegistryItem('logger')->debug( $tableDeps );

      foreach ($deps as $k => $dep) {
        if ( !in_array($dep, $tableDeps) )
          continue;

        try {
          $dep = new $dep();
          $ref = $dep->getReference( get_class($table) );
        } catch (Exception $e) {
          continue;
        }

        try {

          $depName = $dep->info('name');
          $columns = $dep->info('cols');
          $columns = array_combine($columns, $columns);
          foreach ($columns as &$colName) {
            $colName = $depName . '_' . $colName;
          }
          $columns = array_flip($columns);

          // WARN: Поддерживается связь только через одно поле пока что.
          $select->joinFull(
            array($depName => $depName),            // ex.: 'lots' => 'lots'
            $table->info('name') . '.' . $ref['refColumns'][0] . ' = '
            . $depName . '.' . $ref['columns'][0],  // ex.: procedures.id = lots.procedure_id
            $columns
          );

        } catch (Exception $e) {
          getRegistryItem('logger')->debug( $e );
          continue;
        }
      } // foreach ($deps)
    } // if !empty($deps)

    try {

      if ( !empty($where) )
        $select->where( strval($where) );

      if ( !isset($dir) || !preg_match('/^(asc|desc)$/i', $dir) )
        $dir = 'ASC';
      if ( !empty($sort) )
        $select->order( $sort . ' ' . strtoupper($dir) );

      /*if ( !empty($limit) )
        $select->limit( intval($limit), intval($skip) );*/

      //getRegistryItem('logger')->debug( $select->__toString() );

      //$rows = $table->fetchAll($select);
      if ( !empty($limit) ) {
        list($rows, $count) = getPagerData($select, intval($skip), intval($limit), !$getCount);
      } else {
        $rows = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
        $count = count($rows);
      }

    } catch (Exception $e) {
      getRegistryItem('logger')->debug( $e );
      return array();
    }

    //return $className::_parseRowSet($rows, $deps);
    // PHP 5.2 fix:
    $rows = call_user_func( array($className, '_parseRowSet'), $rows, $deps );
    if ($getCount) {
      return array($rows, $count);
    }
    return $rows;
  } // loadLike


  /**
   * Преобразует rowset в массив моделей.
   * Рекурсивный для зависимостей.
   *
   * @access protected
   * @param Array/Zend_Db_Table_Rowset $rowset
   * @param Array $deps (Optional)
   * @param Boolean $asArray (Optional)
   * @return Array
   */
  static protected function _parseRowSet($rowset, $deps = false, $asArray = true)
  {
    $className = get_called_class();
    $items = array();

    if (count($rowset) > 0) {
      foreach ($rowset as $row) {
        if ($asArray) {
          $item = is_array($row) ? $row : $row->toArray();
        } else {
          // WARN: Not implemented yet.
          return false;
          $model = new $relatedToRowModelClassName();
          $item = $model->setFromRow($row);
        }


        if ( !empty($deps) && is_array($deps) ) {
          foreach ($deps as $k => $dep) {
            try {
              if ( is_array($row) )
                continue;

              $depRows = $row->findDependentRowset($dep);
              // TODO: In the models version setters testing needed.
              // TODO: Nested dependencies not implemented yet.
              //$item[$k] = $className::_parseRowSet($depRows);
              // PHP 5.2 fix:
              $item[$k] = call_user_func( array($className, '_parseRowSet'), $depRows );

            } catch (Exception $e) {
              getRegistryItem('logger')->debug( $e );
              continue;
            }
          }
        }


        $items[] = $item;
      }
    }

    return $items;
  } // _parseRowSet


  /**
   * Создание объекта модели из объекта строки БД.
   * Максимально упрощённый альтернативный fill’у метод.
   *
   * TODO: Обработка зависимостей.
   *
   * @param Zend_Db_Table_Row $row
   * @return $this
   */
  public function setFromRow(Zend_Db_Table_Row $row)
  {
    if ( !($row instanceof Zend_Db_Table_Row) )
      return false;

    //$methods = get_class_methods($this);

    foreach ($row as $key => $value) {
      if (isset($this->_parameters[$key]['type']))
      {
        $value = self::convertRawToType($value, $this->_parameters[$key]['type']);
      }
      $this->_setValue($key, $value, false);
    }

    return $this;
  } // setFromRow

  /**
   * Копирует все поля кроме id, драфта (если тот есть) и зависимых полей в объект $destination
   *
   * @param Core_Mapper $destination
   *
   * @return Core_Mapper $destination
   */
  public function copyValues($destination) {
    foreach ($this->_properties as $property) {
      if ('id'==$property
          || (isset($this->_parameters['draft_property']) && $property==$this->_parameters['draft_property'])
          || (isset($this->_parameters[$property]['depends']) && $this->_parameters[$property]['depends'])
         )
      {
        continue;
      }
      $destination->_setValue($property, $this->_getValue($property));
    }
    return $destination;
  }

  /**
   * Дублирует объект
   *
   * @param Core_Mapper $owner
   * @param array $owner_link_vars
   * @param bool $recursive
   */
  public function duplicate($owner, $recursive=true, $int_param=null) {
    $this_class = get_class($this);
    /* @var $new_object Core_Mapper */
    $new_object = new $this_class();
    $this->copyValues($new_object);
    if (isset($this->_owner_link_map)) {
      foreach ($this->_owner_link_map as $src=>$link) {
        $new_object->_setValue($link, $owner->_getValue($src));
      }
    }
    if (method_exists($new_object, '_prepareDuplicate')) {
      $new_object->_prepareDuplicate($owner, $this, $int_param);
    }
    $new_object->save();

    $dep_map = array();
    if ($recursive) {
      foreach ($this->_parameters as $param=>$opts) {
        if (!isset($opts['depends']) || !$opts['depends']
            || (isset($opts['ignore_duplicate']) && $opts['ignore_duplicate']))
        {
          continue;
        }
        $dep_map[$param] = array();
        $depends = $this->_getValue($param);
        if (!is_array($depends)) {
          continue;
        }
        foreach ($depends as $dep) {
          list($new_dep, $new_dep_map) = $dep->duplicate($new_object, $recursive, $int_param);
          $dep_map[$param][$dep->getId()] = array('new_id'=>$new_dep->getId(), 'dep_map'=>$new_dep_map);
        }
      }
    }

    return array($new_object, $dep_map);
  }


} // Core_Mapper
