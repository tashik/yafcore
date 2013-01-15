<?php
$aCompanyTypes = array(
  array('id' => 1, 'name' => 'Юридическое лицо РФ'),
  array('id' => 2, 'name' => 'Юридическое лицо др.страны'),
  array('id' => 3, 'name' => 'Индивидуальный предприниматель РФ'),
  array('id' => 4, 'name' => 'Индивидуальный предприниматель др.страны'),
  array('id' => 5, 'name' => 'Физическое лицо РФ'),
  array('id' => 6, 'name' => 'Физическое лицо др.страны'),
);

$aCustomerTypes = array(
	9 => 'Уполномоченный орган',
	8 => 'Специализированная организация',
	7 => 'Заказчик'
);

$aSystems = array(
  array('id' => 1, 'name' => 'ЭДО'),
  array('id' => 2, 'name' => 'ЭТП'),
);

$aReferences = array(
  'systems'          => $aSystems,
  'companyTypes'     => $aCompanyTypes
);