<?php
namespace Test;

class ContextTest extends \PHPUnit_Framework_TestCase {
    protected function createHandler($type, $config) {
        return \Lysine\ContextHandler::factory($type, $config);
    }

    protected function setUp() {
        \Test\Mock\Sandbox::getInstance()->request('/', 'GET');
    }

    protected function tearDown() {
        \Test\Mock\Sandbox::getInstance()->reset();
    }

    public function testAbstractMethods() {
        $handlers = array(
            'session' => array('token' => 'test'),
            'cookie' => array('token' => 'test', 'sign_salt' => 'fdjsaifowjfojweo'),
            'redis' => array('token' => 'test', 'ttl' => 300, 'service' => 'redis.local'),
        );

        foreach ($handlers as $type => $config) {
            $handler = $this->createHandler($type, $config);
            try {
                $handler->clear();
            } catch (\Lysine\Service\ConnectionError $ex) {
                $this->markTestSkipped('Redis连接不上，无法测试RedisContextHandler');
                continue;
            }

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
            '明文' => array('token' => 'test', 'sign_salt' => 'fdajkfldsjfldsf'),
            '明文+压缩' => array('token' => 'test', 'sign_salt' => 'fdajkfldsjfldsf', 'zip' => true),
        );

        $mock_cookie = \Test\Mock\Cookie::getInstance();

        foreach ($config_list as $msg => $config) {
            $mock_cookie->reset();

            $handler = new \Lysine\CookieContextHandler($config);
            $handler->set('test', 'abc 中文');

            $mock_cookie->apply();
            $handler->reset();

            $this->assertEquals($handler->get('test'), 'abc 中文', $msg);
        }
    }

    public function testCookieEncrypt() {
        if (!extension_loaded('mcrypt'))
            $this->markTestSkipped('没有加载mcrypt模块，无法测试cookie加密功能');

        $crypt = array(
            'ciphers' => array(MCRYPT_RIJNDAEL_256, MCRYPT_3DES, MCRYPT_BLOWFISH, MCRYPT_CAST_256),
            'mode' => array(MCRYPT_MODE_ECB, MCRYPT_MODE_CBC, MCRYPT_MODE_CFB, MCRYPT_MODE_OFB, MCRYPT_MODE_NOFB),
        );

        $config_default = array('token' => 'test', 'sign_salt' => 'fdajkfldsjfldsf');

        $mock_cookie = \Test\Mock\Cookie::getInstance();
        foreach ($crypt['ciphers'] as $cipher) {
            foreach ($crypt['mode'] as $mode) {
                $config = array_merge($config_default, array('encrypt' => array('uf43jrojfosdf', $cipher, $mode)));

                $mock_cookie->reset();
                $handler = new \Lysine\CookieContextHandler($config);
                $handler->set('test', 'abc 中文');

                $mock_cookie->apply();
                $handler->reset();

                $this->assertEquals($handler->get('test'), 'abc 中文', "cipher:{$cipher} mode: {$mode} 加密解密失败");
            }
        }
    }

    // 数字签名
    public function testCookieContextSign() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'sign_salt' => 'fdajkfldsjfldsf');
        $handler = new \Lysine\CookieContextHandler($config);

        $handler->set('test', 'abc');

        $mock_cookie->apply();
        $handler->reset();

        $_COOKIE['test'] = '0'. $_COOKIE['test'];
        $this->assertNull($handler->get('test'), '篡改cookie内容');

        $_COOKIE['test'] = substr($_COOKIE['test'], 1);
        $handler->reset();

        $handler->setConfig('sign_salt', 'r431oj0if31jr3');
        $this->assertNull($handler->get('test'), 'salt没有起作用');
    }

    // 从自定义方法内计算sign salt
    public function testCookieContextSignSaltFunc() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $salt_func = function($string) {
            $context = json_decode($string, true) ?: array();
            return isset($context['id']) ? $context['id'] : 'rj102jrojfoe';
        };

        $config = array('token' => 'test', 'sign_salt' => $salt_func);
        $handler = new \Lysine\CookieContextHandler($config);

        $id = uniqid();
        $handler->set('id', $id);

        $mock_cookie->apply();
        $handler->reset();

        $this->assertEquals($id, $handler->get('id'), '自定义sign salt没有起作用');
    }

    // 地址绑定
    public function testBindIpCookieContext() {
        $mock_cookie = \Test\Mock\Cookie::getInstance();
        $mock_cookie->reset();

        $config = array('token' => 'test', 'sign_salt' => 'fdajkfldsjfldsf', 'bind_ip' => true);
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
