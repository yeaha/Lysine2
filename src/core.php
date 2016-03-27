<?php

namespace Lysine;

class Config
{
    private static $config = array();

    public static function import(array $config)
    {
        self::$config = array_merge(self::$config, $config);
    }

    public static function get($keys = null)
    {
        $keys = $keys === null
              ? null
              : is_array($keys) ? $keys : func_get_args();

        if ($keys === null) {
            return self::$config;
        }

        $config = &self::$config;

        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                return false;
            }
            $config = &$config[$key];
        }

        return $config;
    }
}

class Event
{
    use \Lysine\Traits\Singleton;

    protected $listen = array();
    protected $subscribe = array();

    public function listen($object, $event, $callback)
    {
        $key = $this->keyOf($object);
        $this->listen[$key][$event][] = $callback;
    }

    public function subscribe($class, $event, $callback)
    {
        $class = strtolower(ltrim($class, '\\'));
        $this->subscribe[$class][$event][] = $callback;
    }

    public function fire($object, $event, array $args = null)
    {
        $fire = 0;  // 回调次数

        if (!$this->listen && !$this->subscribe) {
            return $fire;
        }

        $key = $this->keyOf($object);
        if (isset($this->listen[$key][$event])) {
            foreach ($this->listen[$key][$event] as $callback) {
                $args ? call_user_func_array($callback, $args) : call_user_func($callback);
                ++$fire;
            }
        }

        if (!$this->subscribe) {
            return $fire;
        }

        $class = strtolower(get_class($object));
        if (!isset($this->subscribe[$class][$event])) {
            return $fire;
        }

        // 订阅回调参数
        // 第一个参数是事件对象
        // 第二个参数是事件参数
        $args = $args ? array($object, $args) : array($object);
        foreach ($this->subscribe[$class][$event] as $callback) {
            call_user_func_array($callback, $args);
            ++$fire;
        }

        return $fire;
    }

    public function clear($object, $event = null)
    {
        $key = $this->keyOf($object);

        if ($event === null) {
            unset($this->listen[$key]);
        } else {
            unset($this->listen[$key][$event]);
        }
    }

    protected function keyOf($obj)
    {
        return is_object($obj)
             ? spl_object_hash($obj)
             : $obj;
    }
}
