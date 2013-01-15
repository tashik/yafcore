<?php

class Core_Observable {
  private $_events = array();

  /**
   * Подписаться на евент $event. При возникновении этого евента будет вызвана
   * функция $handler.
   *
   * @param string $event имя евента
   * @param string|array $handler каллбек евента (см. call_user_func)
   * @return string|array переданный $handler
   */
  public function on($event, $handler) {
    if (!isset($this->_events[$event]) || !$this->_events[$event]) {
      !$this->_events[$event] = array();
    }
    !$this->_events[$event][] = $handler;
    return $handler;
  }

  /**
   * Отписаться от евента
   *
   * @param string $event имя евента
   * @param string|array $handler каллбек, который необходимо отписать
   * @return bool true, если успешно отписан, false если нет
   */
  public function un($event, $handler) {
    if (!isset($this->_events[$event]) || !$this->_events[$event]) {
      return false;
    }
    foreach ($this->_events as $k=>$e) {
      if ($e==$handler) {
        unset($this->_events[$k]);
        return true;
      }
    }
    return false;
  }

  /**
   * Вызвать евент $event. Все дополнительные параметры будут переданы в хендлеры как есть
   * Специальный случай: когда параметром передается один объект класса Core_Event. В таком случае
   * результатом будет этот же объект, но возможно измененный хендлерами.
   *
   * @param string $event имя евента
   * @return \Core_Event||bool Объект события, если он был передан, или false если хоть один хендлер вернул false, иначе true
   */
  public function fireEvent($event) {
    $args = func_get_args();
    array_shift($args);

    $event_mode = false;
    if (1==count($args) && $args[0] instanceof Core_Event) {
      $event_mode = true;
      $event_object = $args[0];
    }

    if (!isset($this->_events[$event]) || !$this->_events[$event]) {
      $ret = $event_mode?$event_object:true;
    } elseif ($event_mode) {
      foreach ($this->_events[$event] as $e) {
        if (!$e) {
          continue;
        }
        call_user_func($e, $event_object);
        if ($event_object->stop_processing) {
          break;
        }
      }
      $ret = $event_object;
    } else {
      $ret = true;
      foreach ($this->_events[$event] as $e) {
        if (!$e) {
          continue;
        }
        if (false===call_user_func_array($e, $args)) {
          $ret = false;
        }
      }
    }
    return $ret;
  }
}

/**
 * Получить (и создать при отсутствии) общесистемный менеджер сообщений
 *
 * @return Core_Observable системный менеджер сообщений
 */
function getEventManager() {
  if (!Zend_Registry::isRegistered('emgr')) {
    $sys = new Core_Observable();
    Zend_Registry::set('emgr', $sys);
    return $sys;
  }
  return Zend_Registry::get('emgr');
}

/**
 * Алиас Core_Observable->on для общесистемного менеджера сообщений
 *
 * @param string $event имя евента
 * @param string|array $handler каллбек евента (см. call_user_func)
 * @return bool Результат вызова Core_Observable->on
 */
function addListener($event, $handler) {
  $mgr = getEventManager();
  return $mgr->on($event, $handler);
}

/**
 * Алиас Core_Observable->un для общесистемного менеджера сообщений
 *
 * @param string $event имя евента
 * @param string|array $handler  каллбек, который необходимо отписать
 * @return bool Результат вызова Core_Observable->un
 */
function removeListener($event, $handler) {
  $mgr = getEventManager();
  return $mgr->un($event, $handler);
}

/**
 * Алиас Core_Observable->fireEvent для общесистемного менеджера сообщений
 *
 * @param string $event имя евента
 * @return bool false если хоть один хендлер вернул false, иначе true
 */
function fireEvent($event) {
  $mgr = getEventManager();
  $args = func_get_args();
  return call_user_func_array(array($mgr, 'fireEvent'), $args);
}
