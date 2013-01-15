<?php
class Core_Balance
{
  protected static function getDocumentsCount($db, $table, $datefield='date_generated', $date=false)
  {
    $s=$db->select()->from($table, array('count(*)'));
    if (false!==$date) {
      $ts = db_date_to_timestamp($date);
      $s = $s->where("number like '".date('ymd', $ts)."%'");
    }

    $n = $db->fetchOne($s);
    return $n;
  }

  protected static function getFreeDocumentNumber($table, $date=false)
  {
    $db=Zend_Registry::get('db');
    if ( $date && !is_numeric($date)) {
      $date = db_date_to_timestamp($date);
    } elseif (!$date) {
      $date = time();
    }
    $date = date('ymd', $date);
    $date =  getConfigValue('general->etp_id', '10').substr($date, 1);
    $number_rows = $db->fetchAll("SELECT number FROM $table WHERE number LIKE '{$date}%'", array(), Zend_Db::FETCH_ASSOC);
    $numbers = array();
    foreach ($number_rows as $n) {
      if (preg_match("@^{$date}(\d{4})$@", $n['number'], $matches)) {
        $numbers[] = intval($matches[1]);
      }
    }
    $n = 1;
    while (in_array($n, $numbers)) {
      $n++;
    }
    return sprintf("{$date}%04s", $n);
  }

  public static function generateDocumentId($type, $table, $datefield='date_generated', $date=false) {
    return self::getFreeDocumentNumber($table, $date);
  }

  public static function generageFiscalDocs($supplierId, $lotId, $sum) {
    $result = Model_FiscalDocsAct::generateFiscalDocuments($supplierId, $lotId, $sum);
    return $result;
  }


  /**
   * ПРОЦЕДУРА СПИСАНИЯ ПЛАТЫ ЗА УСЛУГИ (id контрагента, id процедуры)
   * serviceFeeDebit(contragent_id, procedure_id)
   * Сумма в колонке “deposit” контрагента с переданным id декрементируется на
   * сумму, указанную в значении $amount (допустимы отрицательные
   * значения), сумма в колонке earnings таблицы stats БД системы инкрементируется
   * на ту же сумму. Операция заносится в историю операций с привязкой к данному
   * контрагенту, текущему timestamp и учетной записи оператора, нажавшего кнопку,
   * активировавшую данную процедуру.
   */
  public static function serviceFeeDebit($contragent_id, $lot_id, $reg_number='', $amount=null, $log_msg = '') {
    if ( is_object($lot_id) ) {
      $lot = $lot_id;
      $lot_id = $lot->getId();

    } else if ($lot_id != 0) {
      $lot = Model_Lot::load($lot_id);
    }

    $procedure = $lot->getProcedure();
    $procedure_id = $procedure->getId();

    if (is_object($contragent_id)) {
      $contragent = $contragent_id;
      $contragent_id = $contragent->getId();
    } else {
      $contragent = Model_Contragent::load($contragent_id);
    }

    if (empty($reg_number) && isset($procedure)) {
      $reg_number = $procedure->getRegistryNumber().', лот № '.$lot->getNumber();;
    } else {
      $reg_number .= ', лот № '.$lot->getNumber();
    }

    if (!$log_msg) {
      $log_msg  = "Списание платы за участие в процедуре $reg_number в размере $amount руб.";
    } else {
      $log_msg .= " Плата списана в размере $amount руб.";
    }

    $contragent->setDeposit($contragent->getDeposit() - $amount, $log_msg);
    $contragent->save();
    fireEvent('deposit_changed', $contragent->getId(), $contragent->getDeposit());
    $_dt = array(
			'contragent_id' => $contragent_id,
                        'lot_id' => $lot_id,
			'procedure_id' => $procedure_id,
			'operation_description' => $log_msg,
			'operation_type' => Model_TransactionLog::FEE_PAYED,
			'date' => db_timestamp_to_date(time()),
			'sum' => $amount
    );
    Model_TransactionLog::create($_dt);

    return true;
  }


  /**
   * Плата за публикацию процедуры (случай, когда платит организатор)
   *
   * @param Model_Procedure $procedure
   * @param Model_Contragent $contragent
   */
  public static function collectProcedureFee($procedure, $contragent = null) {
    if (!is_object($procedure)) {
      $procedure = Model_Procedure::load($procedure);
    }
    if ($contragent === null) {
      $contragent = $procedure->getOrganizerContragentId();
    }
    $config = Zend_Registry::get('config');
    $sum = $config->procedure->service_fee;
    if ($procedure->getProcedureType() == Model_Procedure::PROCEDURE_TYPE_TENDER) {
      $sum = $config->procedure->tender_service_fee;
    }
    $msg = 'Списание сервисной платы за процедуру № '.$procedure->getRegistryNumber();
    try {
      DbTransaction::begin();
      //Логгируем минусовую транзакцию
      if($sum > $contragent->getDeposit()) {
        self::logDebtTx($procedure, $contragent, $sum);
      }

      $act_id = $contragent->alterDeposit($sum, 'service', $msg, $procedure, '', false);
      $bill_id = $act_id;

      $dt = array();
      $dt['procedure_registry_number'] = $procedure->getRegistryNumber();
      $dt['auctions_registry_number'] = $procedure->getRegistryNumber();
      $dt['title'] = $procedure->getTitle();
      $dt['short_name'] = $contragent->getShortName();
      $dt['full_name'] = $contragent->getFullName();
      $dt['service_fee'] = HumanizePrice($sum).' '.$procedure->getCurrencyObject()->name;
      $dt['suppliers_deposit-etp_service_fee'] = $contragent->getDeposit();
      $dt['FILEURL_fiscal_docs_act_filename'] = getServerAddress().printUrl('file', 'get', 't', 'FiscalDocAct', 'id',$act_id);

      $tmpl = Core_Template::AUCTION_PAYMENT_DONE;
      $msg  = Core_Template::dbProcess($tmpl, $dt);
      Model_MailLog::notifyContragent($contragent, $msg['subject'], $msg['message']);
      DbTransaction::commit();
    } catch (Exception $e) {
      DbTransaction::rollback();
      throw $e;
    }
  }

  public static function getServiceFeeRate($procedureType){
    $sum = 0;
    if (Model_Procedure::isTypeTender($procedureType)) {
      $sum = getConfigValue('procedure->tender_service_fee', 0);
    } else if (Model_Procedure::isTypeAuction($procedureType)) {
      $sum = getConfigValue('procedure->auc_service_fee', 0);
      if(!$sum) {
        $sum = getConfigValue('procedure->service_fee', 0);
      }
    } else if ($procedureType == Model_Procedure::PROCEDURE_TYPE_QUOTATION_REQ) {
      $sum = getConfigValue('procedure->quotation_service_fee', 0);
    } else if ($procedureType == Model_Procedure::PROCEDURE_TYPE_PRICELIST_REQ) {
      $sum = getConfigValue('procedure->pricelist_service_fee', 0);
    }
    //logVar($sum, 'сумма платы за участие');
    return $sum;
  }

  public static function getLotPublishFee($total_price) {
    if(getConfigValue('procedure->lot_publish_fee_fixed', false)) {
      return getConfigValue('procedure->lot_publish_fee', 1500);
    }

    $percentage = getConfigValue('procedure->lot_publish_fee_percent', 10);

    $service_fee = $total_price*$percentage/100;
    $max_price = getConfigValue('procedure->lot_publish_maxprice', 1500);
    if($service_fee > $max_price) {
      $service_fee = $max_price;
    }
    return $service_fee;
  }

  /**
   * Плата за участие в лоте (случай, когда платит заявитель)
   *
   * @param Model_Lot $lot
   * @param Model_Contragent $supplier
   * @param Model_Contragent $customer
   */
  public static function collectLotFee($lot, $supplier=null, $customer=null) {
    if (!is_object($lot)) {
      $lot = Model_Lot::load($lot);
    }

    if ($supplier === null) {
      $supplier = $lot->getWinnerId();
    }

    $procedure = $lot->getProcedure();

    if (!$supplier) {
      throw new Exception("Не могу определить победителя лота ".$procedure->getRegistryNumber());
    }

    if (!is_object($supplier)) {
      $supplier = Model_Contragent::load($supplier);
    }

    if ($customer === null) {
      $customer = $procedure->getOrganizerContragentId();
    }

    if (!$customer) {
      throw new Exception("Не могу определить организатора процедуры " . $procedure->getRegistryNumber());
    }

    if (!is_object($customer)) {
      $customer = Model_Contragent::load($customer);
    }

    $sum = self::getServiceFeeRate($procedure->getProcedureType());
    $auc = Model_Procedure::load($lot->getProcedureId());
    //проверить, активен ли тариф
    //если да - не списываем
    $tariffIsActive = $supplier->checkTariff();

    if($sum > 0 && !$tariffIsActive) {
      $msg = 'Списание сервисной платы за победу в электронной процедуре № '.$procedure->getRegistryNumber() . ' лот ' . $lot->getNumber();

      try {
        DbTransaction::begin();
        //Логгируем минусовую транзакцию
        if($sum > $supplier->getDeposit()) {
          self::logDebtTx($lot->getProcedureId(), $supplier, $sum);
        }

        $act_id = $supplier->alterDeposit($sum, 'service', $msg, $procedure, $lot);
        $bill_id = $act_id;

        $dt = array();
        $dt['registry_number'] = $procedure->getRegistryNumber();
        $dt['auctions_registry_number'] = $procedure->getRegistryNumber().', лот № '.$lot->getNumber();
        $dt['title'] = $procedure->getTitle();
        $dt['subject'] = $lot->getSubject();
        $dt['suppliers_short_name'] = $supplier->getShortName();
        $dt['suppliers_full_name'] = $supplier->getFullName();
        $dt['customers_full_name'] = $customer->getFullName();
        $dt['customers_short_name'] = $customer->getShortName();
        $dt['suppliers_id'] = $supplier->getId();
        $dt['etp_service_fee'] = HumanizePrice($sum).' RUB';
        $dt['suppliers_deposit-etp_service_fee'] = HumanizePrice($supplier->getDeposit()).' RUB';
        $dt['FILEURL_fiscal_docs_act_filename'] = getServerAddress().printUrl('file', 'get', 't', 'FiscalDocAct', 'id',$act_id);

        $tmpl = Core_Template::AUCTION_PAYMENT_DONE;
        $msg  = Core_Template::dbProcess($tmpl, $dt);
        $supplier->notify($msg['subject'], $msg['message'], array('procedure_id'=>$procedure->getId(), 'lot_id'=>$lot->getId()));
        DbTransaction::commit();
      } catch (Exception $e) {
        DbTransaction::rollback();
        throw $e;
      }
    }
  }

  /**
   * Списанная сумма за участие в лоте (случай, когда платит заявитель)
   *
   * @param Model_Lot $lot
   * @param Model_Contragent $supplier
   * @return sum
   */
  public static function getServiceFeePayed($lot, $supplier) {
    if (!is_object($lot)) {
      $lot = Model_Lot::load($lot);
    }
    if (!is_object($supplier)) {
      $supplier = Model_Contragent::load($supplier);
    }

    $db = Zend_Registry::get('db');
    $select = $db->select()
                 ->from(DbTable_TransactionLog::NAME, array('SUM(sum)'))
                 ->where('contragent_id = ?', $supplier->getId())
                 ->where('lot_id = ?',  $lot->getId())
                 ->where('operation_type = ?', Model_TransactionLog::SERVICE_FEE);
    return intval($db->fetchOne($select, array(), Zend_Db::FETCH_ASSOC));
  }

  public static function logDebtTx($procedure, $contragent, $sum) {
    if(!is_object($procedure))
      $procedure = Model_Procedure::load($procedure);
    $amount = $sum - $contragent->getDeposit();
    $idata = array('date_begin'=>time(),
                   'contragent_id'=>$contragent->getId(),
                   'amount' =>$amount,
                   'comment'=>'Задолженность по сервисной плате за процедуру № '.$procedure->getRegistryNumber()
    );
    $debt = Model_Debts::create($idata);
    unset($idata['date_begin']);
    unset($idata['contragent_id']);
    $idata['tx_date']=time();
    $idata['type'] = Model_DebtLog::TX_TYPE_DEBT;
    $idata['supplier_id'] = $contragent->getId();
    Model_DebtLog::create($idata);
  }

  /**
   * Транзакция блокировки-разблокировки
   *
   * @param Model_Application $applic
   * @param string $operation_type
   * @param bool $minus
   * @param string $msg
   * @param float $old_block
   * @param float $custom_amount - заданная сумма заблока
   * @return bool
   */
  public static function correctDeposit(Model_Application $applic, $operation_type=null, $minus=false, $msg='', $old_block=0, $custom_amount=0) {
    if(is_null($operation_type)) {
      throw new Exception('Операция с депозитом невозможна без указания кода операции',405);
    }
    $lot = Model_Lot::load($applic->getLotId());
    $auc = Model_Procedure::load($lot->getProcedureId());
    $supplier = Model_Contragent::load($applic->getSupplierId());
    if($old_block==0) {
      // Сумма для блокировки / разблокировки
      //$guarantee = $lot->getGuarantee();
      if ($custom_amount == 0) {
        $amount = Model_Lot::guaranteeApplication($auc, $lot, true);
      } else {
        $amount = $custom_amount;
      }
      $applic_guarantee_required = true;
      if(empty($amount)) {
       $amount = self::getServiceFeeRate($auc->getProcedureType());   // Сумма заблока равна плате за участие
       //для заявки без обеспечения проверить, активен ли тариф
       //если да - не блочим
       $tariffIsActive = $supplier->checkTariff();
       $applic_guarantee_required = false;                     // Обеспечение не требуется
       if($amount===0 || $tariffIsActive) return true;         // Обеспечения не требуется и плата не взимается
      }
    } else {
      if($operation_type==Model_TransactionLog::DEPOSIT_UNBLOCKED) {
        $amount = $old_block;
      } else {
        throw new Exception ('Параметр "прежняя сумма блокировки" не поддерживается для данного типа операции',405);
      }
    }

    $curBlocked = $supplier->getDepositBlocked();

    // Если блокировка средств не производится по данному лоту
    $isBlocked = Model_TransactionLog::isTransactionExist($lot->getId(), $applic->getSupplierId());
    //logVar($isBlocked, 'is money blocked');
    if($operation_type==Model_TransactionLog::DEPOSIT_BLOCKED) {
      // Проверяем, чтобы не заблокировать по второму разу
      if($isBlocked) return true;
      // Подсчитываем новую сумму заблокированных средств
      $newBlocked = $curBlocked+$amount;
      //if ($newBlocked > ($supplier->getDeposit()+0.004) && !$minus)
      if ($amount > ($supplier->getAvailableSum()+0.004))
      {
        // Проверяем статус заявки и решаем чотамделат
        if($applic->getStatus()>0 && $old_block && !$minus) {
          if($lot->getWinnerId()!=$applic->getSupplierId()) {
            $date_cancelled = db_timestamp_to_date(time());
            $applic->setDateCancelled($date_cancelled);
            $applic->setStatus(Model_Application::STATUS_CANCELLED);
            $applic->save();
            $tmpl= Core_Template::APPLICATION_CANCELLED_NOMONEY;

            $data = array(
              'order_number'=>$applic->getOrderNumberAdded(),
              'date_cancelled'=>$date_cancelled,
              'suppliers_short_name'=>$supplier->getFullName(),
              'auctions_registry_number'=>$auc->getRegistryNumber(),
              'auction_id'=>$auc->getId()
            );
            $message = Core_Template::dbProcess($data);
            $supplier->notify($message['subject'], $message['data'], array('procedure_id'=>$lot->getProcedureId(), 'lot_id'=>$lot->getId()));
            return true;
          } else {
            throw new Exception("У победителя - заявки № ".$applic->getOrderNumberAdded()." недостаточтно средств на лицевом счете для блокирования. Заявка не была отменена. Необходимо взять ситуацию под контроль. Номер лицевого счета победителя ".$applic->getSupplierId(),405);
          }
        } else {
          // Только для процедур без обеспечения
          if(!$auc->isTypeRetrade() && ! $applic_guarantee_required || (!!getConfigValue('general->guarantee_required', true)) === false) {
            $data['minus_blocked'] = true; //Блочим в минус - ждем пополнения счета
          } else {
            throw new ResponseException('Недостаточно средств для блокирования в качестве обеспечения участия в процедуре. Требуется  '.  HumanizePrice($amount).' рублей. Доступно '.HumanizePrice($supplier->getAvailableSum()).' руб.');
            return false;
          }
        }
      }
      $descr = 'Блокирование средств в размере обеспечения участия в процедуре '.$auc->getRegistryNumber().', Лот '.$lot->getNumber() ;
    }
    else if($operation_type==Model_TransactionLog::DEPOSIT_UNBLOCKED) {
      // Проверяем, чтобы не разблокировать по второму разу
      if(!$isBlocked) return true;
      // Подсчитываем новую сумму заблокированных средств
      $db=Zend_Registry::get('db');
      $amount = $db->fetchOne("select sum from transaction_log where lot_id=? and contragent_id=? and operation_type='deposit_blocked' and (correction=false OR correction IS NULL) order by date desc limit(1) offset(0)", array($lot->getId(), $applic->getSupplierId()));
      if($amount) {
        $newBlocked = $curBlocked-$amount;
        self::clearMinusBlockedByLot($applic->getSupplierId(), $lot->getId());
      } else {
        $newBlocked = $curBlocked;
      }

      $descr = 'Разблокирование средств в размере обеспечения участия в процедуре '.$auc->getRegistryNumber().', Лот '.$lot->getNumber();
    }

    // Подготавливаем данные для логирования
    $data['lot_id'] = $lot->getId();
    $data['procedure_id'] = $lot->getProcedureId();
    $data['contragent_id'] = $applic->getSupplierId();
    $data['sum'] = $amount;
    $data['date'] = date('Y-m-d H:i:s');
    $data['operation_type'] = $operation_type;
    $data['operation_description'] = $descr;
    $data['basis_text'] = $msg;

    if ($amount && ! empty($amount)) {
      // Логируем транзакцию и сохраняем изменения размера заблокированных средств у поставщика
      Model_TransactionLog::create($data);
      $supplier->setDepositBlocked($newBlocked);
      $supplier->save();
      fireEvent('deposit_blocked_changed', $supplier->getId(), $supplier->getDepositBlocked());
    }
    return true;
  }

  /**
   * Транзакция блокировки-разблокировки без привязки к заявкам заявителя
   *
   * @param int $contragent_id
   * @param string $operation_type
   * @param int $amount
   * @param string $basis_text
   * @return bool
   */
  public static function correctDepositFreeSum($contragent_id, $operation_type=null, $amount=0, $basis_text='') {
    if(is_null($operation_type)) {
      throw new Exception('Операция с депозитом невозможна без указания кода операции',405);
    }
    if ($amount == 0) {
      throw new Exception('Не указана сумма операции',405);
    }

    $supplier = Model_Contragent::load($contragent_id);
    $curBlocked = $supplier->getDepositBlocked();

    $descr = '';
    if ($operation_type == Model_TransactionLog::DEPOSIT_BLOCKED) {
      $newBlocked = $curBlocked+$amount;
      $descr = 'Блокирование средств';
    } elseif ($operation_type == Model_TransactionLog::DEPOSIT_UNBLOCKED) {
      $newBlocked = $curBlocked-$amount;
      $descr = 'Разблокирование средств';
    }

    // Подготавливаем данные для логирования
    $data['lot_id'] = null;
    $data['procedure_id'] = null;
    $data['contragent_id'] = $contragent_id;
    $data['sum'] = $amount;
    $data['date'] = date('Y-m-d H:i:s');
    $data['operation_type'] = $operation_type;
    $data['operation_description'] = $descr;
    $data['basis_text'] = $basis_text;

    if ($amount && ! empty($amount)) {
      // Логируем транзакцию и сохраняем изменения размера заблокированных средств у поставщика
      Model_TransactionLog::create($data);
      $supplier->setDepositBlocked($newBlocked);
      $supplier->save();
      fireEvent('deposit_blocked_changed', $supplier->getId(), $supplier->getDepositBlocked());
    }
    return true;
  }

  public static function coverMinusBlocked($contragent, $sum) {
    if($sum==0) return;
    $cond = "t.contragent_id=".$contragent->getId()." AND t.operation_type='".  Model_TransactionLog::DEPOSIT_BLOCKED."' AND t.minus_blocked=TRUE";
    $minus_blocked = Model_TransactionLog::search($cond, 't.date asc', false);
    $applics_unblocked = array();

    if(count($minus_blocked)) {
      foreach($minus_blocked as $tx) {
        $tx_sum = $tx->getSum();
        if($sum<$tx_sum) {
          continue;
        } else {
          $tx->setMinusBlocked(null);
          $tx->save();
          $sum-=$tx_sum;
          $applics_unblocked[] = $tx;
        }
      }
    }
    return $applics_unblocked;
  }

  /*
   * Убирает все минусовые заблоки по указанному лоту
   * Это нужно когда происходит разблокировка средств по лоту,
   * чтобы при пополнении средств не покрывались неактуальные минусовые заблоки
   */
  public static function clearMinusBlockedByLot($contragent_id, $lot_id) {
    $cond = "t.contragent_id=".$contragent_id." AND t.lot_id = " . $lot_id . " AND t.operation_type='".  Model_TransactionLog::DEPOSIT_BLOCKED."' AND t.minus_blocked=TRUE";
    $minus_blocked = Model_TransactionLog::search($cond, 't.date asc', false);

    if(count($minus_blocked)) {
      foreach($minus_blocked as $tx) {
        $tx->setMinusBlocked(null);
        $tx->save();
      }
    }
    return true;
  }
  /**
   * Получение номера платежки
   * @param int $n id-шник записи для которой нужен номер
   * @param int|string $min минимальный номер диапазона (включительно) или имя парама в конфиге
   * @param int $max максимальный номер диапазона (включительно)
   * @return int
   */
  public static function getPaymentNumber($n, $min=1, $max=99999) {
    if (is_string($min) && !is_numeric($min)) {
      $max = getConfigValue("general->bankdata->number->{$min}->max", 99999);
      $min = getConfigValue("general->bankdata->number->{$min}->min", 1);
    }
    $n = intval($n);
    $delta = $max - $min + 2;
    return ($n % $delta) + $min - 1;
  }
}