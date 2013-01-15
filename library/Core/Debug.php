<?php
/**
 * Concrete class for generating debug dumps related to the output source.
 *
 * @category   Core
 * @package    Core_Debug
 */

class Core_Debug extends Zend_Debug
{
    protected static $time;

    protected static $memory = 0;

    protected static $memoryPeak = 0;

    protected static $timer = null;

    protected static $vars = null;

    /**
     * Stores enabled state of the Debug.
     *
     * @var boolean
     */
    protected static $enabled = false;

    /**
     * Enable or disable the Debug.  If $enable is false, the Debug
     * is disabled.
     *
     * @param  boolean $enable
     * @return Zend_Db_Profiler Provides a fluent interface
     */
    public static function setEnabled($enable)
    {
        self::$enabled = (boolean) $enable;

        if (self::$enabled && (!self::$timer or !self::$vars)) {
            //require_once 'Zend/Wildfire/Plugin/FirePhp/TableMessage.php';

            self::$timer = new Zend_Wildfire_Plugin_FirePhp_TableMessage('Timer');
            self::$timer->setBuffered(true);
            self::$timer->setHeader(array('Time (sec)', 'Total (sec)', 'Memory (Kb)', 'Total (Kb)', 'Comment', 'File'));
            self::$timer->setOption('includeLineNumbers', false);
        }
    }

    /**
     * dump
     *
     * @return string
     */
    public static function debug() {
        $backtrace = debug_backtrace();

        // get variable name
        $arrLines = file($backtrace[0]["file"]);
        $code = $arrLines[($backtrace[0]["line"] - 1)];
        $arrMatches = array();
        // find call to Sys::dump
        preg_match('/\b\s*Core_Debug::debug\s*\(\s*(.+)\s*\);\s*/i', $code, $arrMatches);

        $varName = isset($arrMatches[1])?$arrMatches[1]:'???';

        $trace = array();

        foreach ($backtrace as $rec) {
            if (isset($rec['function'])) {

                $t['call'] = '';
                if (isset($rec['class'])) {
                    $t['call'] .= $rec['class'] . $rec['type'] . $rec['function'];
                } else {
                    $t['call'] .= $rec['function'];
                }
                $t['call'] .= '(';
                if (sizeof($rec['args'])) {
                    foreach ($rec['args'] as $arg) {
                        if (is_object($arg)) {
                            $t['call'] .= get_class($arg);
                        } else {
                            $arg  = str_replace("\n", ' ', (string) $arg);
                            $t['call'] .= '"' . (strlen($arg) <= 30 ? $arg : substr($arg, 0, 25) . '[...]') . '"';
                        }
                        $t['call'] .= ', ';
                    }
                    $t['call'] = substr($t['call'], 0, -2);
                }
                $t['call'] .= ")";
            }
            $t['file'] = @$rec['file'] . ':' . @$rec['line'];
            $trace[] = $t;
        }

        $debug = new Zend_Wildfire_Plugin_FirePhp_TableMessage('Debug');
        $debug->setBuffered(true);
        $debug->setHeader(array('Value and BackTrace'));
        $debug->setOption('includeLineNumbers', false);


        foreach (func_get_args() as $var) {
            $debug->addRow(array($var));
        }
        $debug->addRow(array($trace));

        $where = basename($backtrace[0]["file"]) .':'. $backtrace[0]["line"];

        $debug->setLabel("Debug: {$varName} ({$where})");
        Zend_Wildfire_Plugin_FirePhp::getInstance()->send($debug);
    }

    /**
     * getGenerateTime
     *
     * @access  public
     * @param   strin $aComment
     * @param   bool $aPrint
     * @return  string time
     */
    public static function getGenerateTime ($aComment = "")
    {
        if (!self::$enabled) return ;

        $back = debug_backtrace();
        list ($msec, $sec)     = explode(chr(32), microtime());
        list ($mTotal, $mSec)  = self::getMemoryUsage();

        if (! isset(self::$time)) {
            self::$time["start"] = $sec + $msec;
            self::$time["section"] = $sec + $msec;

            self::$timer->addRow(array(
                sprintf("%01.4f", 0),
                sprintf("%01.4f", 0),
                $mSec,$mTotal,
                $aComment,
                basename(@$back[0]["file"]) . ':' . @$back[0]["line"],
            ));
        } else {
            $start = self::$time["section"];
            self::$time["section"] = $sec + $msec;

            self::$timer->addRow(array(
                sprintf("%01.4f", round(self::$time["section"] - $start, 4)),
                sprintf("%01.4f", round(self::$time["section"] - self::$time["start"], 4)),
                $mSec,$mTotal,
                $aComment,
                basename(@$back[0]["file"]) . ':' . @$back[0]["line"],
            ));
        }

        self::updateMessageLabel();

        Zend_Wildfire_Plugin_FirePhp::getInstance()->send(self::$timer);
    }

    /**
     * getMemoryUsage
     *
     * @access  public
     * @param   bool $aPrint
     * @return  rettype  return
     */
    protected static function getMemoryUsage ()
    {
        if (! function_exists('memory_get_usage')) {
            if (substr(PHP_OS, 0, 3) == 'WIN') {
                $output = array();
                exec('tasklist /FI "PID eq ' . getmypid() . '" /FO LIST', $output);
                $memory = preg_replace('/[\D]/', '', $output[5]) * 1024;
            } else {
                $pid = getmypid();
                exec("ps -eo%mem,rss,pid | grep $pid", $output);
                $output = explode("  ", $output[0]);
                $memory = @$output[1] * 1024;
            }
        } else {
            $memory = memory_get_usage();
        }
        $memorySection = $memory - self::$memory;
        $memoryTotal   = sprintf("%08s", $memory);
        $memorySection = sprintf("%08s", $memorySection);

        self::$memory = $memory;

        return array($memoryTotal, $memorySection);
    }

    /**
     * getMemoryUsage
     *
     * @access  public
     * @param   bool $aPrint
     * @return  rettype  return
     */
    protected static function getMemoryPeak ()
    {
        if (function_exists('memory_get_peak_usage')) {
            self::$memoryPeak = sprintf("%07s", memory_get_peak_usage());
        }

        return self::$memoryPeak;
    }

    /**
     * Update the label of the message holding the profile info.
     *
     * @return void
     */
    protected static function updateMessageLabel()
    {
        self::$timer->setLabel(sprintf('Timer (%s sec @  %s Kb)', round(self::$time["section"] - self::$time["start"], 4), number_format(self::$memory / 1024, 2, '.', ' ') ));
    }


    /**
     * Лог текстового сообщения
     * @param string $message текст сообщения
     * @param int $debug_level уровень отладки, по умолчанию DUMP(9)
     */
    public static function log($message, $debug_level=9) {
      logStr($message, 0, $debug_level);
    }

    /**
     * Лог содержимого переменной
     * @param mixed $var переменная
     * @param string $name Имя переменной
     * @param int $debug_level уровень отладки, по умолчанию DUMP(9)
     */
    public static function dir($var, $name='Variable', $debug_level=9) {
      logVar($var, $name, $debug_level);
    }
}
