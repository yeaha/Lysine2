<?php
namespace Test\Mock\DataMapper;

class Data extends \Lysine\DataMapper\Data {
    static protected $storage = 'mock.storage';
    static protected $collection = 'mock.data';
    static protected $props_meta = array();

    static public function getMapper() {
        return \Test\Mock\DataMapper\Mapper::factory( get_called_class() );
    }

    static public function setPropsMeta(array $props_meta) {
        static::$props_meta = $props_meta;
    }
}
