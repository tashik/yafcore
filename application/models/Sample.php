<?php
/**
 * @name SampleModel
 * @desc sample data class can access databases, files, and other system
 * @author tashik
 */
class SampleModel {
    public function __construct() {
    }   
    
    public function selectSample() {
        return 'Hello World!';
    }

    public function insertSample($arrInfo) {
        return true;
    }
}
