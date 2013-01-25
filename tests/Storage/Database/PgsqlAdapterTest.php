<?php
namespace Test\Storage\Database;

class PgsqlAdapterTest extends \PHPUnit_Framework_TestCase {
    protected $adapter;

    public function __construct() {
        $this->adapter = service('pgsql.local');
    }

    protected function setUp() {
        try {
            $this->adapter->connect();
        } catch (\Lysine\Service\ConnectionError $ex) {
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
}
