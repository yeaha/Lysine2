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

    // postgresql array -> php array
    static public function decodeArray($pg_array) {
        if (!$pg_array)
            return array();

        $pg_array = trim($pg_array, '{}');

        // 如果没有包含"，直接简单的用,拆分
        if (strpos($pg_array, '"') === false) {
            $array = explode(',', $pg_array);
            foreach ($array as $key => $val) {
                if ($val === 'NULL')
                    $array[$key] = NULL;
            }

            return $array;
        }

        ////////////////////////////////////////////////////////////

        $array = array();

        do {
            if (substr($pg_array, 0, 1) === '"') {
                if (!preg_match('/^"(.*)(?<!\\\)",?/U', $pg_array, $match))
                    break;

                $array[] = $match[1];
                $pg_array = substr($pg_array, strlen($match[0])+1);
            } else {
                $pos = strpos($pg_array, ',');
                if ($pos === false) {
                    $val = $pg_array;
                    $pg_array = '';
                } else {
                    $val = substr($pg_array, 0, $pos);
                    $pg_array = substr($pg_array, $pos+1);
                }

                if ($val === 'NULL')
                    $val = NULL;

                $array[] = $val;
            }
        } while($pg_array);

        foreach ($array as $key => $val) {
            if ($val !== NULL) {
                $search = array('\"', '\\\\');
                $replace = array('"', '\\');
                $array[$key] = str_replace($search, $replace , $val);
            }
        }

        return $array;
    }

    // php array -> postgresql array
    static public function encodeArray($array) {
        if (!$array)
            return NULL;

        if (!is_array($array))
            return $array;

        // 过滤掉会导致解析或保存失败的异常字符
        foreach ($array as $key => $val) {
            if ($val === NULL) {
                $val = 'NULL';
            } else {
                $val = rtrim($val, '\\');       // 以\结尾的字符串，在decode时会导致正则表达式无法解析

                $search = array('\\', "'", '"');
                $replace = array('\\\\', "''", '\"');
                $val = '"'.str_replace($search, $replace, $val).'"';
            }

            $array[$key] = $val;
        }

        return new Expr(sprintf("'{%s}'", implode(',', $array)));
    }

    // postgresql hstore -> php array
    static public function decodeHstore($hstore) {
        if (!$hstore || !preg_match_all('/"(.+)(?<!\\\)"=>(NULL|""|".+(?<!\\\)"),?/U', $hstore, $match, PREG_SET_ORDER))
            return array();

        $array = array();

        foreach ($match as $set) {
            list(, $k, $v) = $set;

            $v = $v === 'NULL'
               ? NULL
               : substr($v, 1, -1);

            $search = array('\"', '\\\\');
            $replace = array('"', '\\');

            $k = str_replace($search, $replace, $k);
            if ($v !== NULL)
                $v = str_replace($search, $replace, $v);

            $array[$k] = $v;
        }

        return $array;
    }

    // php array -> postgresql hstore
    static public function encodeHstore($array) {
        if (!$array)
            return NULL;

        if (!is_array($array))
            return $array;

        $expr = array();

        foreach ($array as $key => $val) {
            $search = array('\\', "'", '"');
            $replace = array('\\\\', "''", '\"');

            $key = str_replace($search, $replace, $key);

            if ($val === NULL) {
                $val = 'NULL';
            } else {
                $val = rtrim($val, '\\');       // 以\结尾的字符串，无法用正则表达式解析
                $val = '"'.str_replace($search, $replace, $val).'"';
            }

            $expr[] = sprintf('"%s"=>%s', $key, $val);
        }

        return new Expr(sprintf("'%s'::hstore", implode(',', $expr)));
    }
}
