<?php

namespace Lysine\service;

if (!extension_loaded('memcached')) {
    throw new \RuntimeException('Require memcached extension');
}

class memcached implements \Lysine\Service\IService
{
    protected $handler;
    protected $config;

    /**
     * @param array $config
     *
     * @example
     * new \Lysine\Service\Memcached(array(
     *     'servers' => array(
     *         array('192.168.1.2', 11211, 10),
     *         array('192.168.1.3', 11211, 20),
     *         // ...
     *     ),
     *     'options' => array(  // 可选
     *         \Memcached::OPT_PREFIX_KEY => "foobar",
     *         // ...
     *     ),
     * ));
     */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function __call($fn, $args)
    {
        return $args
             ? call_user_func_array(array($this->connect(), $fn), $args)
             : $this->connect()->$fn();
    }

    /**
     * 连接memcached.
     *
     * @return \Memcached
     */
    public function connect()
    {
        if ($this->handler) {
            return $this->handler;
        }

        $servers = isset($this->config['servers'])
                 ? $this->config['servers']
                 : array(array('127.0.0.1', 11211));

        $handler = new \Memcached();
        if (!$handler->addServers($servers)) {
            throw new \Lysine\Service\ConnectionException('Cannot connect memcached');
        }

        if (isset($config['options'])) {
            $handler->setOptions($config['options']);
        }

        return $this->handler = $handler;
    }

    /**
     * 断开连接.
     *
     * @return $this
     */
    public function disconnect()
    {
        if ($this->handler) {
            $this->handler->quit();
            $this->handler = null;
        }

        return $this;
    }
}
