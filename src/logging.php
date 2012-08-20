<?php
namespace Lysine;

class Logging {
    const CRITICAL = 50;
    const ERROR = 40;
    const WARNING = 30;
    const INFO = 20;
    const DEBUG = 10;
    const NOTEST = 0;

    static protected $instance = array();

    protected $pid;
    protected $level = self::WARNING;
    protected $handler = array();

    public function setLevel($level) {
        $this->level = (int)$level;
        return $this;
    }

    public function addHandler(\Lysine\Logging\Handler $handler) {
        $this->handler[] = $handler;
        return $this;
    }

    public function critical($message) {
        return $this->log($message, self::CRITICAL);
    }

    public function error($message) {
        return $this->log($message, self::ERROR);
    }

    public function warning($message) {
        return $this->log($message, self::WARNING);
    }

    public function info($message) {
        return $this->log($message, self::INFO);
    }

    public function debug($message) {
        return $this->log($message, self::DEBUG);
    }

    public function exception(\Exception $exception) {
        if ($previous = $exception->getPrevious())
            $this->exception($previous);

        $messages = array(
            sprintf('Exception %s (%d)', get_class($exception), $exception->getCode()),
            'Message: '. $exception->getMessage(),
        );

        foreach (explode("\n", $exception->getTraceAsString()) as $trace)
            $messages[] = $trace;

        if ($exception instanceof \Lysine\Error && ($more = $exception->getMore()))
            $message[] = 'More: '. serialize($more);

        foreach ($messages as $message)
            $this->error($message);
    }

    //////////////////// protected method ////////////////////

    protected function log($message, $level) {
        if (!$this->handler)
            return false;

        if ($level < $this->level)
            return false;

        $record = array(
            'message' => $message,
            'level' => $level,
            'pid' => $this->getPid(),
        );

        foreach ($this->handler as $handler)
            $handler->emit($record);

        return true;
    }

    protected function getPid() {
        return $this->pid ?: ($this->pid = getmypid());
    }

    //////////////////// static method ////////////////////

    static public function getLevelName($level) {
        switch ($level) {
            case 50: return 'CRITICAL';
            case 40: return 'ERROR';
            case 30: return 'WARNING';
            case 20: return 'INFO';
            case 10: return 'DEBUG';
            case 0: return 'NOTEST';
            default: return $level;
        }
    }

    static public function factory($name) {
        if (isset(self::$instance[$name]))
            return self::$instance[$name];

        return self::$instance[$name] = new static;
    }
}

namespace Lysine\Logging;

use Lysine\Logging;

interface Handler {
    public function emit(array $record);
}

class FileHandler implements Handler {
    protected $time_format = '%F %T';     // see strftime()
    protected $buffer_max_size = 8192;
    protected $file_name;

    protected $buffer = array();
    protected $buffer_size = 0;

    public function __construct(array $options) {
        if (isset($options['time_format']))
            $this->time_format = $options['time_format'];

        if (isset($options['buffer_max_size']))
            $this->buffer_max_size = $options['buffer_max_size'];

        if (!isset($options['file_name']))
            throw new \Lysine\Runtime('Need log file name');

        $this->file_name = strftime($options['file_name'], time());
    }

    public function __destruct() {
        $this->flush();
    }

    public function emit(array $record) {
        $record['time'] = strftime($this->time_format, time());
        $record['level'] = Logging::getLevelName($record['level']);

        $line = sprintf('%s %-8s %-8s %s', $record['time'], $record['pid'], $record['level'], $record['message']);
        $this->write($line);
    }

    protected function write($line) {
        $this->buffer[] = $line;
        $this->buffer_size += strlen($line);

        if ($this->buffer_size >= $this->buffer_max_size)
            $this->flush();
    }

    protected function flush() {
        if (!$this->buffer_size)
            return false;

        if (!$fp = fopen($this->file_name, 'a'))
            return false;

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, implode("\n", $this->buffer) ."\n");
            flock($fp, LOCK_UN);
            $this->buffer = array();
            $this->buffer_size = 0;
        }

        return fclose($fp);
    }
}

class FirePHPHandler implements Handler {
    private $handler;

    public function __construct() {
        if (!class_exists('FirePHP'))
            throw new \Lysine\RuntimeError('Require FirePHP library');
        $this->handler = \FirePHP::getInstance(true);
    }

    public function __call($method, array $args) {
        return $args
             ? call_user_func_array(array($this->handler, $method), $args)
             : $this->handler->$method();
    }

    public function emit(array $record) {
        switch ($record['level']) {
            case Logging::INFO:
                return $this->handler->info($record['message']);
            case Logging::WARNING:
                return $this->handler->warn($record['message']);
            case Logging::ERROR:
            case Logging::CRITICAL:
                return $this->handler->error($record['message']);
            default:
                return $this->handler->log($record['message']);
        }
    }
}

class FireLoggerHandler implements Handler {
    private $handler;

    public function __construct() {
        if (!class_exists('FireLogger'))
            throw new \Lysine\RuntimeError('Require FireLogger library');

        defined('FIRELOGGER_NO_CONFLICT') or define('FIRELOGGER_NO_CONFLICT', true);
        defined('FIRELOGGER_NO_DEFAULT_LOGGER') or define('FIRELOGGER_NO_DEFAULT_LOGGER', true);
        defined('FIRELOGGER_NO_EXCEPTION_HANDLER') or define('FIRELOGGER_NO_EXCEPTION_HANDLER', true);
        defined('FIRELOGGER_NO_ERROR_HANDLER') or define('FIRELOGGER_NO_ERROR_HANDLER', true);

        $this->handler = new \FireLogger();
    }

    public function __call($method, array $args) {
        return $args
             ? call_user_func_array(array($this->handler, $method), $args)
             : $this->handler->$method();
    }

    public function emit(array $record) {
        switch ($record['level']) {
            case Logging::INFO:
                return $this->handler->log('info', $record['message']);
            case Logging::WARNING:
                return $this->handler->log('warning', $record['message']);
            case Logging::ERROR:
                return $this->handler->log('error', $record['message']);
            case Logging::CRITICAL:
                return $this->handler->log('critical', $record['message']);
            default:
                return $this->handler->log($record['message']);
        }
    }
}

class ChromePHPHandler implements Handler {
    public function __construct() {
        if (!class_exists('ChromePHP'))
            throw new \Lysine\RuntimeError('Require ChromePHP library');
    }

    public function emit(array $record) {
        switch ($record['level']) {
            case Logging::WARNING:
                return \ChromePHP::warn($record['message']);
            case Logging::ERROR:
            case Logging::CRITICAL:
                return \ChromePHP::error($record['message']);
            default:
                return \ChromePHP::log($record['message']);
        }
    }
}
