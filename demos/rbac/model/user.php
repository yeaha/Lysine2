<?php
namespace Model;

use Lysine\DataMapper\Data;

class User extends \Lysine\DataMapper\DBData {
    static protected $storage = 'db';
    static protected $collection = 'public.users';
    static protected $props_meta = array(
        'id' => array('type' => Data::TYPE_INTEGER, 'primary_key' => true, 'auto_increase' => true),
        'email' => array('type' => Data::TYPE_STRING),
        'passwd' => array('type' => Data::TYPE_STRING),
        'create_time' => array('type' => 'datetime', 'refuse_update' => true),
        'update_time' => array('type' => 'datetime'),
    );

    protected $roles;

    protected function __before_insert() {
        $this->create_time = strftime('%F %T');
    }

    protected function __before_save() {
        $this->update_time = strftime('%F %T');
    }

    public function getRoles() {
        if ($this->roles === null) {
            if (!$user_id = $this->id())
                return $this->roles = array();

            $select = User_Role::select()->setCols('role_id')
                                         ->where('user_id = ?', $this->id())
                                         ->where('expire_time is null or expire_time > now()');
            $roles = Role::select()->setCols('name')->whereIn('role_id', $select)->execute()->getCols();
            $this->roles = $roles;
        }

        return $this->roles;
    }
}

class User_Role extends \Lysine\DataMapper\DBData {
    static protected $storage = 'db';
    static protected $collection = 'public.user_role';
    static protected $props_meta = array(
        'id' => array('type' => Data::TYPE_INTEGER, 'primary_key' => true, 'auto_increase' => true),
        'user_id' => array('type' => Data::TYPE_INTEGER),
        'role_id' => array('type' => Data::TYPE_INTEGER),
        'expire_time' => array('type' => 'datetime', 'allow_null' => true),
    );
}
