<?php

class DBSchema {
  public static function getTables($view = 'public') {
    $db = getRegistryItem('db');
    $select = $db->select()->from(array('c'=>'pg_class'), array('oid', 'relname'))
                 ->join(array('n'=>'pg_namespace'), 'n.oid=c.relnamespace', array())
                 ->where('n.nspname=?', $view)
                 ->where("c.relkind='r'");
    return $db->fetchPairs($select);
  }

  public static function getProcedures($view = 'public') {
    $db = getRegistryItem('db');
    $select = $db->select()->from(array('p'=>'pg_proc'), array('oid', 'proname'))
                 ->join(array('n'=>'pg_namespace'), 'n.oid=p.pronamespace', array())
                 ->where('n.nspname=?', $view);
    return $db->fetchPairs($select);
  }

  public static function getProcedureInfo($oid) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = getRegistryItem('db');
    $select = $db->select()->from(array('p'=>'pg_proc'), array())
                 ->join(array('l'=>'pg_language'), 'l.oid=p.prolang', array('language'=>'lanname'))
                 ->columns(array(
                     'arguments'=>new Zend_Db_Expr('pg_get_function_arguments(p.oid)'),
                     'return'=>new Zend_Db_Expr('pg_get_function_result(p.oid)'),
                     'definition'=>new Zend_Db_Expr('pg_get_functiondef(p.oid)'),
                   ))
                 ->where('p.oid=?', $oid);
    $func = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
    if (!count($func)) {
      return null;
    }
    $func = $func[0];
    $func['definition'] = mb_trim(preg_replace('@^.*\$function\$(.+)\$function\$.*@is', '$1', $func['definition']));
    return $func;
  }

  public static function getTableInfo($oid) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = getRegistryItem('db');

    $result = array();

    $descr = self::_getComment($oid);
    if ($descr) {
      $result['comment'] = $descr;
    }

    $result['columns'] = self::_getColumns($oid);

    $indexes = self::_getIndexes($oid);
    if (!empty($indexes)) {
      $result['indexes'] = $indexes;
    }
    $triggers = self::_getTriggers($oid);
    if (!empty($triggers)) {
      $result['triggers'] = $triggers;
    }
    return $result;
  }

  protected static function _getComment($oid) {
    $db = getRegistryItem('db');

    return $db->fetchOne("SELECT description FROM pg_description WHERE objoid=?", array($oid));
  }

  protected static function _getIndexes($oid) {
    $db = getRegistryItem('db');
    $result = array();
    $idxselect = $db->select()->from(array('i'=>'pg_index'), array('indnatts', 'indisunique', 'indkey', 'indclass', 'indoption', 'indexprs'))
                    ->join(array('c'=>'pg_class'), 'i.indexrelid=c.oid', array('relname'))
                    ->join(array('am'=>'pg_am'), 'c.relam=am.oid', array('amname'))
                    ->columns(array('inddef'=> new Zend_Db_Expr("pg_catalog.pg_get_indexdef(i.indexrelid, 0, true)")))
                    ->where('i.indrelid=?', $oid)
                    ->where('i.indisprimary=false')
                    ->where("c.relkind='i'");
    $indexes = $db->fetchAll($idxselect, array(), Zend_Db::FETCH_ASSOC);
    if (count($indexes)) {
      foreach ($indexes as $index) {
        $idx = array(
          '@name' =>$index['relname'],
          'type'  =>$index['amname'],
        );
        if (preg_match('@^[^(]*\((.+)\)$@', $index['inddef'], $expr)) {
          $idx['expression'] = $expr[1];
        }
        /*if (null!==$index['indkey']) {
          $idx['columns'] = array();
          $index['indkey'] = explode(' ', $index['indkey']);
          $index['indclass'] = explode(' ', $index['indclass']);
          foreach ($index['indkey'] as $i=>$key) {
            if ($key) {
              $expr = $db->fetchOne("SELECT attname FROM pg_attribute WHERE attnum=? AND attrelid=?", array($key, $oid));
            } else {
              $expr = $index['inddef'];
            }
            $idx['columns'][] = array(
              'expr'=>$expr,
              'options' => $db->fetchOne("SELECT opcname FROM pg_opclass WHERE oid=?", array($index['indclass'][$i]))
            );
          }
        }*/
        if ($index['indisunique']) {
          $idx['constraints'] = 'unique';
        }
        $result[] = $idx;
      }
    }
    return $result;
  }

  protected static function _getColumns($oid) {
    $db = getRegistryItem('db');

    $result = array();

    $select = $db->select()->from(array('c'=>'pg_attribute'), array('attname', 'atttypmod', 'attnotnull', 'attnum'))
                 ->join(array('t'=>'pg_type'), 't.oid=c.atttypid', array('typname'))
                 ->joinLeft(array('d'=>'pg_attrdef'), "d.adrelid=c.attrelid and d.adnum=c.attnum", array('adsrc'))
                 ->joinLeft(array('descr'=>'pg_description'), 'descr.objoid=c.attrelid AND descr.objsubid=attnum', array('description'))
                 ->columns(array('is_primary'=>new Zend_Db_Expr("(SELECT COUNT(*) FROM pg_constraint WHERE contype='p' AND conrelid=c.attrelid AND c.attnum=ANY(conkey) AND array_upper(conkey,1)=1)>0")))
                 //->columns(array('is_unique'=>new Zend_Db_Expr("(SELECT COUNT(*) FROM pg_constraint WHERE contype='u' AND conrelid=c.attrelid AND c.attnum=ANY(conkey) AND array_upper(conkey,1)=1)>0")))
                     // т.к. UNIQUE констрейн обязательно тянет за собой соответствующий индекс, у которого тоже будет констрейн
                 ->columns(array('references'=>new Zend_Db_Expr("(SELECT COUNT(*) FROM pg_constraint WHERE contype='f' AND conrelid=c.attrelid AND c.attnum=ANY(conkey) AND array_upper(conkey,1)=1)>0")))
                 ->where('c.attrelid=?', $oid)
                 ->where('c.attisdropped=false')
                 ->where('c.attnum>0')
                 ->order('c.attnum');
    $types_map = array(
      'bpchar' => 'character',
      'int2' => 'smallint',
      'int4' => 'integer',
      'int8' => 'bigint',
      'float8' => 'double precision',
      'bool' => 'boolean',
      'timestamptz' => 'timestamp with time zone'
    );
    $actions_map = array(
      'a' => 'no action',
      'r' => 'restrict',
      'c' => 'cascade',
      'n' => 'set null',
      'd' => 'set default',
    );


    $cols = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
    foreach ($cols as $col) {
      $column = array(
        '@name' => $col['attname'],
      );
      if ($col['description']) {
        $column['comment'] = $col['description'];
      }

      $column['type'] = isset($types_map[$col['typname']]) ? $types_map[$col['typname']] : $col['typname'];

      if ($col['atttypmod'] && $col['atttypmod']>4) {
        $column['type'] = array ('@length' => intval($col['atttypmod'])-4, '@@value'=>$column['type']);
      }
      if (preg_match("@^nextval\('[a-zA-Z0-9_]+'::regclass\)$@", $col['adsrc'])) {
        if ('integer'==$column['type']) {
          $col['adsrc'] = false;
          $column['type'] = 'serial';
        } elseif ('bigint'==$column['type']) {
          $col['adsrc'] = false;
          $column['type'] = 'bigserial';
        }
      }
      if (isset($column['type']['@@value']) && 'numeric'==$column['type']['@@value'] && $column['type']['@length']) {
        $column['type']['@precision'] = ($column['type']['@length'])%65536;
        $column['type']['@length'] = floor(($column['type']['@length'])/65536);
      }

      if (false!==$col['adsrc'] && null!==$col['adsrc']) {
        $column['default'] = $col['adsrc'];
      }
      $constraint = array();
      if ($col['attnotnull']) {
        $constraint[] = 'not-null';
      }
      if ($col['is_primary']) {
        $constraint[] = 'primary';
      }
      /*if ($col['is_unique']) {
        $constraint[] = 'unique';
      }*/
      if ($constraint) {
        $column['constraint'] = join(' ', $constraint);
      }
      if ($col['references']) {
        $refselect = $db->select()->from(array('c'=>'pg_constraint'), array('conname', 'confupdtype', 'confdeltype'))
                        ->join(array('cl'=>'pg_class'), 'c.confrelid = cl.oid', array('reftable'=>'relname'))
                        ->join(array('at'=>'pg_attribute'), 'at.attnum = ANY(c.confkey) AND at.attrelid=c.confrelid', array('refcol'=>'attname'))
                        ->where("c.contype='f'")
                        ->where('?=ANY(c.conkey)', $col['attnum'])
                        ->where('conrelid=?', $oid);
        $refs = $db->fetchAll($refselect, array(), Zend_Db::FETCH_ASSOC);
        if ($refs) {
          $refs = $refs[0];
          $column['references'] = array(
            '@name'=>$refs['conname'],
            'table'=>$refs['reftable'],
            'column'=>$refs['refcol'],
            'on-delete'=>$actions_map[$refs['confdeltype']],
            'on-update'=>$actions_map[$refs['confupdtype']],
          );
        }
      }
      $result[] = $column;
    }
    return $result;
  }

  protected static function _getTriggers($oid) {
    /* @var $db Zend_Db_Adapter_Abstract */
    $db = getRegistryItem('db');

    $result = array();

    $select = $db->select()->from(array('t'=>'pg_trigger'))
                 ->join(array('p'=>'pg_proc'), 't.tgfoid=p.oid', array('proname'))
                 ->join(array('l'=>'pg_language'), 'p.prolang=l.oid', array())
                 ->columns(array('definition'=>new Zend_Db_Expr('pg_get_triggerdef(t.oid)')))
                 ->where('l.lanname!=?', 'internal')
                 ->where('t.tgrelid=?', $oid);
    $triggers = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
    foreach ($triggers as $trigger) {
      if (!preg_match('@CREATE.* TRIGGER.* (before|after) (.+) ON .*FOR EACH (row|statement).* EXECUTE\s+PROCEDURE\s+\S+\s*\((.*)\)@i', $trigger['definition'], $tginfo)) {
        //echo "failed match {$trigger['definition']}!\n";
        continue;
      }
      $events = strtolower($tginfo[2]);
      $events = join(' ', explode(' or ', $events));
      $info = array(
        '@name' => $trigger['tgname'],
        'type' => strtolower($tginfo[1]),
        'events' => $events,
        'each' => strtolower($tginfo[3]),
        'procedure' => $trigger['proname'],
        //'def' => $trigger['definition'],
      );
      if ('D'==$trigger['tgenabled']) {
        $info['@disabled'] = '1';
      }
      $args = mb_trim($tginfo[4]);
      if ($args) {
        $info['arguments'] = $args;
      }
      $result[] = $info;
    }
    return $result;
  }

  public static function getSchemaXML($save_path = null) {
    $out_opts = array('xmlns'=>array('db'=>'http://ololo.cc/files/database.xsd'),
                  'xmltag'=>true, 'type'=>false, 'ns'=>'db', 'pretty_print'=>true);

    $schema = array('tables'=>array());

    foreach (DBSchema::getTables() as $oid=>$table) {
      $info = DBSchema::getTableInfo($oid);
      $info['@name'] = $table;
      $schema['tables'][] = $info;
      if ($save_path) {
        file_put_contents("$save_path/$table.xml",
                          toXML('table', $info, array('xmltag'=>true, 'type'=>false, 'pretty_print'=>true)));
      }
    }

    foreach (DBSchema::getProcedures() as $oid=>$procedure) {
      if (!isset($schema['procedures'])) {
        $schema['procedures'] = array();
      }
      $info = DBSchema::getProcedureInfo($oid);
      $info['@name'] = $procedure;
      $info['@language'] = $info['language'];
      unset($info['language']);
      $schema['procedures'][] = $info;
      if ($save_path) {
        file_put_contents("$save_path/proc_$procedure.xml",
                          toXML('procedure', $info, array('xmltag'=>true, 'type'=>false, 'pretty_print'=>true)));
      }
    }

    return toXML('dbschema', $schema, $out_opts);
  }

  public static function getSavedSchemaXML($save_path) {
    $xmls = glob("$save_path/*.xml");
    $overall_xml = array('<?xml version="1.0" encoding="UTF-8"?>',
                         '<db:dbschema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:db="http://ololo.cc/files/database.xsd" xsi:schemaLocation="http://ololo.cc/files/database.xsd http://ololo.cc/files/database.xsd">');
    $tables_xml = array();
    $procedures_xml = array();
    foreach ($xmls as $xml) {
      $content = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', file_get_contents($xml));
      if (preg_match('@/proc_@', $xml) && false!==strpos($content, '<procedure ')) {
        $procedures_xml[] = $content;
      } else {
        $tables_xml[] = $content;
      }
    }
    $overall_xml[] = '<tables>';
    $overall_xml[] = join("\n", $tables_xml);
    $overall_xml[] = '</tables>';
    if (!empty($procedures_xml)) {
      $overall_xml[] = '<procedures>';
      $overall_xml[] = join("\n", $procedures_xml);
      $overall_xml[] = '</procedures>';
    }
    $overall_xml[] = '</db:dbschema>';
    return join("\n", $overall_xml);
  }
}
