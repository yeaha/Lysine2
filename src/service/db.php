<?php
namespace Lysine\Service\DB;

use Lysine\Service;

abstract class Adapter implements \Lysine\Service\IService {
    protected $config;
    protected $handler;
    protected $transaction_counter = 0;

    abstract public function qtab($table);
    abstract public function qcol($column);
    abstract public function lastId($table = null, $column = null);

    public function __construct(array $config = array()) {
        $this->config = static::prepareConfig($config);
    }

    public function __destruct() {
        $this->disconnect();
    }

    public function __sleep() {
        $this->disconnect();
    }

    public function __call($method, array $args) {
        return $args
             ? call_user_func_array(array($this->connect(), $method), $args)
             : $this->connect()->$method();
    }

    public function isConnected() {
        return $this->handler instanceof \PDO;
    }

    public function connect() {
        if ($this->isConnected())
            return $this->handler;

        list($dsn, $user, $password, $options) = $this->config;
        $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        $options[\PDO::ATTR_STATEMENT_CLASS] = array('\Lysine\Service\DB\Statement');

        try {
            $this->handler = new \PDO($dsn, $user, $password, $options);
        } catch (\PDOException $ex) {
            throw new Service\ConnectionError($ex->getMessage(), 0, $ex, array(
                'dsn' => $dsn,
            ));
        }

        return $this->handler;
    }

    public function disconnect() {
        if ($this->isConnected()) {
            while ($this->transaction_counter)
                $this->rollback();

            unset($this->handler);
        }

        return $this;
    }

    public function begin() {
        if ($result = $this->connect()->beginTransaction())
            $this->transaction_counter++;

        return $result;
    }

    public function commit() {
        if (!$this->transaction_counter)
            return false;

        if ($result = $this->connect()->commit())
            $this->transaction_counter--;

        return $result;
    }

    public function rollback() {
        if (!$this->transaction_counter)
            return false;

        if ($result = $this->connect()->rollback())
            $this->transaction_counter--;

        return $result;
    }

    public function inTransaction() {
        return (bool)$this->transaction_counter;
    }

    public function execute($sql, $params = null) {
        $params = $params === null
                ? array()
                : is_array($params) ? $params : array_slice(func_get_args(), 1);

        try {
            $sth = $sql instanceof \PDOStatement
                 ? $sql
                 : $this->connect()->prepare($sql);
            $sth->execute($params);
        } catch (\PDOException $ex) {
            throw new Service\RuntimeError($ex->getMessage(), $ex->errorInfo[1], $ex, array(
                'sql' => (string)$sql,
                'params' => $params,
                'native_code' => $ex->errorInfo[0],
            ));
        }

        $log = 'SQL: '. $sql;
        if ($params) $log .= ' ['. implode(',', $params) .']';
        \Lysine\logger()->debug($log);

        $sth->setFetchMode(\PDO::FETCH_ASSOC);
        return $sth;
    }

    public function qstr($val) {
        if (is_array($val)) {
            foreach ($val as $k => $v)
                $val[$k] = $this->qstr($v);
            return $val;
        }

        if ($val instanceof Expr)
            return $val;

        if (is_numeric($val))
            return $val;

        if ($val === null)
            return 'NULL';

        return $this->connect()->quote($val);
    }

    public function select($table) {
        return new \Lysine\Service\DB\Select($this, $table);
    }

    public function insert($table, array $row) {
        $params = array();
        foreach ($row as $val) {
            if ( !($val instanceof Expr) )
                $params[] = $val;
        }

        $sth = $this->prepareInsert($table, $row);
        return $this->execute($sth, $params)->rowCount();
    }

    public function update($table, array $row, $where = null, $params = null) {
        $where_params = ($where === null || $params === null)
                      ? array()
                      : is_array($params) ? $params : array_slice(func_get_args(), 3);

        $params = array();
        foreach ($row as $val) {
            if ( !($val instanceof Expr) )
                $params[] = $val;
        }
        if ($where_params) $params = array_merge($params, $where_params);

        $sth = $this->prepareUpdate($table, $row, $where);
        return $this->execute($sth, $params)->rowCount();
    }

    public function delete($table, $where = null, $params = null) {
        $params = ($where === null || $params === null)
                ? array()
                : is_array($params) ? $params : array_slice(func_get_args(), 2);

        $sth = $this->prepareDelete($table, $where);
        return $this->execute($sth, $params)->rowCount();
    }

    public function prepareInsert($table, array $cols) {
        $vals = array_values($cols);

        if ($vals === $cols) {
            $vals = array_fill(0, count($cols), '?');
        } else {
            $cols = array_keys($cols);
            foreach ($vals as $key => $val) {
                if ($val instanceof Expr) continue;
                $vals[$key] = '?';
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->qtab($table),
            implode(',', $this->qcol($cols)),
            implode(',', $vals)
        );

        return $this->prepare($sql);
    }

    public function prepareUpdate($table, array $cols, $where = null) {
        $only_col = (array_values($cols) === $cols);

        $set = array();
        foreach ($cols as $col => $val) {
            if ($only_col) {
                $set[] = $this->qcol($val) .' = ?';
            } else {
                $val = ($val instanceof Expr) ? $val : '?';
                $set[] = $this->qcol($col) .' = '. $val;
            }
        }

        $sql = sprintf('UPDATE %s SET %s', $this->qtab($table), implode(',', $set));
        if ($where) $sql .= ' WHERE '. $where;

        return $this->prepare($sql);
    }

    public function prepareDelete($table, $where = null) {
        $table = $this->qtab($table);

        $sql = sprintf('DELETE FROM %s', $table);
        if ($where)
            $sql .= ' WHERE '. $where;

        return $this->prepare($sql);
    }

    static protected function prepareConfig(array $config) {
        if (!isset($config['dsn']))
            throw new \InvalidArgumentException('Invalid database config, need "dsn" key');

        return array(
            $config['dsn'],
            isset($config['user']) ? $config['user'] : null,
            isset($config['password']) ? $config['password'] : null,
            isset($config['options']) ? $config['options'] : array(),
        );
    }
}

class Expr {
    private $expr;

    public function __construct($expr) {
        $this->expr = (string)$expr;
    }

    public function __toString() {
        return $this->expr;
    }
}

class Statement extends \PDOStatement {
    public function __toString() {
        return $this->queryString;
    }

    public function getRow() {
        return $this->fetch();
    }

    public function getCol($col_number = 0) {
        return $this->fetch(\PDO::FETCH_COLUMN, $col_number);
    }

    public function getCols($col_number = 0) {
        return $this->fetchAll(\PDO::FETCH_COLUMN, $col_number);
    }

    public function getAll($col = null) {
        if (!$col) return $this->fetchAll();

        $rowset = array();
        while ($row = $this->fetch())
            $rowset[ $row[$col] ] = $row;
        return $rowset;
    }
}

class Select {
    protected $adapter;
    protected $table;
    protected $where = array();
    protected $cols = array();
    protected $group_by;
    protected $order_by;
    protected $limit = 0;
    protected $offset = 0;
    protected $processor;

    public function __construct(Adapter $adapter, $table) {
        $this->adapter = $adapter;
        $this->table = $table;
    }

    public function __destruct() {
        $this->adapter = null;
    }

    public function __toString() {
        list($sql,) = $this->compile();
        return $sql;
    }

    public function setCols($cols) {
        $this->cols = is_array($cols) ? $cols : func_get_args();
        return $this;
    }

    public function where($where, $params = null) {
        $params = $params === null
                ? array()
                : is_array($params) ? $params : array_slice(func_get_args(), 1);

        $this->where[] = array($where, $params);
        return $this;
    }

    public function whereIn($col, $relation) {
        return $this->whereSub($col, $relation, true);
    }

    public function whereNotIn($col, $relation) {
        return $this->whereSub($col, $relation, false);
    }

    public function group($cols, $having = null, $having_params = null) {
        $having_params = ($having === null || $having_params === null)
                       ? array()
                       : is_array($having_params) ? $having_params : array_slice(func_get_args(), 2);

        $this->group_by = array($cols, $having, $having_params);
        return $this;
    }

    public function order($cols) {
        $this->order_by = $cols;
        return $this;
    }

    public function limit($count) {
        $this->limit = abs((int)$count);
        return $this;
    }

    public function offset($count) {
        $this->offset = abs((int)$count);
        return $this;
    }

    public function execute(array $params = null) {
        list($sql, $params) = $this->compile();
        return $this->adapter->execute($sql, $params);
    }

    public function compile() {
        $adapter = $this->adapter;
        $sql = 'SELECT ';
        $params = array();

        $sql .= $this->cols
              ? implode(', ', $adapter->qcol($this->cols))
              : '*';

        list($table, $table_params) = $this->compileFrom();
        if ($table_params) $params = array_merge($params, $table_params);

        $sql .= ' FROM '. $table;

        list($where, $where_params) = $this->compileWhere();
        if ($where)
            $sql .= ' WHERE '. $where;
        if ($where_params)
            $params = array_merge($params, $where_params);

        list($group_by, $group_params) = $this->compileGroupBy();
        if ($group_by)
            $sql .= ' '.$group_by;
        if ($group_params)
            $params = array_merge($params, $group_params);

        if ($this->order_by)
            $sql .= ' ORDER BY '. $this->order_by;

        if ($this->limit)
            $sql .= ' LIMIT '. $this->limit;

        if ($this->offset)
            $sql .= ' OFFSET '. $this->offset;

        return array($sql, $params);
    }

    public function count() {
        $cols = $this->cols;
        $this->cols = array(new Expr('count(1)'));

        $count = $this->execute()->getCol();

        $this->cols = $cols;
        return $count;
    }

    public function setPage($page, $size) {
        $this->limit($size)->offset( ($page - 1) * $size );
        return $this;
    }

    public function getPage($page, $size) {
        return $this->setPage($page, $size)->get();
    }

    public function getPageInfo($current, $size, $total = null) {
        if ($total === null) {
            $limit = $this->limit;
            $offset = $this->offset;
            $order = $this->order_by;

            $this->order(null)->limit(0)->offset(0);

            $total = $this->count();

            $this->order($order)->limit($limit)->offset($offset);
        }

        return \Lysine\cal_page($total, $size, $current);
    }

    public function setProcessor($processor) {
        if ($processor && !is_callable($processor))
            throw new \Lysine\UnexpectedValueError('Select processor is not callable');

        $this->processor = $processor;
        return $this;
    }

    public function process(array $row) {
        return $this->processor
             ? call_user_func($this->processor, $row)
             : $row;
    }

    public function get($limit = null) {
        if ($limit !== null)
            $this->limit($limit);

        $sth = $this->execute();
        $processor = $this->processor;

        $records = array();
        while ($record = $sth->getRow()) {
            $records[] = $processor
                       ? call_user_func($processor, $record)
                       : $record;
        }

        return $records;
    }

    public function getOne() {
        $records = $this->get(1);
        return array_shift($records);
    }

    // 注意：直接利用select删除数据可能不是你想要的结果
    // <code>
    // // 找出符合条件的前5条
    // // select * from "users" where id > 100 order by create_time desc limit 5
    // $select = $adapter->select('users')->where('id > ?', 100)->order('create_time desc')->limit(5);
    //
    // // 因为DELETE语句不支持order by / limit / offset
    // // 删除符合条件的，不仅仅是前5条
    // // delete from "users" where id > 100
    // $select->delete()
    //
    // // 如果要删除符合条件的前5条
    // // delete from "users" where id in (select id from "users" where id > 100 order by create_time desc limit 5)
    // $adapter->select('users')->whereIn('id', $select->setCols('id'))->delete();
    // </code>
    // 这里很容易犯错，考虑是否不提供delete()和update()方法
    // 或者发现定义了limit / offset就抛出异常中止
    public function delete() {
        list($where, $params) = $this->compileWhere();

        // 在这里，不允许没有任何条件的delete
        if (!$where)
            throw new \LogicException('MUST specify WHERE condition before delete');

        // 见方法注释
        if ($this->limit OR $this->offset OR $this->group_by)
            throw new \LogicException('CAN NOT DELETE while specify LIMIT or OFFSET or GROUP BY');

        return $this->adapter->delete($this->table, $where, $params);
    }

    public function update(array $row) {
        list($where, $params) = $this->compileWhere();

        // 在这里，不允许没有任何条件的update
        if (!$where)
            throw new \LogicException('MUST specify WHERE condition before update');

        // 见delete()方法注释
        if ($this->limit OR $this->offset OR $this->group_by)
            throw new \LogicException('CAN NOT DELETE while specify LIMIT or OFFSET or GROUP BY');

        return $this->adapter->update($this->table, $row, $where, $params);
    }

    public function iterator() {
        return new \NoRewindIterator(new SelectIterator($this));
    }

    //////////////////// protected method ////////////////////

    protected function whereSub($col, $relation, $in) {
        $col = $this->adapter->qcol($col);
        $params = array();

        if ($relation instanceof Select) {
            list($sql, $params) = $relation->compile();
            $sub = $sql;
        } else {
            $sub = implode(',', $this->adapter->qstr($relation));
        }

        $where = $in
               ? sprintf('%s IN (%s)', $col, $sub)
               : sprintf('%s NOT IN (%s)', $col, $sub);

        $this->where[] = array($where, $params);
        return $this;
    }

    protected function compileFrom() {
        $params = array();

        if ($this->table instanceof Select) {
            list($sql, $params) = $this->table->compile();
            $table = sprintf('(%s) AS %s', $sql, $this->adapter->qtab(uniqid()));
        } elseif ($this->table instanceof Expr) {
            $table = (string)$this->table;
        } else {
            $table = $this->adapter->qtab($this->table);
        }

        return array($table, $params);
    }

    protected function compileWhere() {
        if (!$this->where)
            return array('', array());

        $where = $params = array();

        foreach ($this->where as $w) {
            list($where_sql, $where_params) = $w;
            $where[] = $where_sql;
            if ($where_params)
                $params = array_merge($params, $where_params);
        }
        $where = '('. implode(') AND (', $where) .')';
        return array($where, $params);
    }

    protected function compileGroupBy() {
        if (!$this->group_by)
            return array('', array());

        list($group_cols, $having, $having_params) = $this->group_by;

        $group_cols = $this->adapter->qcol($group_cols);
        if (is_array($group_cols))
            $group_cols = implode(',', $group_cols);

        $sql = 'GROUP BY '. $group_cols;
        if ($having)
            $sql .= ' HAVING '. $having;

        return array($sql, $having_params);
    }
}

class SelectIterator implements \Iterator {
    private $sth;
    private $select;
    private $row_count;
    private $pos = 0;

    public function __construct(Select $select) {
        $this->sth = $select->execute();
        $this->row_count = $this->sth->rowCount();
        $this->select = $select;
    }

    public function current() {
        return $this->select->process( $this->sth->fetch() );
    }

    public function key() {
        return $this->pos;
    }

    public function next() {
        $this->pos++;
    }

    public function rewind() {
        $this->sth->closeCursor();
        $this->pos = 0;
    }

    public function valid() {
        return $this->pos < $this->row_count;
    }
}
