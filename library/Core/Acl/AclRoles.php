<?php

class Core_Acl_AclRoles extends Zend_Db_Table_Abstract {
	/**
	 * The default table name 
	 */
	protected $_name = 'acl_roles';
    
    public function getRoleIds() {
        $roles = $this->getAdapter()->fetchPairs('select id, role from "'.$this->_name.'"');
        return array_keys($roles);
    }

}
