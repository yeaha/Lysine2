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
}
