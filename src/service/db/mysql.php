<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

if (!extension_loaded('pdo_mysql'))
    throw new \Lysine\Service\RuntimeError('Require pdo_mysql extension!');

class Mysql extends \Lysine\Service\DB\Adapter {
    public function quoteTable($table) {
        return $this->quoteColumn($table);
    }

    public function quoteColumn($column) {
        if (is_array($column)) {
            foreach ($column as $k => $c)
                $column[$k] = $this->quoteColumn($c);
            return $column;
        }

        if ($column instanceof Expr)
            return $column;

        $parts = explode('.', str_replace(array('"', "'", ';', '`'), '', $column));
        foreach ($parts as $k => $p)
            $parts[$k] = '`'. $p .'`';

        return new Expr(implode('.', $parts));
    }

    public function lastId($table = null, $column = null) {
        return $this->execute('SELECT last_insert_id()')->getCol();
    }
}
