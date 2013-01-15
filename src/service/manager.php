<?php
namespace Lysine\Service;

class Manager {
    use \Lysine\Traits\Event;

    const BEFORE_CREATE_EVENT = 'before create service instance';
    const AFTER_CREATE_EVENT = 'after create service instance';

    static private $instance;

    protected $instances = array();
    protected $dispatcher = array();
    protected $config = array();

    protected function __construct() {
    }

    public function __destruct() {
        $this->instances = array();
        $this->dispatcher = array();
    }

    public function importConfig(array $config) {
        $this->config = array_merge($this->config, $config);
    }

    public function setDispatcher($name, $callback) {
        $this->dispatcher[$name] = $callback;
        return $this;
    }

    public function get($name, $args = null) {
        if (isset($this->dispatcher[$name])) {
            $callback = $this->dispatcher[$name];

            $args = $args === null
                  ? null
                  : is_array($args) ? $args : array_slice(func_get_args(), 1);

            $dispatcher_name = $name;
            $name = $args === null
                  ? call_user_func($callback)
                  : call_user_func_array($callback, $args);

            if (!$name)
                throw new \Lysine\Service\RuntimeError('Service dispatcher ['.$dispatcher_name.'] MUST return a service name');

            if ($name instanceof IService)
                return $name;
        }

        if (isset($this->instances[$name]))
            return $this->instances[$name];

        $config = $this->getConfig($name);

        $this->fireEvent(self::BEFORE_CREATE_EVENT, array($name, $config));

        $class = $config['class'];
        unset($config['class']);
        $service = new $class($config);

        $this->instances[$name] = $service;

        $this->fireEvent(self::AFTER_CREATE_EVENT, array($name, $config, $service));

        return $service;
    }

    protected function getConfig($name) {
        if (!isset($this->config[$name]))
            throw new \Lysine\Service\RuntimeError('Undefined Service: '. $name);

        $config = $this->config[$name];
        if (!isset($config['__IMPORT__']))
            return $config;

        $import_config = $this->getConfig($config['__IMPORT__']);

        $config = array_merge($import_config, $config);
        unset($config['__IMPORT__']);

        return $config;
    }

    //////////////////// static method ////////////////////
    static public function instance() {
        return self::$instance ?: (self::$instance = new static);
    }
}

interface IService {
    public function __construct(array $config = array());
}
