<?php
namespace Model;

class RBAC {
    static private $instance;

    private function __construct() {
    }

    public function execute($class) {
        \Lysine\logger()->debug('RBAC check class '. $class);
    }

    static public function instance() {
        return self::$instance ?: self::$instance = new self;
    }
}
