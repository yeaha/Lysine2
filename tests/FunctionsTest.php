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
            'http://www.example.com/foo?bar=1' => array('http://www.example.com/foo', array('bar' => 1)),
            'https://www.example.com/foo?bar=1' => array('https://www.example.com/foo', array('bar' => 1)),
            'https://www.example.com:8080/foo?bar=1' => array('https://www.example.com:8080/foo', array('bar' => 1)),
        );

        foreach ($test as $expected => $args)
            $this->assertEquals($expected, call_user_func_array('\Lysine\url', $args));
    }

    public function testBaseConvert() {
        $test = array(
            '0' => array(0, 10, 2),
            'a' => array(10, 10, 16),
            'ff' => array(255, 10, 16),
            '61' => array('Z', 62, 10),
            '110' => array(6, 10, 2),
            '101000110111001100110100' => array('a37334', 16, 2),
            '20' => array(8, 10, 4),
        );

        foreach ($test as $expected => $args)
            $this->assertEquals($expected, call_user_func_array('\Lysine\base_convert', $args));

        $test = false;
        try {
            \Lysine\base_convert(10, 10, 64);
        } catch (\ErrorException $ex) {
            $test = true;
            $this->assertEquals('Only support base between 2 and 62', $ex->getMessage());
        }
        $this->assertTrue($test);

        $test = false;
        try {
            \Lysine\base_convert('A37334', 16, 2);
        } catch (\ErrorException $ex) {
            $test = true;
            $this->assertEquals('Unexpected base character: A', $ex->getMessage());
        }
        $this->assertTrue($test);
    }
}
