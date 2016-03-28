<?php
// README: Advanced Message Queue Protocol Service
namespace Lysine\service;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;

if (!extension_loaded('amqp')) {
    throw new \RuntimeException('Require amqp extension');
}

class amqp implements \Lysine\Service\IService
{
    protected $connection;
    protected $channel;
    protected $config;

    public function __construct(array $config = array())
    {
        $this->config = self::prepareConfig($config);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    // return AMQPConnection
    public function connection()
    {
        return $this->connection ?: $this->connection = new AMQPConnection($this->config);
    }

    public function disconnect()
    {
        if ($this->channel instanceof AMQPChannel) {
            $this->channel = null;
        }

        if ($this->connection instanceof AMQPConnection) {
            $this->connection->disconnect();
            $this->connection = null;
        }

        return $this;
    }

    // return AMQPChannel
    public function channel($new = false)
    {
        $connection = $this->connection();

        if (!$connection->isConnected() && !$connection->connect()) {
            throw new \Lysine\Service\ConnectionException('Cannot connect to the broker');
        }

        if ($new || !$this->channel) {
            $this->channel = new AMQPChannel($connection);
        }

        return $this->channel;
    }

    // return AMQPExchange
    public function declareExchange($name, $type = null, $flag = null, $arguments = null)
    {
        $exchange = new AMQPExchange($this->channel());
        $exchange->setName($name);

        $exchange->setType($type ?: AMQP_EX_TYPE_DIRECT);

        if ($flag !== null) {
            $exchange->setFlags($flag);
        }

        if ($arguments !== null) {
            $exchange->setArguments($arguments);
        }

        $exchange->declare();

        return $exchange;
    }

    // return AMQPQueue
    public function declareQueue($name, $flag = null, $arguments = null)
    {
        $queue = new AMQPQueue($this->channel());
        $queue->setName($name);

        if ($flag !== null) {
            $queue->setFlags($flag);
        }

        if ($arguments !== null) {
            $queue->setArguments($arguments);
        }

        $queue->declare();

        return $queue;
    }

    protected static function prepareConfig(array $config)
    {
        return array(
            'host' => isset($config['host']) ? $config['host'] : ini_get('amqp.host'),
            'vhost' => isset($config['vhost']) ? $config['vhost'] : ini_get('amqp.vhost'),
            'port' => isset($config['port']) ? $config['port'] : ini_get('amqp.port'),
            'login' => isset($config['login']) ? $config['login'] : ini_get('amqp.login'),
            'password' => isset($config['password']) ? $config['password'] : ini_get('amqp.password'),
        );
    }
}
