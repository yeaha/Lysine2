<?php
namespace Test;

use Test\Mock\Environment;

abstract class Controller extends \PHPUnit_Framework_TestCase {
    static protected $app;

    public function __construct() {
        self::$app = \Test\app();
    }

    protected function GET($path, array $options = array()) {
        return $this->execute($path, 'GET', array(), $options);
    }

    protected function POST($path, array $params, array $options = array()) {
        return $this->execute($path, 'POST', $params, $options);
    }

    protected function PUT($path, array $params, array $options = array()) {
        $params['_method'] = 'PUT';
        return $this->POST($path, $params, $options);
    }

    protected function DELETE($path, array $options = array()) {
        return $this->POST($path, array('_method' => 'DELETE'), $options);
    }

    protected function execute($path, $method, array $params, array $options) {
        Environment::init($path, $method, $params, $options);

        $response = self::$app->execute($path, $method);

        return $response;
    }
}
