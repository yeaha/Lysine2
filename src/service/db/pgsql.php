<?php
namespace Lysine\Service\DB\Adapter;

use Lysine\Service\DB\Expr;

if (!extension_loaded('pdo_pgsql'))
    throw new \Lysine\Service\RuntimeError('Require pdo_pgsql extension!');

class Pgsql extends \Lysine\Service\DB\Adapter {
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

        $parts = explode('.', str_replace(array('"', "'", ';'), '', $column));
        foreach ($parts as $k => $p)
            $parts[$k] = '"'. $p .'"';

        return new Expr(implode('.', $parts));
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

        return $this->quoteColumn($sequence);
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

    // postgresql hstore -> php array
    static public function decodeHstore($hstore) {
        $re = '/"(.+)(?<!\\\)"=>(""|NULL|".+(?<!\\\)"),?/U';
        $array = array();

        if ($hstore === '' || $hstore === NULL)
            return $array;

        do {
            // 匹配一对key=>value
            if (!preg_match($re, $hstore, $match))
                break;

            $s = $match[0];
            $k = $match[1];
            $v = $match[2];

            if ($v === 'NULL') {
                $v = NULL;
            } else {
                // 如果value不是NULL，匹配到的结果需要去掉两边的"
                $v = substr($v, 1, -1);
            }

            // 反转key value的转义字符
            $search = array('\"', '\\\\');
            $replace = array('"', '\\');
            $k = str_replace($search, $replace, $k);
            if ($v !== NULL)
                $v = str_replace($search, $replace, $v);

            $array[$k] = $v;

            // 把匹配到的key=>value从字符串中去掉，继续下一次匹配
            $hstore = substr($hstore, strlen($s));
            if (substr($hstore, 0, 2) == ', ')
                $hstore = substr($hstore, 2);

            if (!$hstore)
                break;
        } while (true);

        return $array;
    }

    // php array -> postgresql hstore
    static public function encodeHstore($array, $new_style = false) {
        if (!$array)
            return NULL;

        if (!is_array($array))
            return $array;

        $expr = array();

        foreach ($array as $key => $val) {
            if ($key === NULL)
                continue;

            $search = array('\\', "'", '"');
            $replace = array('\\\\', "''", '\"');

            $key = str_replace($search, $replace, $key);
            $val = $val === NULL
                 ? 'NULL'
                 : '"'.str_replace($search, $replace, $val).'"';

            $expr[] = sprintf('"%s"=>%s', $key, $val);
        }

        return new Expr(sprintf("'%s'::hstore", implode(',', $expr)));
    }
}
