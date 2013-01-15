<?php

class Cli_Action {

  const PART_SEPARATOR     = '.';
  const DEFAULT_CONTROLLER = 'index';
  const DEFAULT_MODULE     = 'cli';

  /**
   * Action name
   *
   * @var string
   */
  private $name;

  /**
   * Action controller name
   *
   * @var string
   */
  private $controller;

  /**
   * Action module name
   *
   * @var string
   */
  private $module;

  /**
   * Action arguments
   * @var Cli_Getopt
   */
  private $arguments;

  /**
   * @return Cli_Action
   * @param string $name
   * @param string $controller
   * @param string $module
   */
  public function __construct($name, $controller = self::DEFAULT_CONTROLLER, $module = self::DEFAULT_MODULE) {
    $this->name       = $name;
    $this->controller = $controller;
    $this->module     = $module;
    $this->arguments  = new Cli_Getopt(array());
  }

  /**
   * @return Cli_Action
   * @param string $name
   * @param string $controller
   * @param string $module
   */
  public static function create($name, $controller = self::DEFAULT_CONTROLLER, $module = self::DEFAULT_MODULE) {
    return new self($name, $controller, $module);
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getController() {
    return $this->controller;
  }

  /**
   * @return string
   */
  public function getModule() {
    return $this->module;
  }

  /**
   * @return string
   */
  public function getKey() {
    return
      $this->getModule()
      . self::PART_SEPARATOR . $this->getController()
      . self::PART_SEPARATOR . $this->getName()
    ;
  }

  /**
   * @var string
   */
  private $description = null;

  /**
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @return Cli_Action
   * @param string $value
   */
  public function setDescription($value) {
    $this->description = $value;
    return $this;
  }

  /**
   * Adds new argument to action
   *
   * @return Cli_Action
   * @param string $argument
   * @param string $description
   */
  public function addArgument($argument, $description) {
    $this->arguments->addRules(array(
      $argument => $description
    ));
    return $this;
  }

  /**
   * @return Cli_Getopt
   */
  public function getArguments() {
    return $this->arguments;
  }

  /**
   * @return string
   */
  public function getHelp() {
    $result = '';
    if ($this->getDescription()) {
      $result .= $this->getDescription() . PHP_EOL;
    }
    $result .= $this->getArguments()->getArgumentsDescription();
    return $result;
  }

}