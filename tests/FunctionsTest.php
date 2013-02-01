<?php
namespace Test;

class FunctionsTest extends \PHPUnit_Framework_TestCase {
    public function testUrl() {
        $test = array(
            '/?a=1&b=2' => array('/', array('a' => 1, 'b' => 2)),
            '/?a=1&b=2' => array('/?c=3', array('a' => 1, 'b' => 2, 'c' => false)),
            '/?a=1&b=2&c=4' => array('/', array('a' => 1, 'b' => 2, 'c' => 3), array('c' => 4)),
            '/?a=1&b=0' => array('/', array('a' => 1, 'b' => 0)),
            '/?a=1&b=' => array('/', array('a' => 1, 'b' => '')),
            '/?a=1' => array('/', array('a' => 1, 'b' => null)),
            '?a=1&b=2' => array('?', array('a' => 1, 'b' => 2)),
            '?a=1&b=2' => array('', array('a' => 1, 'b' => 2)),
        );

        foreach ($test as $expected => $args)
            $this->assertEquals($expected, call_user_func_array('\Lysine\url', $args));
    }
}
