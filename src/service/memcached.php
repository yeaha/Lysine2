<?php
namespace Lysine\Service;

if (!extension_loaded('memcached'))
    throw new \Lysine\Service\RuntimeError('Require memcached extension');

class Memcached extends \Lysine\Service\IService {
    protected $handler;
    protected $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function __destruct() {
        $this->disconnect();
    }

    public function __call($fn, $args) {
        return $args
             ? call_user_func_array(array($this->connect(), $fn), $args)
             : $this->connect()->$fn();
    }

    public function connect() {
        if ($this->handler)
            return $this->handler;

        $servers = isset($this->config['servers'])
                 ? $this->config['servers']
                 : array(array('127.0.0.1', 11211));

        $handler = new \Memcached;
        if (!$handler->addServers($servers))
            throw new \Lysine\Service\ConnectionError('Cannot connect memcached');

        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $val)
                $memcache->setOption($key, $val);
        }

        return $this->handler = $handler;
    }

    public function disconnect() {
        if ($this->handler)
            unset($this->handler);
        return $this;
    }
}
