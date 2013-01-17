<?php
namespace Test;

use \Lysine\DataMapper\Data;

class DataMapperData extends \PHPUnit_Framework_TestCase {
    protected $class = '\Test\Mock\DataMapper\Data';

    protected function tearDown() {
        \Test\Mock\DataMapper\Meta::reset();
    }

    protected function setPropsMeta(array $props_meta) {
        \Test\Mock\DataMapper\Meta::reset();
        \Test\Mock\DataMapper\Data::setPropsMeta($props_meta);
    }

    public function testDefaultValue() {
        $this->setPropsMeta(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
            'datetime1' => array('type' => 'datetime', 'default' => Data::CURRENT_DATETIME),
            'datetime2' => array('type' => 'datetime', 'allow_null' => true, 'default' => Data::CURRENT_DATETIME),
            'timestamp' => array('type' => 'datetime', 'default' => Data::CURRENT_TIMESTAMP),
            'date' => array('type' => 'datetime', 'default' => Data::CURRENT_DATE),
            'time' => array('type' => 'datetime', 'default' => Data::CURRENT_TIME),
            'p' => array('type' => 'integer', 'default' => 0),
        ));

        $class = $this->class;
        $d = new $class;

        $this->assertNull($d->id);

        $this->assertEquals($d->datetime1, strftime('%F %T'));
        $this->assertTrue(isset($d->datetime1));

        $this->assertEquals($d->datetime2, strftime('%F %T'));
        $this->assertFalse(isset($d->datetime2));

        $this->assertEquals($d->timestamp, time());

        $this->assertEquals($d->date, strftime('%F'));

        $this->assertEquals($d->time, strftime('%T'));

        $this->assertEquals($d->p, 0);
    }
}
