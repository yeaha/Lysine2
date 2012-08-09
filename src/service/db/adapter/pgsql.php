<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

if (!extension_loaded('pdo_pgsql'))
    throw new \Lysine\Service\RuntimeError('Require pdo_pgsql extension!');

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

        $parts = explode('.', str_replace(array('"', "'", ';'), '', $column));
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

    public function lastId($table = null, $column = null) {
        $sql = ($table && $column)
             ? sprintf("SELECT CURRVAL('%s')", $this->sequenceName($table, $column))
             : 'SELECT LASTVAL()';

        return $this->execute($sql)->getCol();
    }

    public function nextId($table, $column) {
        $sql = sprintf("SELECT NEXTVAL('%s')", $this->sequenceName($table, $column));
        return $this->execute($sql)->getCol();
    }

    //////////////////// protected method ////////////////////

    protected function sequenceName($table, $column) {
        list($schema, $table) = $this->parseTableName($table);

        $sequence = sprintf('%s_%s_seq', $table, $column);
        if ($schema)
            $sequence = $schema .'.'. $sequence;

        return $this->qcol($sequence);
    }

    protected function parseTableName($table) {
        $table = str_replace('"', '', $table);
        $pos = strpos($table, '.');

        if ($pos) {
            list($schema, $table) = explode('.', $table, 2);
            return array($schema, $table);
        } else {
            return array(null, $table);
        }
    }

    //////////////////// static method ////////////////////

    static public function decodeArray($array) {
        $array = explode(',', trim($array, '{}'));
        return $array;
    }

    static public function encodeArray(array $array) {
        return $array ? sprintf('{"%s"}', implode('","', $array)) : null;
    }

    static public function decodeHstore($hstore) {
        $result = array();
        if (!$hstore) return $result;

        foreach (preg_split('/"\s*,\s*"/', $hstore) as $pair) {
            $pair = explode('=>', $pair);
            if (count($pair) !== 2) continue;

            list($k, $v) = $pair;
            $k = trim($k, '\'" ');
            $v = trim($v, '\'" ');
            $result[$k] = $v;
        }
        return $result;
    }

    static public function encodeHstore(array $array, $new_style = false) {
        if (!$array) return null;

        if (!$new_style) {
            $result = array();
            foreach ($array as $k => $v) {
                $v = str_replace('\\', '\\\\\\\\', $v);
                $v = str_replace('"', '\\\\"', $v);
                $v = str_replace("'", "\\'", $v);
                $result[] = sprintf('"%s"=>"%s"', $k, $v);
            }
            return new Expr('E\''. implode(',', $result) .'\'::hstore');
        } else {
            $result = 'hstore(ARRAY[%s], ARRAY[%s])';
            $cols = $vals = array();
            foreach ($array as $k => $v) {
                $v = str_replace('\\', '\\\\', $v);
                $v = str_replace("'", "\\'", $v);
                $cols[] = $k;
                $vals[] = $v;
            }

            return new Expr(sprintf(
                $result,
                "'". implode("','", $cols) ."'",
                "E'". implode("',E'", $vals) ."'"
            ));
        }
    }
}
