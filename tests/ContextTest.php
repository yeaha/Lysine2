<?php
namespace Test;

class ContextTest extends \PHPUnit_Framework_TestCase {
    protected function createHandler($type, $config) {
        return \Lysine\ContextHandler::factory($type, $config);
    }

    public function testAbstractMethods() {
        $handlers = array(
            'session' => array('token' => 'test'),
            'cookie' => array('token' => 'test', 'salt' => 'fdjsaifowjfojweo'),
        );

        foreach ($handlers as $type => $config) {
            $handler = $this->createHandler($type, $config);
            $class = get_class($handler);

            $this->assertFalse($handler->has('test'), "{$class}->has()");

            $handler->set('test', 'abc');
            $this->assertEquals($handler->get('test'), 'abc', "{$class}->get() exists key");

            $handler->remove('test');
            $this->assertNull($handler->get('test'), "{$class}->get() not exists key");

            $handler->clear();
            $this->assertEquals($handler->get(), array(), "{$class}->get()");
        }
    }

    // 明文
    public function testCookieContext() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'salt' => 'fdajkfldsjfldsf');

        $handler = new \Lysine\CookieContextHandler($config);
        $handler->set('test', 'abc');

        $mock_cookie->apply();

        $other_handler = new \Lysine\CookieContextHandler($config);
        $this->assertEquals($other_handler->get('test'), 'abc');
    }

    // 明文压缩
    public function testZipCookieContext() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'salt' => 'fdajkfldsjfldsf', 'zip' => true);

        $handler = new \Lysine\CookieContextHandler($config);
        $handler->set('test', 'abc');

        $mock_cookie->apply();

        $other_handler = new \Lysine\CookieContextHandler($config);
        $this->assertEquals($other_handler->get('test'), 'abc');
    }

    // 加密
    public function testEncryptCookieContext() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'salt' => 'fdajkfldsjfldsf', 'encrypt' => array(MCRYPT_RIJNDAEL_256));

        $handler = new \Lysine\CookieContextHandler($config);
        $handler->set('test', 'abc');

        $mock_cookie->apply();

        $other_handler = new \Lysine\CookieContextHandler($config);
        $this->assertEquals($other_handler->get('test'), 'abc');
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

        $_SERVER['REMOTE_ADDR'] = '192.168.1.3';
        $other_handler = new \Lysine\CookieContextHandler($config);
        $this->assertEquals($other_handler->get('test'), 'abc', '同子网IP取值');

        $_SERVER['REMOTE_ADDR'] = '192.168.2.1';
        $other_handler = new \Lysine\CookieContextHandler($config);
        $this->assertNull($other_handler->get('test'), '不同子网IP取值');
    }
}
