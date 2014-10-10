<?php
namespace Test;

use \Lysine\DataMapper\Data;

class DataTest extends \PHPUnit_Framework_TestCase {
    protected $class = '\Test\Mock\DataMapper\Data';

    protected function setAttributes(array $attributes) {
        $class = $this->class;
        $class::getMapper()->setAttributes($attributes);
    }

    public function testDefaultValue() {
        $class = $this->class;

        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
            'p' => array('type' => 'integer', 'default' => 0),
        ));

        $class = $this->class;
        $d = new $class;

        $this->assertNull($d->id);

        $this->assertEquals($d->p, 0);
    }

    public function testSetProp() {
        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
            'email' => array('type' => 'string', 'refuse_update' => true, 'pattern' => "/^([a-z0-9_\-\.])+\@([a-z0-9_\-\.])+\.([a-z]{2,4})$/i"),
            'name' => array('type' => 'string', 'allow_null' => true),
        ));

        $class = $this->class;
        $data = new $class;
        $this->assertTrue($data->isFresh());

        $test = false;
        try {
            $data->email = 'yangyi';
        } catch (\UnexpectedValueException $ex) {
            $test = true;
        }
        $this->assertTrue($test, '属性pattern检查没有生效');

        $data->name = '';
        $this->assertNull($data->name, '对string类型的属性赋值\'\'应该转换为null');

        $data->email = 'yangyi.cn.gz@gmail.com';
        $data->name = 'yangyi';
        $this->assertTrue($data->isDirty());

        $data->save();
        $this->assertFalse($data->isFresh());
        $this->assertFalse($data->isDirty());

        // 对属性设置同样的值，应该不会把属性标记为已修改
        $data->name = 'yangyi';
        $this->assertFalse($data->isDirty());
        $data->destroy();

        $data = new $class;
        try {
            $data->save();
            $this->fail('属性的not allow null没有生效');
        } catch (\UnexpectedValueException $ex) {
        }

        //////////////////// refuse update ////////////////////
        $values = array(
            'id' => 1,
            'email' => 'yangyi.cn.gz@gmail.com',
            'name' => 'yangyi',
        );
        $data = new $class($values, array('fresh' => false));

        $test = false;
        try {
            $data->email = 'yangyi.cn.gz@gmail.com';
        } catch (\RuntimeException $ex) {
            $test = true;
        }
        $this->assertTrue($test, '属性的refuse update没有生效');

        $test = false;
        try {
            $data->id = 2;
        } catch (\RuntimeException $ex) {
            $test = true;
        }
        $this->assertTrue($test, '主键应该refuse update');

        //////////////////// null not allowed ////////////////////
        $data = new $class();

        $test = false;
        try {
            $data->save();
        } catch (\UnexpectedValueException $ex) {
            $test = true;
        }
        $this->assertTrue($test);

        $test = false;
        $data->email = 'yangyi.cn.gz@gmail.com';
        try {
            $data->save();
        } catch (\UnexpectedValueException $ex) {
            $test = true;
        }
        $this->assertFalse($test);

        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true),
        ));

        $class = $this->class;
        $data = new $class();

        $test = false;
        try {
            $data->save();
        } catch (\UnexpectedValueException $ex) {
            $test = true;
        }
        $this->assertTrue($test);
    }

    public function testGetProp() {
        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
            'email' => array('type' => 'string', 'refuse_update' => true),
            'name' => array('type' => 'string', 'allow_null' => true),
        ));

        $class = $this->class;
        $data = new $class(array(
            'email' => 'yangyi.cn.gz@gmail.com',
            'name' => 'yangyi',
        ));

        $this->assertEquals($data->email, 'yangyi.cn.gz@gmail.com');
        $this->assertEquals($data->name, 'yangyi');

        $test = false;
        try {
            $data->passwd;
        } catch (\UnexpectedValueException $ex) {
            $test = true;
        }
        $this->assertTrue($test);
    }

    public function testPrimaryKey() {
        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true),
        ));

        $class = $this->class;
        $data = new $class(array(
            'id' => 1
        ));

        $this->assertEquals(1, $data->id());

        $data->save();
        $test = false;
        try {
            $data->id = 2;
        } catch (\RuntimeException $ex) {
            $test = true;
        }
        $this->assertTrue($test);

        // 复合主键
        $this->setAttributes(array(
            'a' => array('type' => 'integer', 'primary_key' => true),
            'b' => array('type' => 'integer', 'primary_key' => true),
        ));

        $class = $this->class;
        $data = new $class(array(
            'a' => 1,
            'b' => 2,
        ));

        $this->assertSame(array('a' => 1, 'b' => 2), $data->id());
    }

    public function testStrictProp() {
        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
            'name' => array('type' => 'string', 'strict' => true),
            'address' => array('type' => 'string'),
        ));

        $class = $this->class;
        $data = new $class;

        try {
            $data->merge(array(
                'name' => 'abc',
                'address' => 'def',
                'other' => 'xyz',
            ));
        } catch (\UnexpectedValueException $ex) {
            $this->fail('merge()没有忽略不存在的属性');
        }
        $this->assertFalse(isset($data->name), 'merge()没有忽略strict属性');
        $this->assertTrue(isset($data->address), 'merge()应该可以修改非strict属性');

        $data->name = 'abc';
        $this->assertTrue(isset($data->name), 'strict属性应该允许->设置');
    }

    public function testFindRegistry() {
        $registry = \Lysine\DataMapper\Registry::getInstance();

        $this->assertTrue($registry->isEnabled());

        $registry->disable();
        $this->assertFalse($registry->isEnabled());

        $registry->enable();
        $this->assertTrue($registry->isEnabled());

        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true),
            'name' => array('type' => 'string', 'strict' => true),
            'address' => array('type' => 'string'),
        ));

        $class = $this->class;
        $data = new $class;
        $data->id = 9999999;
        $data->name = 'name';
        $data->address = 'address';
        $data->save();

        $id = $data->id();

        $data1 = $class::find($id);
        $data2 = $class::find($id);

        $this->assertEquals(spl_object_hash($data1), spl_object_hash($data2));

        $registry->disable();
        $data3 = $class::find($id);

        $this->assertNotEquals(spl_object_hash($data1), spl_object_hash($data3));

        $registry->enable();
        $data4 = $class::find($id);

        $this->assertEquals(spl_object_hash($data1), spl_object_hash($data4));
    }

    public function testNestedType() {
        $this->setAttributes(array(
            'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
            'json' => array('type' => 'json'),
            'hstore' => array('type' => 'pg_hstore'),
            'array' => array('type' => 'pg_array'),
        ));

        $class = $this->class;
        $mapper = $class::getMapper();

        foreach ($mapper->getAttributes() as $key => $attribute) {
            if ($key == 'id') continue;

            $this->assertSame($attribute['default'], array());
            $this->assertSame($attribute['strict'], true);
        }
    }
}
