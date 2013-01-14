<?php
namespace Test;

class ContextTest extends \PHPUnit_Framework_TestCase {
    protected $handler;

    protected function createHandler($type, $config) {
        return $this->handler = \Lysine\ContextHandler::factory($type, $config);
    }

    protected function tearDown() {
        $this->handler = null;
    }

    public function testAbstractMethods() {
        $handlers = array(
            'session' => array('token' => 'test'),
            'cookie' => array('token' => 'test', 'salt' => 'fdjsaifowjfojweo'),
        );

        foreach ($handlers as $type => $config) {
            $handler = $this->createHandler($type, $config);

            $this->assertFalse($handler->has('test'));

            $handler->set('test', 'abc');
            $this->assertEquals($handler->get('test'), 'abc');

            $handler->remove('test');
            $this->assertNull($handler->get('test'));

            $handler->clear();
            $this->assertEquals($handler->get(), array());
        }
    }
}
