<?php
namespace Test\Mock\DataMapper;

class Data extends \Lysine\DataMapper\Data {
    static protected $service = 'mock.storage';
    static protected $collection = 'mock.data';
    static protected $attributes = array(
        'id' => array('type' => 'integer', 'primary_key' => true, 'auto_generate' => true),
    );

    static public function getMapper() {
        return \Test\Mock\DataMapper\Mapper::factory( get_called_class() );
    }
}
