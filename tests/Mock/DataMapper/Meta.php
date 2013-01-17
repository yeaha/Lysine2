<?php
namespace Test\Mock\DataMapper;

class Meta extends \Lysine\DataMapper\Meta {
    static public function reset($class = null) {
        if ($class) {
            unset(static::$instance[$class]);
        } else {
            static::$instance = array();
        }
    }
}
