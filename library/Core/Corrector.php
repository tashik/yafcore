<?php

function core_corrector_sum_sort_compare_reverse($a1, $a2) {
  $d1 = $a1['date'];
  $d2 = $a2['date'];
  if ($d1<$d2)
  {
    return -1;
  } else if ($d1>$d2)
  {
    return 1;
  }
  return 0;
}

// @include_once APPLICATION_PATH.'/../data/vigruzka_010710.php';

class Core_Corrector {
  static function getSupplierOperations($id) {
    $db = Zend_Registry::get('db');
    if (is_object($id))
    {
      $supplier = $id;
      $id = $supplier->getId();
    } else {
      $supplier = Model_Contragent::load($id);
    }
    $data = array();
    $result = $db->fetchAll("SELECT id,sum,contragent_id,date_added,date_solved,date_cancelled FROM transaction_requests WHERE contragent_id=$id", array(), Zend_Db::FETCH_ASSOC);
    foreach ($result as $r)
    {
      $sumb = $r['sum'];
      $sumd = $r['sum'];
      $sumr = null;
      if ($r['date_solved'])
      {
        $msg="Удовлетворенная заявка №{$r['id']} на вывод $sumb";
        $sumb=0;
        $sumd=0;
        $sumr=0;
      } else if ($r['date_cancelled'])
      {
        $msg="Отмененная заявка №{$r['id']} на вывод $sumb";
        $sumb=0;
        $sumd=0;
        $sumr=0;
      } else {
        $msg="Заявка №{$r['id']} на вывод";
        $sumd=null;
        $sumr=$sumb;
      }

      $data[]=array('sumb'=>$sumb, 'sumd'=>$sumd, 'sumr'=>$sumr,
                    'msg'=>$msg, 'reqid'=>$r['id'],
                    'date' => db_date_to_timestamp($r['date_added']));
    }
    $aucs = array();

    $sql = "SELECT  app.supplier_id,
                    app.date_added,
                    lots.id,
                    lots.status,
                    procedures.registry_number,
                    procedures.procedure_type,
                    lots.guarantee_application,
                    lots.start_price,
                    lots.winner_id,
                    app.date_rejected,
                    app.date_cancelled,
                    app.status as app_status,
                    app.id as appid,
                    app.order_number_assigned,
                    app.reject_stage
            FROM applications app
            LEFT JOIN procedures ON app.procedure_id=procedures.id
            LEFT JOIN lots ON app.lot_id=lots.id
            WHERE app.date_published is NOT NULL
              AND app.supplier_id=$id";

    $result = $db->fetchAll($sql, array(), Zend_Db::FETCH_ASSOC);
    foreach ($result as $r)
    {
      $blocked = $db->fetchOne("SELECT SUM(sum) FROM transaction_log WHERE contragent_id=$id AND operation_type='deposit_blocked' AND lot_id={$r['id']} AND (correction=false OR correction IS NULL)");
      $unblocked = $db->fetchOne("SELECT SUM(sum) FROM transaction_log WHERE contragent_id=$id AND operation_type='deposit_unblocked' AND lot_id={$r['id']} AND (correction=false OR correction IS NULL)");
      if (abs($blocked-$unblocked)<0.001) {
        $diff_blocked = false;
      } else {
        $diff_blocked = round(($blocked-$unblocked), 2);
      }
      if (!!getConfigValue('general->guarantee_required', true) === false) {
        $sum = filterPrice(Core_Balance::getServiceFeeRate($r['procedure_type']));
      } else {
        $sum = filterPrice($r['guarantee_application']);
      }

      $w='';

      $guarantee = sprintf('%.2f', $sum/$r['price']);
      $owner = '';

      $msg="Участие в процедуре({$r['status']}{$owner}) {$r['registry_number']} ({$r['id']}), сумма: $sum = {$r['price']}*$guarantee%{$w} заявка {$r['appid']}";
      if (in_array($r['status'], array(9,10))) {
        if ($r['date_cancelled']) {
          $t = ', заявка отменена';
        }
        if ($r['date_rejected']) {
          $t = ", заявка отклонена";
        }
        $msg = "Состояние д/с по заявке не контролируется{$t}. $msg";
        $sum = $blocked-$unblocked;
      } else if ($r['date_cancelled'])
      {
        $sum=0;
        $msg="Заявка отменена. $msg";
      } else if (Model_Application::STATUS_FAILED==$r['app_status'] && $r['date_rejected'] && 9!=$r['status']) {
        $msg="Заявитель уклонился от заключения договора. $msg";
      } else if ($r['date_rejected']) {
        $sum=0;
        $m = 'Заявка отклонена';
        if (2==$r['reject_stage']) {
          $m = "<a href=\"/admin/supplier/quarterblocks/id/$id\">Заявка отклонена по 2м частям</a>";
        }
        $msg="$m. $msg";
      } else if (!in_array(intval($r['status']), array(2,3,4,5,6,7))) {
        $sum=0;
        $auc_status = 'завершен';
        $r['status'] = intval($r['status']);
        $status_map = array(
          Model_Lot::STATUS_CANCELLED => 'отменены',
        );
        if (isset($status_map[$r['status']])) {
          $auc_status = $status_map[$r['status']];
        }
        if ($r['winner_id']==$r['supplier_id']) {
          $msg="Торги $auc_status, Заявитель победитель. $msg";
        } else {
          $msg="Торги $auc_status. $msg";
        }
      } else if (Model_Lot::STATUS_CONTRACT==intval($r['status']) && $r['order_number_assigned']>5) {
        //$sum=0; таки пока учатник не отменит заявку сам, мы ему ничего не разблочиваем
        $msg="Заявка имеет номер {$r['order_number_assigned']}. $msg";
      } else if (Model_Lot::STATUS_CONTRACT==intval($r['status'])) {
        $msg="Заявка имеет номер {$r['order_number_assigned']}. $msg";
      }
      if (in_array($r['id'], $aucs)) {
        $msg = "Дублирующаяся заявка! $msg";
        $sum = 0;
      }
      if ($sum>0) {
        $aucs[] = $r['id'];
      }

      $data[]=array(
          'sumb'=>$sum,
          'msg'=>$msg,
          'blocked'=>$diff_blocked,
          'appid'=>$r['appid'],
          'auc_reg'=>$r['registry_number'],
          'date'=>  db_date_to_timestamp($r['date_added']));
    }

    $inn = $supplier->getInn();

    $result = $db->fetchAll("SELECT id,contragent_id,sum,operation_description,operation_type,basis_text,\"date\",correction FROM transaction_log WHERE contragent_id=$id AND (operation_type='deposit_unblocked' OR operation_type='deposit_blocked')", array(), Zend_Db::FETCH_ASSOC);
    foreach ($result as $r)
    {
      $sum = filterPrice($r['sum']);
      $ignore = $r['correction'];
      $msg = "Ручная операция: {$r['operation_description']} {$r['basis_text']}";
      if ($ignore) {
        $msg.=' (корректирующая операция, не учитываем в расчете)';
      }

      if ('deposit_unblocked'==$r['operation_type'] )
      {
        $sum=-$sum;
      }
      if (false === strpos($r['operation_description'], 'оператором ЕЭТП')
          && false === strpos($r['operation_description'], 'заявка отклонялась')
          && !$r['correction'])
      {
        continue;
      }

      $data[]=array('sumb'=>$sum, 'ignore'=>$ignore,
                    'msg'=>$msg, 'id'=>$r['id'],
                    'date'=> db_date_to_timestamp($r['date']));
    }

    $result = $db->fetchAll("SELECT id,contragent_id,sum,operation_description,operation_type,\"date\",correction FROM transaction_log WHERE contragent_id=$id AND (operation_type='money_back' OR operation_type='service_fee' OR operation_type='customer_fee' OR operation_type='money_deposit')", array(), Zend_Db::FETCH_ASSOC);
    foreach ($result as $r)
    {
      if ('Списание в счет погашения задолженности' == $r['operation_description']) {
        continue;
      }
      $sum = filterPrice($r['sum']);
      $msg = "Ручная операция: {$r['operation_description']}";
      $ignore = $r['correction'];
      if ($ignore) {
        $msg.=' (корректирующая операция, не учитываем в расчете)';
      }

      if ('money_deposit'!=$r['operation_type'] )
      {
        $sum=-$sum;
      }

      $data[]=array('sumd'=>$sum, 'ignore'=>$ignore,
                    'msg'=>$msg, 'id'=>$r['id'],
                    'date'=> db_date_to_timestamp($r['date']));
    }

    $totalsumb=0;
    $totalsumd=0;
    $totalsumr=0;
    foreach($data as $s)
    {
      if (isset($s['ignore']) && $s['ignore']) {
        continue;
      }
      if (isset($s['blocked']) && $s['blocked'] === false) {
        continue;
      }
      $totalsumb+=isset($s['sumb'])?$s['sumb']:0;
      $totalsumd+=isset($s['sumd'])?$s['sumd']:0;
      $totalsumr+=isset($s['sumr'])?$s['sumr']:0;
    }
    $totalsumb = round($totalsumb*100, 0)/100;
    $totalsumd = round($totalsumd*100, 0)/100;
    $totalsumr = round($totalsumr*100, 0)/100;
    $dups = $db->fetchOne("SELECT count(*) from contragents where inn='".$supplier->getInn()."'");

    usort($data, 'core_corrector_sum_sort_compare_reverse');
    $data = array (
      'totalsumb' => $totalsumb,
      'totalsumd' => $totalsumd,
      'totalsumr' => $totalsumr,
      'data' => $data,
      'dups' => $dups,
    );
    return $data;
  }
}

