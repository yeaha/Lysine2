<?php
namespace Lysine;

class Config {
    static private $config = array();

    static public function import(array $config) {
        self::$config = array_merge(self::$config, $config);
    }

    static public function get($key) {
        if (strpos($key, ',') === false)
             return isset(self::$config[$key]) ? self::$config[$key] : false;

        $config = &self::$config;
        $path = preg_split('/\s?,\s?/', $key, NULL, PREG_SPLIT_NO_EMPTY);

        foreach ($path as $key) {
            if (!isset($config[$key]))
                return false;
            $config = &$config[$key];
        }

        return $config;
    }
}

class Event {
    static protected $instance;

    protected $listen = array();
    protected $subscribe = array();

    public function listen($object, $event, $callback) {
        $key = $this->keyOf($object);
        $this->listen[$key][$event][] = $callback;
    }

    public function subscribe($class, $event, $callback) {
        $class = strtolower(ltrim($class, '\\'));
        $this->subscribe[$class][$event][] = $callback;
    }

    public function fire($object, $event, array $args = null) {
        $fire = 0;  // 回调次数

        if (!$this->listen)
            return $fire;

        $key = $this->keyOf($object);
        if (isset($this->listen[$key][$event])) {
            foreach ($this->listen[$key][$event] as $callback) {
                $args ? call_user_func_array($callback, $args) : call_user_func($callback);
                $fire++;
            }
        }

        if (!$this->subscribe)
            return $fire;

        $class = strtolower(get_class($object));
        if (!isset($this->subscribe[$class][$event])) return $fire;

        // 订阅回调参数
        // 第一个参数是事件对象
        // 第二个参数是事件参数
        $args = $args ? array($object, $args) : array($object);
        foreach ($this->subscribe[$class][$event] as $callback) {
            call_user_func_array($callback, $args);
            $fire++;
        }

        return $fire;
    }

    public function clear($object, $event = null) {
        $key = $this->keyOf($object);

        if ($event === null) {
            unset($this->listen[$key]);
        } else {
            unset($this->listen[$key][$event]);
        }
    }

    protected function keyOf($obj) {
        return is_object($obj)
             ? spl_object_hash($obj)
             : $obj;
    }

    static public function instance() {
        return self::$instance ?: (self::$instance = new static);
    }
}

class Session implements \ArrayAccess {
    static private $instance;

    protected $start;
    protected $data = array();
    protected $snapshot = array();

    protected function __construct() {
        $this->start = session_status() === PHP_SESSION_ACTIVE;

        if ($this->start)
            $this->data = $_SESSION instanceof Session
                        ? $_SESSION->toArray()
                        : $_SESSION;

        $this->snapshot = $this->data;
    }

    public function offsetExists($offset) {
        $this->start();
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset) {
        $this->start();
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->start();
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        $this->start();
        unset($this->data[$offset]);
    }

    public function commit() {
        if (!$this->start)
            return false;

        $_SESSION = $this->data;
        session_write_close();

        $this->snapshot = $this->data;
        $_SESSION = $this;
    }

    public function reset() {
        $this->data = $this->snapshot;
    }

    public function destroy() {
        $this->start();

        session_destroy();
        $this->reset();
    }

    public function start() {
        if ($this->start)
            return true;

        if (PHP_SAPI == 'cli' || session_status() === PHP_SESSION_DISABLED)
            return false;

        session_start();
        $this->data = $_SESSION;
        $this->snapshot = $_SESSION;

        $_SESSION = $this;
        $this->start = true;
    }

    public function toArray() {
        return $this->data;
    }

    //////////////////// static method ////////////////////

    static public function initialize() {
        if (!isset($GLOBALS['_SESSION']) or !($GLOBALS['_SESSION'] instanceof Session))
            $GLOBALS['_SESSION'] = self::instance();
        return self::instance();
    }

    static public function instance() {
        return self::$instance
            ?: (self::$instance = new static);
    }
}
