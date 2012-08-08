<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

class Pgsql extends \Lysine\Service\DB\Adapter {
    protected $savepoint = array();

    public function qtab($table) {
        return $this->qcol($table);
    }

    public function qcol($column) {
        if (is_array($column)) {
            foreach ($column as $k => $c)
                $column[$k] = $this->qcol($c);
            return $column;
        }

        if ($column instanceof Expr)
            return $column;

        $parts = explode('.', str_replace('"', '', $column));
        foreach ($parts as $k => $p)
            $parts[$k] = '"'. $p .'"';

        return new Expr(implode('.', $parts));
    }

    public function begin() {
        if ($this->transacter_counter) {
            $savepoint = 'SAVEPOINT_'. $this->transacter_counter;
            $this->savepoint[] = $savepoint;

            $this->execute('SAVEPOINT '. $savepoint);
        } else {
            $this->execute('BEGIN');
        }

        $this->transacter_counter++;
        return true;
    }

    public function commit() {
        if (!$this->transacter_counter)
            return false;

        if ($this->savepoint) {
            $savepoint = array_pop($this->savepoint);

            $this->execute('RELEASE SAVEPOINT '. $savepoint);
        } else {
            $this->execute('COMMIT');
        }

        $this->transacter_counter--;
        return true;
    }

    public function rollback() {
        if (!$this->transacter_counter)
            return false;

        if ($this->savepoint) {
            $savepoint = array_pop($this->savepoint);

            $this->execute('ROLLBACK TO SAVEPOINT '. $savepoint);
        } else {
            $this->execute('ROLLBACK');
        }

        $this->transacter_counter--;
        return true;
    }
}
