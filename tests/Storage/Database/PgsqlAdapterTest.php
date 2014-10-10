<?php
namespace Test\Storage\Database;

use \Lysine\Service\DB\Adapter\Pgsql;

class PgsqlAdapterTest extends \PHPUnit_Framework_TestCase {
    protected $adapter;

    public function __construct() {
        $this->adapter = service('pgsql.local');
    }

    protected function setUp() {
        try {
            $this->adapter->connect();
        } catch (\Lysine\Service\ConnectionException $ex) {
            $this->markTestSkipped('Postgresql连接不上，无法测试Postgresql adapter');
        }
    }

    protected function tearDown() {
        if ($this->adapter->isConnected())
            $this->adapter->disconnect();
    }

    public function testCRUD() {
        $adapter = $this->adapter;

        $table = 'bug_tracker.accounts';
        $rows = array(
            array(
                'account_name' => 'zhangsan',
                'first_name' => 'zhang',
                'last_name' => 'san',
                'email' => 'zhangsan@example.com',
                'password_hash' => md5('zhang'). md5('san'),
                'hourly_rate' => 50.0,
            ),
            array(
                'account_name' => 'lisi',
                'first_name' => 'li',
                'last_name' => 'si',
                'email' => 'lisi@example.com',
                'password_hash' => md5('li'). md5('si'),
                'hourly_rate' => 50.55,
            )
        );

        foreach ($rows as $row)
            $affected = $adapter->insert($table, $row);

        $this->assertEquals(1, $affected);
        $this->assertInternalType('integer', $adapter->lastId($table, 'account_id'));
        $this->assertEquals($adapter->lastId($table, 'account_id'), $adapter->lastId());

        $res = $adapter->execute("select count(1) from {$table} where email = ? and account_name = ?", 'lisi@example.com', 'lisi');
        $this->assertInstanceof('\PDOStatement', $res);
        $this->assertEquals(1, $res->getCol());

        $affected = $adapter->update($table, array('password_hash' => md5('a').md5('b')), 'email = ?', 'lisi@example.com');
        $this->assertEquals(1, $affected);

        $affected = $adapter->delete($table);
        $this->assertEquals(2, $affected);
    }

    public function testPrepare() {
        $adapter = $this->adapter;

        $table = 'bug_tracker.accounts';
        $data = array(
                    'account_name' => 'zhangsan',
                    'first_name' => 'zhang',
                    'last_name' => 'san',
                    'email' => 'zhangsan@example.com',
                    'password_hash' => md5('zhang'). md5('san'),
                    'hourly_rate' => 50.0,
                    'create_time' => new \Lysine\Service\DB\Expr('now()'),
                );

        $except = 'INSERT INTO "bug_tracker"."accounts" ("account_name","first_name","last_name","email","password_hash","hourly_rate","create_time") VALUES (?,?,?,?,?,?,now())';
        $sth = $adapter->prepareInsert($table, $data);
        $this->assertInstanceof('\PDOStatement', $sth);
        $this->assertEquals($except, (string)$sth);

        $except = 'INSERT INTO "bug_tracker"."accounts" ("account_name","first_name","last_name","email","password_hash","hourly_rate","create_time") VALUES (?,?,?,?,?,?,?)';
        $sth = $adapter->prepareInsert($table, array_keys($data));
        $this->assertEquals($except, (string)$sth);

        $except = 'UPDATE "bug_tracker"."accounts" SET "account_name" = ?,"first_name" = ?,"last_name" = ?,"email" = ?,"password_hash" = ?,"hourly_rate" = ?,"create_time" = now() WHERE account_id = ?';
        $sth = $adapter->prepareUpdate($table, $data, 'account_id = ?');
        $this->assertInstanceof('\PDOStatement', $sth);
        $this->assertEquals($except, (string)$sth);

        $except = 'UPDATE "bug_tracker"."accounts" SET "account_name" = ?,"first_name" = ?,"last_name" = ?,"email" = ?,"password_hash" = ?,"hourly_rate" = ?,"create_time" = ? WHERE account_id = ?';
        $sth = $adapter->prepareUpdate($table, array_keys($data), 'account_id = ?');
        $this->assertEquals($except, (string)$sth);

        $except = 'DELETE FROM "bug_tracker"."accounts" WHERE account_id = ?';
        $sth = $adapter->prepareDelete($table, 'account_id = ?');
        $this->assertInstanceof('\PDOStatement', $sth);
        $this->assertEquals($except, (string)$sth);
    }

    public function testHstore() {
        $this->assertEquals('aaa', Pgsql::encodeHstore('aaa'));
        $this->assertNull(Pgsql::encodeHstore(''));
        $this->assertNull(Pgsql::encodeHstore(NULL));
        $this->assertNull(Pgsql::encodeHstore(array()));

        $this->assertSame(array(), Pgsql::decodeHstore(''));
        $this->assertSame(array(), Pgsql::decodeHstore(NULL));

        $data = array(
            'a' => NULL,
            'b' => 'b',
            'c' => 'a"b',
            'd' => 'a\'b',
            'e' => 'a\\b',
            'f' => 123,
            'g' => '',
            'h' => 'a"b\'c\\d',
            'i' => 'a=>b',
            'j' => '"a"=>"b", ',
            'k=>' => 'ab',
            '"l=>' => 'a=>b',
            'm' => 'a,b',
            'n' => 0,
        );

        $expr = Pgsql::encodeHstore($data);
        $this->assertInstanceof('\Lysine\Service\DB\Expr', $expr);

        $adapter = $this->adapter;
        $hstore = $adapter->execute('select '. $expr)->getCol();
        $this->assertInternalType('string', $hstore);

        $decoded = Pgsql::decodeHstore($hstore);

        foreach ($decoded as $key => $val) {
            $this->assertArrayHasKey($key, $data);

            // 恢复回来的数据除NULL外，全部都是字符串类型
            $expect = ($data[$key] === NULL) ? NULL : (string)$data[$key];
            $this->assertSame($expect, $decoded[$key]);
        }
    }

    public function testPgArray() {
        $this->assertNull(Pgsql::encodeArray(NULL));
        $this->assertNull(Pgsql::encodeArray(array()));

        $this->assertSame(array(), Pgsql::decodeArray(''));
        $this->assertSame(array(), Pgsql::decodeArray(NULL));

        $data_set = array(
            array('1', '2', '3'),
            array('a', 'b', 'c'),
            array('a\'', 'b,', 'c"', 'd\\d', 'e'),
            array('', NULL, 'a'),
            array('1', NULL, 'c"'),
        );

        $adapter = $this->adapter;
        foreach ($data_set as $data) {
            $expr = Pgsql::encodeArray($data);
            $this->assertInstanceof('\Lysine\Service\DB\Expr', $expr);

            $pg_array = $adapter->execute("select ({$expr})::varchar[]")->getCol();
            $this->assertInternalType('string', $pg_array);

            $decoded = Pgsql::decodeArray($pg_array);
            $this->assertInternalType('array', $decoded);
            $this->assertSame($data, $decoded);
        }
    }
}
