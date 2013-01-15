<?php
class Core_Validate_Digits extends Zend_Validate_Digits
{
    
    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value only contains digit characters
     *
     * @param  string $value
     * @return boolean
     */
    public function isValid($value)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            $this->_error(parent::INVALID);
            return false;
        }

        $this->_setValue((string) $value);

        if ('' === $this->_value) {
            return true;
        }

        if (null === self::$_filter) {
            self::$_filter = new Zend_Filter_Digits();
        }

        if ($this->_value !== self::$_filter->filter($this->_value)) {
            $this->_error(parent::NOT_DIGITS);
            return false;
        }

        return true;
    }

}
