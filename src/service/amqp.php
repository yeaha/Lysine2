<?php // README: Advanced Message Queue Protocol Service
namespace Lysine\Service;

use \AMQPChannel;
use \AMQPConnection;
use \AMQPEnvelope;
use \AMQPExchange;
use \AMQPQueue;

if (!extension_loaded('amqp'))
    throw new \Lysine\Service\RuntimeError('Require amqp extension');

class AMQP implements \Lysine\Service\IService {
    protected $connection;
    protected $channel;
    protected $config;

    public function __construct(array $config) {
        $this->config = self::prepareConfig($config);
    }

    public function __destruct() {
        $this->disconnect();
    }

    public function connect() {
        if ($this->connection)
            return $this->connection;

        return $this->connection = new AMQPConnection( $this->config );
    }

    public function disconnect() {
        if ($this->channel instanceof AMQPChannel)
            unset($this->channel);

        if ($this->connection instanceof AMQPConnection) {
            $this->connection->disconnect();
            unset($this->connection);
        }

        return $this;
    }

    public function channel($new = false) {
        if (!$new && $this->channel)
            return $this->channel;

        $connection = $this->connect();

        if (!$connection->isConnected() && !$connection->connect())
            throw new \Lysine\Service\ConnectionError('Cannot connect to the broker');

        return $this->channel = new AMQPChannel($connection);
    }

    public function declareExchange($name, $type = null, $flag = null, $arguments = null) {
        $exchange = new AMQPExchange($this->channel());
        $exchange->setName($name);

        $exchange->setType($type ?: AMQP_EX_TYPE_DIRECT);

        if ($flag !== null)
            $exchange->setFlags($flag);

        if ($arguments !== null)
            $exchange->setArguments($arguments);

        $exchange->declare();
        return $exchange;
    }

    public function declareQueue($name, $flag = null, $arguments = null) {
        $queue = new AMQPQueue($this->channel());
        $queue->setName($name);

        if ($flag !== null)
            $queue->setFlags($flag);

        if ($arguments !== null)
            $queue->setArguments($arguments);

        $queue->declare();
        return $queue;
    }

    static protected function prepareConfig(array $config) {
        return array(
            'host' => isset($config['host']) ? $config['host'] : ini_get('amqp.host'),
            'vhost' => isset($config['vhost']) ? $config['vhost'] : ini_get('amqp.vhost'),
            'port' => isset($config['port']) ? $config['port'] : ini_get('amqp.port'),
            'login' => isset($config['login']) ? $config['login'] : ini_get('amqp.login'),
            'password' => isset($config['password']) ? $config['password'] : ini_get('amqp.password'),
        );
    }
}
