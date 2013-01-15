<?php

class Core_Auth extends Zend_Auth
{
  /**
     * Redefined authenticate method to prevent storage before session cookie is set up
	 * Authenticates against the supplied adapter
     *
     * @param  Zend_Auth_Adapter_Interface $adapter
     * @return Zend_Auth_Result
     */
    public function authenticate(Zend_Auth_Adapter_Interface $adapter)
    {
        $result = $adapter->authenticate();

        /**
         * ZF-7546 - prevent multiple succesive calls from storing inconsistent results
         * Ensure storage has clean state
         */ 
        if ($this->hasIdentity()) {
            $this->clearIdentity();
        } 

        return $result;
    }
}
