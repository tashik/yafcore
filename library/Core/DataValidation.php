<?php
require_once('Util/validate_email.php');
require_once('Util/idna_convert.class.php');
class Core_DataValidation
{
  protected $values;
  protected $params;
  private  $errors;
  protected $valid=true;

  /**
    * this method assumes you have registered the post data
    * it loads each of the fields from the current table and sets
    * the data hash with the unvalidated data
    *  Может понадобиться для мяппинга - в другом классе
    */
  private function loadPost()
  {
      foreach ($this->_cols as $col) {
          if (isset($_POST[$col])) {
              $this->data[$col] = $_POST[$col];
          }
      }
  }
  /**
    * Available validators: Required, Number, Digits, Regex, Length, Integer, Email, Password
    * For password $values = array("password"=>"blabla", "minlength"=>5, "passwordConfirm"=>"blabla")
    * For string length $values = array("colname"=>"value", "min"=>1, "max"=>10)
    * For regex $values = array("regex"=>"expression", "colname"=>"value")
    * For others $values = array("colname"=>"value")
    */
  public function validateData($validators, $value, $params)
  {
    $this->data = $value;
    $this->params = $params;
    if(count($validators)) {
    foreach($validators as $val)
      {
        $validateFunction = '_validate' . $val;
        if (!method_exists($this, $validateFunction))
        {
          throw new Exception("Cannot validate: unknown validator '$val' for '$value'");
        }
        if (!$this->$validateFunction()) return false;
      }
    }
  }

  /**
    * gets the errors array
    */
  public function getErrors()
  {
      return $this->errors;
  }

  /**
    * validates that each key in the required array exists
    *
    */
  private function _validateRequired()
  {
    if ($this->data === '') {
      $this->errors['required'] = 'Поле %fieldname% обязательно для заполнения';
      $this->valid = false;
    }
    return $this->valid;
  }

  /**
    * throws an error if any of the fields are not valid numbers
    *
    */
  private function _validateNumber()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Zend_Validate_Float();

    if (!$validator->isValid($this->data)) {
      $this->errors['float'] = 'В поле %fieldname% требуется числовое значение';
      $this->valid = false;
    }
    return $this->valid;
  }

  private function _validateDigits()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Core_Validate_Digits();

    if (!$validator->isValid($this->data)) {
      $this->errors['digits'] = 'В поле %fieldname% вводите только цифры';
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validateRegex()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Zend_Validate_Regex($this->params['regex']);

    if (!$validator->isValid($this->data)) {
      $this->errors['regex'] = 'Поле %fieldname% заполнено некорректно';
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validateDate()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    if(!isset($this->params['format']))
      $format = 'YYYY-MM-DD';
    else
      $format = $this->params['format'];
      $validator = new Zend_Validate_Date($format);

      if (!$validator->isValid($this->data)) {
        $this->errors['date'] = 'Поле %fieldname% должно содержать корректную дату';
        $this->valid = false;
      }

      if(isset($this->params['future']) && $this->params['future']==1) {
        $curDate = new Zend_Date(null, 'ru_RU');
        $curTimeStamp = $curDate->getTimestamp();
        $checkDate = new Zend_Date($this->data, 'ru_RU');
        $result = $checkDate->compareTimestamp($curTimeStamp);
        if($result<0) {
          $this->errors['date'] = 'Поле %fieldname% должно содержать дату в будущем';
          $this->valid = false;
        }
      }

    return $this->valid;
  }

  private function _validateInArray()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Zend_Validate_InArray($this->params['array'], true);

    if (!$validator->isValid($this->data)) {
      $this->errors['notinarray'] = 'Поле %fieldname% ссылается на информацию, которой в системе нет';
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validateLength()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Zend_Validate_StringLength(array('encoding'=>'UTF-8'));
    $validator->setMin($this->params['min']);
    $validator->setMax($this->params['max']);
    if(!$validator->isValid($this->data)) {
      $this->errors['length'] = "Значение поля %fieldname% должно быть от {$this->params['min']} до {$this->params['max']} символов";
      $this->valid = false;
    }
    return $this->valid;
  }

  private function _validateRange()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    if( (isset($this->params['min']) && $this->params['min'] > $this->data) ||
        (isset($this->params['max']) && $this->params['max'] < $this->data) )
    {
      $this->errors['range'] = "Значение поля %fieldname% должно быть".(isset($this->params['min'])?" от {$this->params['min']}":'')
                                                                      .(isset($this->params['min'])?" до {$this->params['max']}":'');
      $this->valid = false;
    }
    return $this->valid;
  }

  private function _validateInteger()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    if (!is_integer($this->data) && !preg_match('@^[+-]?\d+$@', $this->data)) {
      $this->errors['integer'] = "В поле %fieldname% вводите только целое число";
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validateFloat()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $validator = new Zend_Validate_Float('en');
    if(!$validator->isValid($this->data)) {
      $this->errors['float'] = "Некорректный формат ввода в поле %fieldname%";
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validatePositive()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }

    if (!is_integer($this->data) || 0>$this->data) {
      $this->errors['positive'] = "В поле %fieldname% вводите положительное целое число";
      $this->valid = false;
    }

    return $this->valid;
  }

  /**
    * throws an error if the email fields are not valid email addresses
    *
    */
  private function _validateEmail()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $valid = validEmail($this->data);
    if (true!==$valid) {
      $this->errors['email'] = 'Некорректный email в поле %fieldname%';
      if (is_string($valid)) {
        $this->errors['email'].=": $valid";
      }
      $this->valid = false;
    }

    return $this->valid;
  }

  private function _validateInn()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    switch (strlen($this->data))
    {
      case 0:
        break;
      case 10:
        $weights=array(2,4,10,3,5,9,4,6,8,0);
        if ( $this->data[9]!=$this->checkSum($this->data, $weights) )
        {
          $this->errors['inn'] = 'Некорректное значение ИНН в поле %fieldname%';
          $this->valid = false;
        }
        break;
      case 12:
        $weights1=array(7,2,4,10,3,5,9,4,6,8,0);
        $weights2=array(3,7,2,4,10,3,5,9,4,6,8,0);
        if ( $this->data[10]!=$this->checkSum($this->data, $weights1) ||
             $this->data[11]!=$this->checkSum($this->data, $weights2)
           )
        {
          $this->errors['inn'] = 'Некорректное значение ИНН в поле %fieldname%';
          $this->valid = false;
        }
        break;
      default:
        $this->errors['inn'] = 'Некорректное число символов в поле %fieldname%';
        $this->valid = false;
        break;
    }
    return $this->valid;
  }

  private function _validateKor()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    if (strlen($this->data)>0)
    {
      $weights=array(7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1);
      $data='0'.substr($this->params['bik'], 4, 2).$this->data;
      if ( 0!=$this->checkSum($data, $weights, 10) )
      {
        $this->errors['kor'] = 'Некорректный номер кор. счета в поле %fieldname% (или некорректный БИК)';
        $this->valid = false;
      }
    }
    return $this->valid;
  }

  private function _validateAccount()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    if (strlen($this->data)>0)
    {
      $weights=array(7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1,3,7,1);
      $data=substr($this->params['bik'], -3).$this->data;
      if ( isset($this->params['user_account'])&& $this->params['user_account'])
      {
        $t=substr($this->data, 0, 3);
        if ($t!='405' && $t!='407' && $t!='408')
        {
          $this->errors['account'] = 'Некорректный счет в поле %fieldname%';
          $this->valid = false;
          return false;
        }
      }
      if ( 0!=$this->checkSum($data, $weights, 10) )
      {
        $this->errors['account'] = 'Некорректный номер счета в поле %fieldname% (или некорректный БИК)';
        $this->valid = false;
      }
    }
    return $this->valid;
  }

  private function checkSum($string, $weights, $mod1=11, $mod2=10)
  {
    $sum=0;
    for($i=0; $i<strlen($string); $i++)
    {
      if (isset($weights[$i]))
      {
        $sum+=intval($string[$i])*$weights[$i];
      }
    }
    $sum%=$mod1;
    $sum%=$mod2;
    return $sum;
  }

  private function _validateNullEmpty()
  {
    if (empty($this->data) && !is_null($this->data))
    {
      $this->errors['nullempty'] = 'Значение должно быть или установлено или отключено';
      $this->valid = false;
    }
    return $this->valid;
  }

  private function _validateUrl()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $data_for_validation = $this->data;

    $IDN = new idna_convert();
    $idn_result = $IDN->encode($data_for_validation);

    if (!preg_match('@^https?://[a-z0-9.-]{2,}\.[a-z0-9\-]{2,8}(:[0-9]{2,5})?(/\S*)?$@i', $idn_result)) {
      $this->errors['url'] = 'Некорректный адрес в поле %fieldname%';
      $this->valid = false;
      return $this->valid;
    }
    /*if (MySubStr($data_for_validation, 0, 7) == 'http://') {
      $data_for_validation = MySubStr($this->data, 7);
    } elseif (MySubStr($data_for_validation, 0, 8) == 'https://') {
      $data_for_validation = MySubStr($this->data, 8);
    } else {
      $this->errors['url'] = 'Некорректный адрес в поле %fieldname%';
      $this->valid = false;
      return $this->valid;
    }
    if (MySubStr($data_for_validation, -1) == '/') {
      $data_for_validation = MySubStr($data_for_validation, 0, -1);
    }
    $validator = new Zend_Validate_Hostname();
    if (!$validator->isValid($data_for_validation)) {
      $this->errors['url'] = 'Некорректный адрес в поле %fieldname%';
      $this->valid = false;
    }*/
    return $this->valid;
  }

  private function _validatePhone()
  {
    if (empty($this->data))
    {
      return $this->valid;
    }
    $data_for_validation = explode('-', $this->data);
    if(empty($data_for_validation[0])) {
      $this->errors['phone'] = 'В поле "%fieldname%" не заполнен код страны';
      $this->valid = false;
    } elseif(empty($data_for_validation[1])) {
      $this->errors['phone'] = 'В поле "%fieldname%" не заполнен код города';
      $this->valid = false;
    } elseif(empty($data_for_validation[2])) {
      $this->errors['phone'] = 'В поле "%fieldname%" не заполнен номер';
      $this->valid = false;
    } elseif(MyStrLen($data_for_validation[2]) < 5) {
      $this->errors['phone'] = 'В поле "%fieldname%" значение поля номер должно быть не менее 5 знаков';
      $this->valid = false;
    }

    return $this->valid;
  }
}
