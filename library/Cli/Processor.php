<?php

class Cli_Processor {

  const HELP_ACTION = 'help';

  public static function run() {
    $argv    = $_SERVER['argv'];
    $program = array_shift($argv);
    $action  = array_shift($argv);

    self::runAction($program, $action, $argv);
  }

  public static function runAction($program, $action, array $argv = array()) {
    if (self::HELP_ACTION === $action) {
      $actionName = array_shift($argv);
      if (Cli_ActionStorage::exists($actionName)) {
        self::showActionHelp($program, Cli_ActionStorage::get($actionName));
      } else {
        self::showActionsShortHelp($program);
      }

      return;
    }
    if (!Cli_ActionStorage::exists($action)) {
      self::showActionsShortHelp($program);

      return;
    }

    $action = Cli_ActionStorage::get($action);
    $action->getArguments()->setArguments($argv);
    try {
      $action->getArguments()->parse();
    } catch (Zend_Console_Getopt_Exception $e) {
      echo $e . PHP_EOL . PHP_EOL;
      self::showActionHelp($program, $action);

      return;
    }

    $params = array();
    foreach ($action->getArguments()->getOptions() as $option) {
      $params[$option] = $action->getArguments()->getOption($option);
    }
    $request = new Zend_Controller_Request_Simple($action->getName(), $action->getController(), $action->getModule(), $params);

    try {
      Zend_Controller_Front::getInstance()->registerPlugin(new Cli_Controller_Plugin())->setRequest($request)->setRouter(new Cli_Controller_Router())->setResponse(new Zend_Controller_Response_Cli())->throwExceptions(true)->setParam('noViewRenderer', true)->setParam('noErrorHandler', true)->setParam('disableOutputBuffering', true)->setDefaultModule('default')->dispatch();
    } catch (Exception $e) {
      echo $e . PHP_EOL . PHP_EOL;
      self::showActionsShortHelp($program);
    }
  }

  /**
   * @return string
   * @param string $program
   * @param string $action
   */
  public static function getCommonUsageMessage($program, $action = null) {
    $result = 'Usage: ' . PHP_EOL;
    if ($action) {
      $result .= '  ' . $program . ' ' . $action . ' [ options ]' . PHP_EOL;
//			$result .= '  '. $program . ' help '. $action . '        - shows detailed action help' . PHP_EOL;
    } else {
      $result .= '  ' . $program . ' <action> [ options ] - runs specific action' . PHP_EOL;
      $result .= '  ' . $program . ' help                 - shows actions overview' . PHP_EOL;
      $result .= '  ' . $program . ' help <action>        - shows detailed action help' . PHP_EOL;
    }
    $result .= PHP_EOL;

    return $result;
  }

  /**
   * @return void
   * @param string $program
   */
  public static function showActionsShortHelp($program) {
    $result = self::getCommonUsageMessage($program) . 'Available actions are:' . PHP_EOL;

    $maxLength = 1;
    $actions   = array();
    foreach (Cli_ActionStorage::getAll() as $action) {
      $length = strLen($action->getKey());
      if ($length > $maxLength) {
        $maxLength = $length;
      }
      $actions[$action->getKey()] = $action->getDescription();
    }
    foreach ($actions as $key => $description) {
      $result .= '  ' . str_pad($key, $maxLength, ' ', STR_PAD_RIGHT) . ' - ' . $description . PHP_EOL;
    }

    echo $result;
  }

  public static function showActionHelp($program, Cli_Action $action) {
    $result = self::getCommonUsageMessage($program, $action->getKey()) . $action->getHelp();
    echo $result;
  }

}