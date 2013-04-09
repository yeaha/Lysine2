<?php
namespace Test;

use \Lysine\DataMapper\Data;

class MetaTest extends \PHPUnit_Framework_TestCase {
    protected $class = '\Test\Mock\DataMapper\Data';

    protected function tearDown() {
        \Test\Mock\DataMapper\Meta::reset();
    }

    protected function setPropsMeta(array $props_meta) {
        \Test\Mock\DataMapper\Meta::reset();
        \Test\Mock\DataMapper\Data::setPropsMeta($props_meta);
    }

    public function testPropMeta() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'time' => array('type' => 'datetime', 'default' => 'now'),
        ));

        $class = $this->class;
        $meta = $class::getMapper()->getMeta();

        $id_meta = $meta->getPropMeta('id');
        $this->assertEquals('integer', $id_meta['type']);
        $this->assertTrue($id_meta['primary_key']);
        $this->assertTrue($id_meta['auto_increase']);
        $this->assertNull($id_meta['default']);

        $time_meta = $meta->getPropMeta('time');
        $this->assertEquals('time', $time_meta['name']);
        $this->assertFalse($time_meta['primary_key']);
        $this->assertEquals('now', $time_meta['default']);
    }
}
