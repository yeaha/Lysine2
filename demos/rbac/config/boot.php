<?php
use Lysine\Config;
use Lysine\MVC;

define('ROOT_DIR', realpath(__DIR__ .'/../'));
define('DEBUG', true);

require __DIR__ .'/../../../src/loader.php';
require ROOT_DIR .'/lib/mvc.php';

$config = require __DIR__ .'/_config.php';

Config::import($config);

Lysine\Service\Manager::instance()
    ->importConfig(Config::get('services'));

Lysine\logger()
    ->setLevel(DEBUG ? Lysine\Logging::DEBUG : Lysine\Logging::ERROR)
    ->addHandler(new Lysine\Logging\FileHandler(Config::get('logging')));

Lysine\Session::initialize();

$router = new MVC\Router(Config::get('router'));

$app = new MVC\Application(Config::get('application'));
$app->setRouter( $router );

Lysine\Event::instance()->listen($router, $router::BEFORE_DISPATCH_EVENT, array(Model\RBAC::instance(), 'execute'));

return $app;
