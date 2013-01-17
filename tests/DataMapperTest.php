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
            'create_time' => array('type' => 'datetime', 'default' => Data::CURRENT_DATETIME),
            'update_time' => array('type' => 'datetime', 'allow_null' => true, 'default' => Data::CURRENT_DATETIME),
            'active' => array('type' => 'integer', 'default' => 0),
        ));

        $class = $this->class;
        $d = new $class;

        $this->assertNull($d->id);

        $this->assertEquals($d->create_time, strftime('%F %T'));
        $this->assertTrue(isset($d->create_time));

        $this->assertEquals($d->update_time, strftime('%F %T'));
        $this->assertFalse(isset($d->update_time));

        $this->assertEquals($d->active, 0);
    }
}
