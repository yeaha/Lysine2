<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

if (!extension_loaded('pdo_sqlite'))
    throw new \Lysine\Service\RuntimeError('Require pdo_sqlite extension!');

class Sqlite extends \Lysine\Service\DB\Adapter {
    protected $savepoint = array();

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

        $column = '"'. trim($column, '"') .'"';

        return new Expr($column);
    }

    public function begin() {
        if ($this->transaction_counter) {
            $savepoint = 'SAVEPOINT_'. $this->transaction_counter;
            $this->savepoint[] = $savepoint;

            $this->execute('SAVEPOINT '. $savepoint);
        } else {
            $this->execute('BEGIN');
        }

        $this->transaction_counter++;
        return true;
    }

    public function commit() {
        if (!$this->transaction_counter)
            return false;

        if ($this->savepoint) {
            $savepoint = array_pop($this->savepoint);

            $this->execute('RELEASE SAVEPOINT '. $savepoint);
        } else {
            $this->execute('COMMIT');
        }

        $this->transaction_counter--;
        return true;
    }

    public function rollback() {
        if (!$this->transaction_counter)
            return false;

        if ($this->savepoint) {
            $savepoint = array_pop($this->savepoint);

            $this->execute('ROLLBACK TO SAVEPOINT '. $savepoint);
        } else {
            $this->execute('ROLLBACK');
        }

        $this->transaction_counter--;
        return true;
    }

    public function lastId($table = null, $column = null) {
        return $this->execute('SELECT last_insert_rowid()')->getCol();
    }
}
