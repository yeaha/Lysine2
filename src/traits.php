<?php
namespace Lysine\Traits;

use Lysine\Event;
use Lysine\RuntimeError;

// 事件方法
trait EventMethods {
    public function listenEvent($event, $callback) {
        return Event::instance()->listen($this, $event, $callback);
    }

    public function fireEvent($event, array $args = null) {
        return Event::instance()->fire($this, $event, $args);
    }
}

// 单例模式
trait Singleton {
    static protected $instance;

    protected function __construct() {}

    public function __clone() {
        throw new RuntimeError('Cloning '. __CLASS__ .' is not allowed');
    }

    static public function getInstance() {
        return static::$instance ?: (static::$instance = new static);
    }
}

// 上下文消息
trait Context {
    protected $context_handler;

    public function setContext($key, $val) {
        return $this->getContextHandler()->set($key, $val);
    }

    public function getContext($key = null) {
        return $this->getContextHandler()->get($key);
    }

    public function hasContext($key) {
        return $this->getContextHandler()->has($key);
    }

    public function removeContext($key) {
        return $this->getContextHandler()->remove($key);
    }

    public function clearContext() {
        return $this->getContextHandler()->clear();
    }

    protected function setContextHandler(\Lysine\ContextHandler $handler) {
        $this->conntext_handler = $handler;
    }

    protected function getContextHandler() {
        if (!$this->context_handler)
            throw new RuntimeError('Please set context handler before use');

        return $this->context_handler;
    }
}
