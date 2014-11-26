<?php
namespace Test\Storage\Database;

use \Lysine\Service\DB\Adapter\Pgsql;
use \Lysine\Service\DB\Adapter\Mysql;

class DBTest extends \PHPUnit_Framework_TestCase {
    public function testMysqlQuoteIdentifier() {
        $db = new Mysql(array(
            'dsn' => 'mysql:host=127.0.0.1;dbname=foobar'
        ));

        $this->assertInstanceof('\Lysine\Service\DB\Expr', $db->quoteIdentifier('foobar'));

        $tests = array(
            'foobar' => '`foobar`',
            'foo.bar' => '`foo`.`bar`',
        );

        foreach ($tests as $identifier => $expect) {
            $this->assertEquals($expect, $db->quoteIdentifier($identifier));
        }

        $values = array_values($tests);
        foreach ($db->quoteIdentifier(array_keys($tests)) as $i => $expr) {
            $this->assertEquals($values[$i], $expr);
        }
    }

    public function testPgsqlQuoteIdentifier() {
        $db = new Pgsql(array(
            'dsn' => 'pgsql:host=127.0.0.1;dbname=foobar'
        ));

        $tests = array(
            'foobar' => '"foobar"',
            'foo.bar' => '"foo"."bar"',
        );

        foreach ($tests as $identifier => $expect) {
            $this->assertEquals($expect, $db->quoteIdentifier($identifier));
        }
    }
}
