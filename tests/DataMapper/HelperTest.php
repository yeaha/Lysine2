<?php
namespace Test;

use \Lysine\DataMapper\Data;

class HelperTest extends \PHPUnit_Framework_TestCase {
    protected $class = '\Test\Mock\DataMapper\Data';

    protected function tearDown() {
        \Test\Mock\DataMapper\Meta::reset();
    }

    protected function setPropsMeta(array $props_meta) {
        \Test\Mock\DataMapper\Meta::reset();
        \Test\Mock\DataMapper\Data::setPropsMeta($props_meta);
    }

    public function testManager() {
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

        $expect = array(
            'a' => '\Lysine\DataMapper\Helper\Integer',
            'b' => '\Lysine\DataMapper\Helper\Integer',
            'b' => '\Lysine\DataMapper\Helper\Numeric',
            'c' => '\Lysine\DataMapper\Helper\String',
            'd' => '\Lysine\DataMapper\Helper\String',
            'e' => '\Lysine\DataMapper\Helper\DateTime',
            'f' => '\Lysine\DataMapper\Helper\Json',
            'g' => '\Lysine\DataMapper\Helper\Mixed',
        );

        $meta = $class::getMapper()->getMeta();
        $manager = \Lysine\DataMapper\HelperManager::getInstance();

        foreach ($expect as $prop => $class) {
            $prop_meta = $meta->getPropMeta($prop);
            $this->assertInstanceof($class, $manager->getHelper($prop_meta['type']));
        }

        $manager->registerType('foo', '\Lysine\DataMapper\Helper\Json');
        $this->assertInstanceof('\Lysine\DataMapper\Helper\Json', $manager->getHelper('foo'));
    }

    public function testDatetime() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'time' => array('type' => 'datetime', 'default' => 'now'),
            'other' => array('type' => 'datetime', 'format' => 'U'),
        ));

        $class = $this->class;

        $data = new $class;
        $this->assertInstanceof('\DateTime', $data->time);

        $date = '2012-01-01 00:00:00';
        $data->time = $date;
        $this->assertInstanceof('\DateTime', $data->time);
        $this->assertEquals(strtotime($date), $data->time->getTimestamp());

        $ts = 1365487937;
        $data->other = $ts;
        $this->assertInstanceof('\DateTime', $data->other);
        $this->assertEquals($ts, $data->other->getTimestamp());

        $this->assertTrue($data->save());
    }

    public function testString() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'foo' => array('type' => 'string'),
            'bar' => array('type' => 'string', 'default' => 'bar'),
        ));
        $class = $this->class;

        $data = new $class;
        $this->assertNull($data->foo);
        $this->assertEquals('bar', $data->bar);

        $data->foo = '';
        $this->assertNull($data->foo);

        $data->foo = 'foo';
        $this->assertEquals('foo', $data->foo);
    }

    public function testInteger() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'foo' => array('type' => 'integer'),
            'bar' => array('type' => 'integer', 'default' => 1),
        ));
        $class = $this->class;

        $data = new $class;
        $this->assertNull($data->foo);
        $this->assertSame(1, $data->bar);

        $data->foo = '';
        $this->assertNull($data->foo);

        $data->foo = 'aaaaa';
        $this->assertSame(0, $data->foo);

        $data->foo = 1;
        $this->assertSame(1, $data->foo);
    }

    public function testNumeric() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'foo' => array('type' => 'numeric'),
        ));
        $class = $this->class;

        $data = new $class;
        $this->assertNull($data->foo);

        $data->foo = '';
        $this->assertNull($data->foo);

        $data->foo = '1.1';
        $this->assertSame(1.1, $data->foo);
    }
}
