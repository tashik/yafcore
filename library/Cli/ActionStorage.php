<?php

class Cli_ActionStorage {

  /**
   * @var Cli_Action[]
   */
  private static $actions = array();

  /**
   * @return Cli_Action[]
   */
  public static function getAll() {
    return self::$actions;
  }

  /**
   * @return void
   * @param Cli_Action $action
   *
   * @throws RuntimeException
   */
  public static function add(Cli_Action $action) {
    if (self::exists($action)) {
      throw new RuntimeException('Action "' . $action->getKey() . '" already exists in storage');
    }
    self::$actions[$action->getKey()] = $action;
  }

  /**
   * @return boolean
   * @param Cli_Action|string $action
   */
  public static function exists($action) {
    if ($action instanceof Cli_Action) {
      return array_key_exists($action->getKey(), self::$actions);
    }
    return array_key_exists($action, self::$actions);
  }

  /**
   * @return Cli_Action
   * @param string $key
   *
   * @throws RuntimeException
   */
  public static function get($key) {
    if (!self::exists($key)) {
      throw new RuntimeException('Action "' . $key . '" not exists in storage');
    }
    return self::$actions[$key];
  }

}