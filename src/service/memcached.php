<?php
namespace Lysine\Service;

if (!extension_loaded('memcached'))
    throw new \RuntimeException('Require memcached extension');

class Memcached implements \Lysine\Service\IService {
    protected $handler;
    protected $config;

    public function __construct(array $config=array()) {
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
            throw new \Lysine\Service\ConnectionException('Cannot connect memcached');

        if (isset($config['options'])) {
            foreach ($config['options'] as $key => $val)
                $handler->setOption($key, $val);
        }

        return $this->handler = $handler;
    }

    public function disconnect() {
        if ($this->handler)
            $this->handler = null;
        return $this;
    }
}
