<?php

namespace Lysine\Traits;

// 事件方法
trait Event
{
    public function onEvent($event, $callback)
    {
        return \Lysine\Event::getInstance()->listen($this, $event, $callback);
    }

    public function fireEvent($event, array $args = null)
    {
        return \Lysine\Event::getInstance()->fire($this, $event, $args);
    }

    public function clearEvent($event = null)
    {
        return \Lysine\Event::getInstance()->clear($this, $event);
    }

    public static function subscribeEvent($event, $callback)
    {
        return \Lysine\Event::getInstance()->subscribe(get_called_class(), $event, $callback);
    }
}

// 单例模式
trait Singleton
{
    protected static $__instances__ = array();

    protected function __construct()
    {
    }

    public function __clone()
    {
        throw new \RuntimeException('Cloning '.__CLASS__.' is not allowed');
    }

    public static function getInstance()
    {
        $class = get_called_class();

        if (!isset(static::$__instances__[$class])) {
            static::$__instances__[$class] = new static();
        }

        return static::$__instances__[$class];
    }
}

// 上下文消息
trait Context
{
    protected $context_handler;

    public function setContext($key, $val)
    {
        return $this->getContextHandler(true)->set($key, $val);
    }

    public function getContext($key = null)
    {
        return $this->getContextHandler(true)->get($key);
    }

    public function hasContext($key)
    {
        return $this->getContextHandler(true)->has($key);
    }

    public function removeContext($key)
    {
        return $this->getContextHandler(true)->remove($key);
    }

    public function clearContext()
    {
        return $this->getContextHandler(true)->clear();
    }

    public function saveContext()
    {
        return $this->getContextHandler(true)->save();
    }

    public function setContextHandler(\Lysine\ContextHandler $handler)
    {
        $this->context_handler = $handler;
    }

    public function getContextHandler($throw_exception = false)
    {
        if (!$this->context_handler && $throw_exception) {
            throw new \RuntimeException('Please set context handler before use');
        }

        return $this->context_handler ?: false;
    }
}
