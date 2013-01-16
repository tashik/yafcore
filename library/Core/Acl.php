<?php

class Core_Acl extends Zend_Acl
{
  public function __construct()
  {
    /*$res = new Core_Acl_AclResources();
    $r = new Core_Acl_AclRoles();
    $roles = getRegistryItem('roles');
    //$roles = $r->fetchAll();

    $cache = getRegistryItem('cache');

    $this->_addRoles($roles);
    if (!($modules = $cache->load('modules'))) {
        $modules = $res->getAdapter()->fetchAll('select DISTINCT "module", "id" from acl_resources where "parentId"=0');
        $cache->save($modules, 'modules');
    }
    foreach($modules as $m) {
        if (!($controllers = $cache->load('controllers_'.$m->id))) {
        $controllers = $res->getAdapter()->fetchCol('select DISTINCT "controller" from acl_resources where "parentId"='.$m->id.' AND "controller" IS NOT NULL');
        $cache->save($controllers, 'controllers_'.$m->id);

        }

        $perm = new Model_Permissions('roles', '', $m->id);
        $resources = $perm->getResourceList();
		//Zend_Debug::dump($resources);
        if(!$this->has($m->module))
          $this->add(new Zend_Acl_Resource($m->module));
        foreach($controllers as $k=>$c) {
             $this->add(new Zend_Acl_Resource($m->module.':'.$c), $m->module);
        }
        foreach($roles as $key=>$val) {
          $perm->loadPermissions($key);
          $permissions = $perm->getPermissions();
          //if($key==1) logVar($permissions, 'PERMISSIONS');
          foreach($permissions as $k=>$v) {
            if(isset($resources[$k]) && $v==1) {
            	$resInfo = $resources[$k];
            	list($contr, $act) = explode(' / ',$resInfo);
            	$this->allow($val, $m->module.":".$contr, $act);
            	unset($resInfo);
            }
          }
          unset($permissions);
        }
    }*/
  }

	protected function _addRoles($roles)
	{
		foreach ($roles as $key=>$val) {
			if (!$this->hasRole($val)) {
				$this->addRole(new Zend_Acl_Role($val));
			}
		}
	}
}
