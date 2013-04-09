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
            'time' => array('type' => 'datetime', 'helper' => '\Lysine\DataMapper\Helper\DateTime', 'default' => 'now'),
        ));

        $class = $this->class;
        $meta = $class::getMapper()->getMeta();

        $id_meta = $meta->getPropMeta('id');
        $this->assertEquals('integer', $id_meta['type']);
        $this->assertTrue($id_meta['primary_key']);
        $this->assertTrue($id_meta['auto_increase']);
        $this->assertNull($time_meta['default']);

        $time_meta = $meta->getPropMeta('time');
        $this->assertEquals('time', $time_meta['name']);
        $this->assertEquals('\Lysine\DataMapper\Helper\DateTime', $time_meta['helper']);
        $this->assertFalse($time_meta['primary_key']);
        $this->assertEquals('now', $time_meta['default']);
    }

    public function testPropHelper() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'a' => array('type' => 'int'),
            'b' => array('type' => 'numeric'),
            'c' => array('type' => 'text'),
            'd' => array('type' => 'string'),
            'e' => array('type' => 'datetime'),
            'f' => array('type' => 'json'),
            'g' => array('type' => 'foo'),
        ));

        $class = $this->class;
        $meta = $class::getMapper()->getMeta();

        $this->assertInstanceof('\Lysine\DataMapper\Helper\Integer', $meta->getPropHelper('id'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\Integer', $meta->getPropHelper('a'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\Numeric', $meta->getPropHelper('b'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\String', $meta->getPropHelper('c'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\String', $meta->getPropHelper('d'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\DateTime', $meta->getPropHelper('e'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\Json', $meta->getPropHelper('f'));
        $this->assertInstanceof('\Lysine\DataMapper\Helper\Mixed', $meta->getPropHelper('g'));
    }
}
