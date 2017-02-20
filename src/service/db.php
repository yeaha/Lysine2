<?php

namespace Lysine\Service\DB;

use Lysine\Service;

abstract class Adapter implements \Lysine\Service\IService
{
    protected $config;
    protected $handler;
    protected $identifier_symbol = '`';

    protected $support_savepoint = true;
    protected $savepoints = [];
    protected $in_transaction = false;

    abstract public function lastId($table = null, $column = null);

    /**
     * @param array [$config]
     */
    public function __construct(array $config = [])
    {
        $this->config = static::prepareConfig($config);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function __sleep()
    {
        $this->disconnect();

        return ['config'];
    }

    /**
     * @magic
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($method, array $args)
    {
        return $args
             ? call_user_func_array([$this->connect(), $method], $args)
             : $this->connect()->$method();
    }

    /**
     * 检查是否连接到了数据库.
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->handler instanceof \PDO;
    }

    /**
     * 创建数据库连接，如果已经创建就直接返回创建好的连接.
     *
     * @return \PDO
     *
     * @throws \Lysine\Service\ConnectionError 数据库连接失败
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return $this->handler;
        }

        list($dsn, $user, $password, $options) = $this->config;
        $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        $options[\PDO::ATTR_STATEMENT_CLASS] = ['\Lysine\Service\DB\Statement'];

        try {
            $handler = new \PDO($dsn, $user, $password, $options);
        } catch (\PDOException $ex) {
            throw new Service\ConnectionException('Database connect failed!', 0, $ex, [
                'dsn' => $dsn,
            ]);
        }

        return $this->handler = $handler;
    }

    /**
     * 断开数据库连接.
     *
     * @return $this
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            $max = 9;   // 最多9次，避免死循环
            while ($this->in_transaction && $max-- > 0) {
                $this->rollback();
            }

            $this->handler = null;
        }

        return $this;
    }

    /**
     * 开始事务
     *
     * @return bool
     */
    public function begin()
    {
        if ($this->in_transaction) {
            if (!$this->support_savepoint) {
                throw new \LogicException(get_class() . ' unsupport savepoint');
            }

            $savepoint = $this->quoteIdentifier(uniqid('savepoint_'));
            $this->execute('SAVEPOINT ' . $savepoint);
            $this->savepoints[] = $savepoint;
        } else {
            $this->execute('BEGIN');
            $this->in_transaction = true;
        }

        return true;
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public function commit()
    {
        if ($this->in_transaction) {
            if ($this->savepoints) {
                $savepoint = array_pop($this->savepoints);
                $this->execute('RELEASE SAVEPOINT ' . $savepoint);
            } else {
                $this->execute('COMMIT');
                $this->in_transaction = false;
            }
        }

        return true;
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public function rollback()
    {
        if ($this->in_transaction) {
            if ($this->savepoints) {
                $savepoint = array_pop($this->savepoints);
                $this->execute('ROLLBACK TO SAVEPOINT ' . $savepoint);
            } else {
                $this->execute('ROLLBACK');
                $this->in_transaction = false;
            }
        }

        return true;
    }

    /**
     * 是否处于事务中.
     *
     * @return bool
     */
    public function inTransaction()
    {
        return $this->in_transaction;
    }

    /**
     * 执行sql语句.
     *
     * @param string $sql
     * @param mixed... [$params]
     *
     * @return \Lysine\Service\DB\Statement
     *
     * @example
     * $db->execute('select * from foobar');
     * $db->execute('select * from foobar where foo = ? and bar = ?', $foo, $bar);
     * $db->execute('select * from foobar where foo = ? and bar = ?', array($foo, $bar));
     */
    public function execute($sql, $params = null)
    {
        $params = $params === null
                ? []
                : is_array($params) ? $params : array_slice(func_get_args(), 1);

        try {
            $sth = $sql instanceof \PDOStatement
                 ? $sql
                 : $this->connect()->prepare($sql);
            $sth->execute($params);
        } catch (\PDOException $ex) {
            throw new \Lysine\Exception($ex->getMessage(), $ex->errorInfo[1], $ex, [
                'sql' => (string) $sql,
                'params' => $params,
                'native_code' => $ex->errorInfo[0],
            ]);
        }

        $log = 'SQL: ' . $sql;
        if ($params) {
            $log .= ' [' . implode(',', $params) . ']';
        }
        \Lysine\logger()->debug($log);

        $sth->setFetchMode(\PDO::FETCH_ASSOC);

        return $sth;
    }

    /**
     * 对数据的异常字符串进行“引用”处理.
     *
     * @param mixed $val
     *
     * @return mixed
     */
    public function quote($val)
    {
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->quote($v);
            }

            return $val;
        }

        if ($val instanceof Expr) {
            return $val;
        }

        if (is_numeric($val)) {
            return $val;
        }

        if ($val === null) {
            return 'NULL';
        }

        return $this->connect()->quote($val);
    }

    /**
     * 对数据库字段名、表名进行“引用”处理，逃逸敏感字符.
     *
     * @param string $identifier
     *
     * @return \Lysine\Service\DB\Expr
     */
    public function quoteIdentifier($identifier)
    {
        if (is_array($identifier)) {
            return array_map([$this, 'quoteIdentifier'], $identifier);
        }

        if ($identifier instanceof Expr) {
            return $identifier;
        }

        $symbol = $this->identifier_symbol;
        $identifier = str_replace(['"', "'", ';', $symbol], '', $identifier);

        $result = [];
        foreach (explode('.', $identifier) as $s) {
            $result[] = $symbol . $s . $symbol;
        }

        return new Expr(implode('.', $result));
    }

    /**
     * 返回指定数据表的查询对象
     *
     * @param string|Expr $table
     * @param \Lysine\Service\DB\Select
     */
    public function select($table)
    {
        return new \Lysine\Service\DB\Select($this, $table);
    }

    /**
     * 插入一条数据到表.
     *
     * @param string $table
     * @param array  $row
     *
     * @return int affected row count
     */
    public function insert($table, array $row)
    {
        $params = [];
        foreach ($row as $val) {
            if (!($val instanceof Expr)) {
                $params[] = $val;
            }
        }

        $sth = $this->prepareInsert($table, $row);

        return $this->execute($sth, $params)->rowCount();
    }

    /**
     * 更新表内的数据，可以指定条件.
     *
     * @param string $table
     * @param array  $row
     * @param string [$where]
     * @param mixed [$params]
     *
     * @return int affected row count
     *
     * @example
     * $db->update('foobar', array('foo' => 1));
     * $db->update('foobar', array('foo' => 1), 'bar = ? and baz = ?', 2, 3);
     * $db->update('foobar', array('foo' => 1), 'bar = ? and baz = ?', array(2, 3));
     */
    public function update($table, array $row, $where = null, $params = null)
    {
        $where_params = ($where === null || $params === null)
                      ? []
                      : is_array($params) ? $params : array_slice(func_get_args(), 3);

        $params = [];
        foreach ($row as $val) {
            if (!($val instanceof Expr)) {
                $params[] = $val;
            }
        }
        if ($where_params) {
            $params = array_merge($params, $where_params);
        }

        $sth = $this->prepareUpdate($table, $row, $where);

        return $this->execute($sth, $params)->rowCount();
    }

    /**
     * 删除表内的数据，允许指定条件.
     *
     * @param string $table
     * @param string [$where]
     * @param mixed [$params]
     *
     * @return int affected row count
     *
     * @example
     * $db->delete('foobar');
     * $db->delete('foobar', 'foo = ? and bar = ?', 1, 2);
     * $db->delete('foobar', 'foo = ? and bar = ?', array(1, 2));
     */
    public function delete($table, $where = null, $params = null)
    {
        $params = ($where === null || $params === null)
                ? []
                : is_array($params) ? $params : array_slice(func_get_args(), 2);

        $sth = $this->prepareDelete($table, $where);

        return $this->execute($sth, $params)->rowCount();
    }

    /**
     * 返回一条insert语句的prepare结果.
     *
     * @param string $table
     * @param array  $cols  字段
     *
     * @return \Lysine\Service\DB\Statement
     *
     * @example
     * $sth = $db->prepareInsert('foobar', array('foo', 'bar'));
     * // or
     * $sth = $db->prepareInsert('foobar', array('foo' => 1, 'bar' => 2));
     *
     * // insert foobar (foo, bar) values (1, 2)
     *
     * $db->execute($sth, 1, 2);
     * // or
     * $db->execute($sth, array(1, 2));
     * // or
     * $sth->execute(array(1, 2));
     */
    public function prepareInsert($table, array $cols)
    {
        $vals = array_values($cols);

        if ($vals === $cols) {
            $vals = array_fill(0, count($cols), '?');
        } else {
            $cols = array_keys($cols);
            foreach ($vals as $key => $val) {
                if ($val instanceof Expr) {
                    continue;
                }
                $vals[$key] = '?';
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(',', $this->quoteIdentifier($cols)),
            implode(',', $vals)
        );

        return $this->prepare($sql);
    }

    /**
     * 返回一条update语句的prepare结果.
     *
     * @param string $table
     * @param array  $cols
     * @param string [$where]
     *
     * @return \Lysine\Service\DB\Statement
     *
     * @example
     * $sth = $db->prepareUpdate('foobar', array('foo', 'bar'), 'foo = ?');
     *
     * // update foobar set foo = 1, bar = 2 where foo = 3
     *
     * $db->execute($sth, array(1, 2, 3));
     * // or
     * $sth->execute(array(1, 2, 3));
     */
    public function prepareUpdate($table, array $cols, $where = null)
    {
        $only_col = (array_values($cols) === $cols);

        $set = [];
        foreach ($cols as $col => $val) {
            if ($only_col) {
                $set[] = $this->quoteIdentifier($val) . ' = ?';
            } else {
                $val = ($val instanceof Expr) ? $val : '?';
                $set[] = $this->quoteIdentifier($col) . ' = ' . $val;
            }
        }

        $sql = sprintf('UPDATE %s SET %s', $this->quoteIdentifier($table), implode(',', $set));
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $this->prepare($sql);
    }

    /**
     * 返回一条delete语句的prepare结果.
     *
     * @param string $table
     * @param string [$where]
     *
     * @return \Lysine\Service\DB\Statement
     *
     * @example
     * $sth = $db->prepareDelete('foobar', 'foo = ?');
     *
     * // delete foobar where foo = 1
     *
     * $db->execute($sth, 1);
     * // or
     * $sth->execute(array(1));
     */
    public function prepareDelete($table, $where = null)
    {
        $table = $this->quoteIdentifier($table);

        $sql = sprintf('DELETE FROM %s', $table);
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        return $this->prepare($sql);
    }

    /**
     * 格式化配置信息.
     *
     * @param array $config
     *
     * @return array
     *
     * @throws \InvalidArgumentException "dsn"配置不存在时
     */
    protected static function prepareConfig(array $config)
    {
        if (!isset($config['dsn'])) {
            throw new \InvalidArgumentException('Invalid database config, need "dsn" key');
        }

        return [
            $config['dsn'],
            isset($config['user']) ? $config['user'] : null,
            isset($config['password']) ? $config['password'] : null,
            isset($config['options']) ? $config['options'] : [],
        ];
    }
}

/**
 * sql表达式封装
 * 被包装为Expr的表达式字符串内容不会被当作异常字符处理
 * 不正确的使用方法可能导致sql注入漏洞等
 * 比如，直接把客户端提交的数据不经过任何处理就包装为Expr.
 */
class Expr
{
    private $expr;

    public function __construct($expr)
    {
        $this->expr = (string) $expr;
    }

    public function __toString()
    {
        return $this->expr;
    }
}

/**
 * sql语句执行结果.
 *
 * @see \PDOStatement
 */
class Statement extends \PDOStatement
{
    /**
     * 返回用于执行的sql语句.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->queryString;
    }

    /**
     * 从查询结果提取下一行.
     *
     * @return array
     */
    public function getRow()
    {
        return $this->fetch();
    }

    /**
     * 从下一行行中获取指定列的数据.
     *
     * @param int $col_number 列序号
     *
     * @return mixed
     */
    public function getCol($col_number = 0)
    {
        return $this->fetch(\PDO::FETCH_COLUMN, $col_number);
    }

    /**
     * 获取查询结果内指定列的所有结果.
     *
     * @param int $col_number 列序号
     *
     * @return array
     */
    public function getCols($col_number = 0)
    {
        return $this->fetchAll(\PDO::FETCH_COLUMN, $col_number);
    }

    /**
     * 返回所有的查询结果，允许以指定的字段内容为返回数组的key.
     *
     * @param string $col
     *
     * @return array
     */
    public function getAll($col = null)
    {
        if (!$col) {
            return $this->fetchAll();
        }

        $rowset = array();
        while ($row = $this->fetch()) {
            $rowset[ $row[$col] ] = $row;
        }

        return $rowset;
    }
}

/**
 * 数据库查询对象
 * 这个类主要是做字符串处理.
 */
class Select
{
    /**
     * 数据库连接.
     *
     * @var
     */
    protected $adapter;

    /**
     * 被查询的表或关系.
     *
     * @var
     */
    protected $table;

    /**
     * 查询条件表达式.
     *
     * @var
     */
    protected $where = [];

    /**
     * 查询结果字段.
     *
     * @var array
     */
    protected $cols = [];

    /**
     * group by 语句.
     *
     * @var array
     */
    protected $group_by;

    /**
     * order by 语句.
     *
     * @var array
     */
    protected $order_by;

    /**
     * limit 语句参数.
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * offset 语句参数.
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * 预处理函数
     * 每条返回的结果都会被预处理函数处理一次
     *
     * @see Select::get()
     *
     * @var callable
     */
    protected $processor;

    /**
     * @param \Lysine\Service\DB\Adapter $adapter
     * @param string|Expr|Select         $table
     */
    public function __construct(Adapter $adapter, $table)
    {
        $this->adapter = $adapter;
        $this->table = $table;
    }

    public function __destruct()
    {
        $this->adapter = null;
    }

    /**
     * 返回select语句.
     *
     * @return string
     */
    public function __toString()
    {
        list($sql) = $this->compile();

        return $sql;
    }

    /**
     * 获取数据库连接.
     *
     * @return \Lysine\Service\DB\Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * 设置查询的字段.
     *
     * @param string|array $cols
     *
     * @return $this
     *
     * @example
     * $select->setCols('foo', 'bar');
     * $select->setCols(array('foo', 'bar'));
     * $select->setCols('foo', 'bar', new DB\Expr('foo + bar'));
     */
    public function setCols($cols)
    {
        $this->cols = is_array($cols) ? $cols : func_get_args();

        return $this;
    }

    /**
     * 设置查询条件
     * 通过where()方法设置的多条条件之间的关系都是AND
     * OR关系必须写到同一个where条件内.
     *
     * @param string $where
     * @param mixed... [$params]
     *
     * @return $this
     *
     * @example
     * $select->where('foo = ?', 1)->where('bar = ?', 2);
     * $select->where('foo = ? or bar = ?', 1, 2);
     */
    public function where($where, $params = null)
    {
        $params = $params === null
                ? []
                : is_array($params) ? $params : array_slice(func_get_args(), 1);

        $this->where[] = [$where, $params];

        return $this;
    }

    /**
     * in 子查询.
     *
     * @param string       $col
     * @param array|Select $relation
     *
     * @return $this
     *
     * @example
     * // select * from foobar where foo in (1, 2, 3)
     * $select->whereIn('foo', array(1, 2, 3));
     *
     * // select * from foo where id in (select foo_id from bar where bar > 1)
     * $foo_select = $db->select('foo');
     * $bar_select = $db->select('bar');
     *
     * $foo_select->whereIn('id', $bar_select->setCols('foo_id')->where('bar > 1'));
     */
    public function whereIn($col, $relation)
    {
        return $this->whereSub($col, $relation, true);
    }

    /**
     * not in 子查询.
     *
     * @param string       $col
     * @param array|Select $relation
     *
     * @return $this
     */
    public function whereNotIn($col, $relation)
    {
        return $this->whereSub($col, $relation, false);
    }

    /**
     * group by 条件.
     *
     * @param array $cols
     * @param string [$having]
     * @param mixed... [$having_params]
     *
     * @return $this
     *
     * @example
     * // select foo, count(1) from foobar group by foo having count(1) > 2
     * $select->setCols('foo', new Expr('count(1) as count'))->group('foo', 'count(1) > ?', 2);
     */
    public function group($cols, $having = null, $having_params = null)
    {
        $having_params = ($having === null || $having_params === null)
                       ? []
                       : is_array($having_params) ? $having_params : array_slice(func_get_args(), 2);

        $this->group_by = [$cols, $having, $having_params];

        return $this;
    }

    /**
     * order by 语句.
     *
     * @param string|Expr
     *
     * @return $this
     *
     * @example
     * $select->order('foo');
     * $select->order(new Expr('foo desc'));
     */
    public function order($cols)
    {
        $this->order_by = $cols;

        return $this;
    }

    /**
     * limit语句.
     *
     * @param int $count
     *
     * @return $this
     */
    public function limit($count)
    {
        $this->limit = abs((int) $count);

        return $this;
    }

    /**
     * offset语句.
     *
     * @param int $count
     * @param $this
     */
    public function offset($count)
    {
        $this->offset = abs((int) $count);

        return $this;
    }

    /**
     * 执行查询，返回查询结果句柄对象
     *
     * @return \Lysine\Service\DB\Statement
     */
    public function execute()
    {
        list($sql, $params) = $this->compile();

        return $this->adapter->execute($sql, $params);
    }

    /**
     * 根据当前查询对象的各项参数，编译为具体的select语句及查询参数.
     *
     * @return
     * array(
     *     (string),    // select语句
     *     (array)      // 查询参数值
     * )
     */
    public function compile()
    {
        $adapter = $this->adapter;
        $sql = 'SELECT ';
        $params = [];

        $sql .= $this->cols
              ? implode(', ', $adapter->quoteIdentifier($this->cols))
              : '*';

        list($table, $table_params) = $this->compileFrom();
        if ($table_params) {
            $params = array_merge($params, $table_params);
        }

        $sql .= ' FROM ' . $table;

        list($where, $where_params) = $this->compileWhere();
        if ($where) {
            $sql .= ' WHERE ' . $where;
        }
        if ($where_params) {
            $params = array_merge($params, $where_params);
        }

        list($group_by, $group_params) = $this->compileGroupBy();
        if ($group_by) {
            $sql .= ' ' . $group_by;
        }
        if ($group_params) {
            $params = array_merge($params, $group_params);
        }

        if ($this->order_by) {
            $sql .= ' ORDER BY ' . $this->order_by;
        }

        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, $params];
    }

    /**
     * 查询当前查询条件在表内的行数.
     *
     * @return int
     */
    public function count()
    {
        $cols = $this->cols;
        $this->cols = [new Expr('count(1)')];

        $count = $this->execute()->getCol();

        $this->cols = $cols;

        return $count;
    }

    /**
     * 分页，把查询结果限定在指定的页.
     *
     * @param int $page
     * @param int $size
     *
     * @return $this
     *
     * @example
     * $select->setPage(2, 10)->get();
     */
    public function setPage($page, $size)
    {
        $this->limit($size)->offset(($page - 1) * $size);

        return $this;
    }

    /**
     * 分页，直接返回指定页的结果.
     *
     * @param int $page
     * @param int $size
     *
     * @return array
     *
     * @example
     * $select->getPage(2, 10);
     */
    public function getPage($page, $size)
    {
        return $this->setPage($page, $size)->get();
    }

    /**
     * 查询数据库数量，计算分页信息.
     *
     * @param int $current 当前页
     * @param int $size    每页多少条
     * @param int [$total]  一共有多少条，不指定就到数据库内查询
     *
     * @return
     * array(
     *  'total' => (integer),       // 一共有多少条数据
     *  'size' => (integer),        // 每页多少条
     *  'from' => (integer),        // 本页开始的序号
     *  'to' => (integer),          // 本页结束的序号
     *  'first' => 1,               // 第一页
     *  'prev' => (integer|null),   // 上一页，null说明没有上一页
     *  'current' => (integer),     // 本页页码
     *  'next' => (integer|null),   // 下一页，null说明没有下一页
     *  'last' => (integer),        // 最后一页
     * )
     */
    public function getPageInfo($current, $size, $total = null)
    {
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

    /**
     * 设置预处理函数.
     *
     * @param callable $processor
     *
     * @return $this
     */
    public function setProcessor($processor)
    {
        if ($processor && !is_callable($processor)) {
            throw new \UnexpectedValueException('Select processor is not callable');
        }

        $this->processor = $processor;

        return $this;
    }

    /**
     * 用预处理函数处理查询到的行.
     *
     * @param array $row
     *
     * @return mixed
     */
    public function process(array $row)
    {
        return $this->processor
             ? call_user_func($this->processor, $row)
             : $row;
    }

    /**
     * 获得所有的查询结果.
     *
     * @param int [$limit]
     *
     * @return array
     */
    public function get($limit = null)
    {
        if ($limit !== null) {
            $this->limit($limit);
        }

        $sth = $this->execute();
        $processor = $this->processor;

        $records = [];
        while ($record = $sth->getRow()) {
            $records[] = $processor
                       ? call_user_func($processor, $record)
                       : $record;
        }

        return $records;
    }

    /**
     * 只查询返回第一行数据.
     *
     * @return mixed
     */
    public function getOne()
    {
        $records = $this->get(1);

        return array_shift($records);
    }

    /**
     * 根据当前的条件，删除相应的数据.
     *
     * 注意：直接利用select删除数据可能不是你想要的结果
     * <code>
     * // 找出符合条件的前5条
     * // select * from "users" where id > 100 order by create_time desc limit 5
     * $select = $adapter->select('users')->where('id > ?', 100)->order('create_time desc')->limit(5);
     *
     * // 因为DELETE语句不支持order by / limit / offset
     * // 删除符合条件的，不仅仅是前5条
     * // delete from "users" where id > 100
     * $select->delete()
     *
     * // 如果要删除符合条件的前5条
     * // delete from "users" where id in (select id from "users" where id > 100 order by create_time desc limit 5)
     * $adapter->select('users')->whereIn('id', $select->setCols('id'))->delete();
     * </code>
     * 这里很容易犯错，考虑是否不提供delete()和update()方法
     * 或者发现定义了limit / offset就抛出异常中止
     *
     * @return int affected row count
     */
    public function delete()
    {
        list($where, $params) = $this->compileWhere();

        // 在这里，不允许没有任何条件的delete
        if (!$where) {
            throw new \LogicException('MUST specify WHERE condition before delete');
        }

        // 见方法注释
        if ($this->limit or $this->offset or $this->group_by) {
            throw new \LogicException('CAN NOT DELETE while specify LIMIT or OFFSET or GROUP BY');
        }

        return $this->adapter->delete($this->table, $where, $params);
    }

    /**
     * 根据当前查询语句的条件参数更新数据.
     *
     * @param array $row
     *
     * @return int affected row count
     */
    public function update(array $row)
    {
        list($where, $params) = $this->compileWhere();

        // 在这里，不允许没有任何条件的update
        if (!$where) {
            throw new \LogicException('MUST specify WHERE condition before update');
        }

        // 见delete()方法注释
        if ($this->limit or $this->offset or $this->group_by) {
            throw new \LogicException('CAN NOT UPDATE while specify LIMIT or OFFSET or GROUP BY');
        }

        return $this->adapter->update($this->table, $row, $where, $params);
    }

    /**
     * 以iterator的形式返回查询结果
     * 通过遍历iterator的方式处理查询结果，避免过大的内存占用.
     *
     * @return \Lysine\Service\DB\SelectIterator
     */
    public function iterator()
    {
        $res = $this->execute();

        while ($row = $res->fetch()) {
            yield $this->process($row);
        }
    }

    //////////////////// protected method ////////////////////

    /**
     * where in 子查询语句.
     *
     * @param string       $col
     * @param array|Select $relation
     * @param bool         $in
     *
     * @return $this
     */
    protected function whereSub($col, $relation, $in)
    {
        $col = $this->adapter->quoteIdentifier($col);
        $params = [];

        if ($relation instanceof self) {
            list($sql, $params) = $relation->compile();
            $sub = $sql;
        } else {
            $sub = implode(',', $this->adapter->quote($relation));
        }

        $where = $in
               ? sprintf('%s IN (%s)', $col, $sub)
               : sprintf('%s NOT IN (%s)', $col, $sub);

        $this->where[] = [$where, $params];

        return $this;
    }

    /**
     * 把from参数编译为select 子句.
     *
     * @return
     * array(
     *     (string),    // from 子句
     *     (array),     // 查询参数
     * )
     */
    protected function compileFrom()
    {
        $params = [];

        if ($this->table instanceof self) {
            list($sql, $params) = $this->table->compile();
            $table = sprintf('(%s) AS %s', $sql, $this->adapter->quoteIdentifier(uniqid()));
        } elseif ($this->table instanceof Expr) {
            $table = (string) $this->table;
        } else {
            $table = $this->adapter->quoteIdentifier($this->table);
        }

        return [$table, $params];
    }

    /**
     * 把查询条件参数编译为where子句.
     *
     * @return
     * array(
     *     (string),    // where 子句
     *     (array),     // 查询参数
     * )
     */
    protected function compileWhere()
    {
        if (!$this->where) {
            return ['', []];
        }

        $where = $params = [];

        foreach ($this->where as $w) {
            list($where_sql, $where_params) = $w;
            $where[] = $where_sql;
            if ($where_params) {
                $params = array_merge($params, $where_params);
            }
        }
        $where = '(' . implode(') AND (', $where) . ')';

        return [$where, $params];
    }

    /**
     * 编译group by 子句.
     *
     * @return
     * array(
     *     (string),    // group by 子句
     *     (array),     // 查询参数
     * )
     */
    protected function compileGroupBy()
    {
        if (!$this->group_by) {
            return ['', []];
        }

        list($group_cols, $having, $having_params) = $this->group_by;

        $group_cols = $this->adapter->quoteIdentifier($group_cols);
        if (is_array($group_cols)) {
            $group_cols = implode(',', $group_cols);
        }

        $sql = 'GROUP BY ' . $group_cols;
        if ($having) {
            $sql .= ' HAVING ' . $having;
        }

        return [$sql, $having_params];
    }
}
