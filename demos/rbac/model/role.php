<?php
namespace Model;

class Role extends \Lysine\DataMapper\DBData {
    static protected $storage = 'db';
    static protected $collection = 'public.roles';
    static protected $props_meta = array(
        'id' => array('type' => Data::TYPE_INTEGER, 'primary_key' => true, 'auto_increase' => true),
        'name' => array('type' => Data::TYPE_STRING),
    );
}
