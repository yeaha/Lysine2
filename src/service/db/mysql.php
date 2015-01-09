<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

if (!extension_loaded('pdo_mysql'))
    throw new \RuntimeException('Require pdo_mysql extension!');

class Mysql extends \Lysine\Service\DB\Adapter {
    protected $identifier_symbol = '`';

    public function lastId($table = null, $column = null) {
        return $this->execute('SELECT last_insert_id()')->getCol();
    }

    static protected function prepareConfig(array $config) {
        list($dsn, $user, $password, $options) = parent::prepareConfig($config);

        // 默认禁用buffered query特性
        if (!isset($options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY])) {
            $options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        return array($dsn, $user, $password, $options);
    }
}
