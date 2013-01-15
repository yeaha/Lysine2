<?php
namespace Test;

define('TEST_DIR', __DIR__);
define('LYSINE_REQUEST_CLASS', '\Test\Mock\Request');
define('LYSINE_RESPONSE_CLASS', '\Test\Mock\Response');

require __DIR__ .'/../src/loader.php';

spl_autoload_register(function($class) {
    $parts = array_map('ucfirst', explode('\\', trim($class, '\\')));
    if ($parts[0] != 'Test') return false;

    array_shift($parts);

    $file = __DIR__ .'/'. implode('/', $parts) .'.php';
    if (!is_file($file)) return false;

    require $file;

    return class_exists($class, false) || interface_exists($class, false);
});

\Lysine\Service\Manager::getInstance()->importConfig(require __DIR__.'/Fixture/config/services.php');
