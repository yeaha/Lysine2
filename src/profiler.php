<?php
namespace Lysine;

class Profiler {
    static private $instance;

    private $stack = array();
    private $time = array();

    protected function __construct() {
    }

    public function __toString() {
        $lines = array();
        foreach ($this->time as $name => $use_time)
            $lines[] = sprintf('%s: %ss', $name, $use_time);

        return implode(PHP_EOL, $lines);
    }

    public function start($name) {
        $this->stack[] = array($name, microtime(true));
    }

    public function stop() {
        if (!$this->stack)
            return false;

        list($name, $start_time) = array_pop($this->stack);
        return $this->time[$name] = microtime(true) - $start_time;
    }

    public function getRuntime($name = null) {
        if ($name === null) return $this->time;
        return isset($this->time[$name]) ? $this->time[$name] : false;
    }

    static public function instance() {
        return self::$instance ?: (self::$instance = new static);
    }
}
