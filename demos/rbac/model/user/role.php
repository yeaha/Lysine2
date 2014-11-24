<?php
namespace Model\User;

class Role extends \Lysine\DataMapper\DBData {
    static protected $service = 'db';
    static protected $collection = 'rbac.users_role';
    static protected $attribute = array(
        'user_id' => array('type' => 'integer', 'primary_key' => true),
        'role' => array('type' => 'string', 'primary_key' => true),
        'expire_time' => array('type' => 'datetime', 'allow_null' => true),
    );
}
