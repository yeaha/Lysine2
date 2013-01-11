<?php
namespace Lysine\Traits;

use Lysine\RuntimeError;
use Lysine\Event;

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
