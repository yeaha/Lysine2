<?php
namespace Test;

class ContextTest extends \PHPUnit_Framework_TestCase {
    protected function createHandler($type, $config) {
        return \Lysine\ContextHandler::factory($type, $config);
    }

    protected function setUp() {
        \Test\Mock\Environment::init('/', 'GET');
    }

    protected function tearDown() {
        \Test\Mock\Environment::reset();
    }

    public function testAbstractMethods() {
        $handlers = array(
            'session' => array('token' => 'test'),
            'cookie' => array('token' => 'test', 'salt' => 'fdjsaifowjfojweo'),
            'redis' => array('token' => 'test', 'ttl' => 300, 'service' => 'redis.local'),
        );

        foreach ($handlers as $type => $config) {
            $handler = $this->createHandler($type, $config);
            $handler->clear();

            $class = get_class($handler);

            $this->assertFalse($handler->has('test'), "{$class}->has()");

            $handler->set('test', 'abc');
            $this->assertEquals($handler->get('test'), 'abc', "{$class}->get() exists key");

            $handler->remove('test');
            $this->assertFalse($handler->has('test'), "{$class}->has() not exists key");

            $handler->clear();
            $this->assertEquals($handler->get(), array(), "{$class}->get()");
        }
    }

    public function testCookieContext() {
        $config_list = array(
            '明文' => array('token' => 'test', 'salt' => 'fdajkfldsjfldsf'),
            '明文+压缩' => array('token' => 'test', 'salt' => 'fdajkfldsjfldsf', 'zip' => true),
            '加密' => array('token' => 'test', 'salt' => 'fdajkfldsjfldsf', 'encrypt' => array(MCRYPT_RIJNDAEL_256)),
        );

        $mock_cookie = \Test\Mock\Cookie::getInstance();

        foreach ($config_list as $msg => $config) {
            $mock_cookie->reset();

            $handler = new \Lysine\CookieContextHandler($config);
            $handler->set('test', 'abc');

            $mock_cookie->apply();
            $handler->reset();

            $this->assertEquals($handler->get('test'), 'abc', $msg);
        }
    }

    // 数字签名
    public function testCookieContextSign() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'salt' => 'fdajkfldsjfldsf');
        $handler = new \Lysine\CookieContextHandler($config);

        $handler->set('test', 'abc');

        $mock_cookie->apply();
        $handler->reset();

        $_COOKIE['test'] = '0'. $_COOKIE['test'];
        $this->assertNull($handler->get('test'), '篡改cookie内容');

        $_COOKIE['test'] = substr($_COOKIE['test'], 1);
        $handler->reset();

        $handler->setConfig('salt', 'r431oj0if31jr3');
        $this->assertNull($handler->get('test'), 'salt没有起作用');
    }

    // 地址绑定
    public function testBindIpCookieContext() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'salt' => 'fdajkfldsjfldsf', 'bind_ip' => true);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $handler = new \Lysine\CookieContextHandler($config);
        $handler->set('test', 'abc');

        $mock_cookie->apply();

        $handler->reset();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.3';
        $this->assertEquals($handler->get('test'), 'abc', '同子网IP取值');

        $handler->reset();
        $_SERVER['REMOTE_ADDR'] = '192.168.2.1';
        $this->assertNull($handler->get('test'), '不同子网IP取值');
    }
}
