<?php

class Cli_Getopt extends Zend_Console_Getopt {

  /**
   * @return boolean
   */
  public function hasRules() {
    return (!empty($this->_rules));
  }

  /**
   * Parse command-line arguments and find both long and short
   * options.
   *
   * Also find option parameters, and remaining arguments after
   * all options have been parsed.
   *
   * @return Zend_Console_Getopt|null Provides a fluent interface
   * @throws Zend_Console_Getopt_Exception
   */
  public function parse() {
    if (null === parent::parse()) {
      return null;
    }

    if ($this->hasRules()) {
      foreach ($this->_rules as $flag => $options) {
        if ('required' !== $options['param']) {
          continue;
        }
        if (null === $this->getOption($flag)) {
          throw new Zend_Console_Getopt_Exception('Option "'. $flag . '" is required but not specified.', $this->getUsageMessage());
        }
      }
    }
    return $this;
  }

  /**
   * @return null|string
   */
  public function getArgumentsDescription() {
    if (!$this->hasRules()) {
      return null;
    }
    $result = $this->getUsageMessage();
    $lines = explode("\n", $result);
    array_shift($lines);
    $result = 'Options:' . PHP_EOL . '  ' . implode(PHP_EOL . '  ', $lines);
    return $result;
  }

}