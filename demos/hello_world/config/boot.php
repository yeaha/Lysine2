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

$app = new MVC\Application(Config::get('application'));
$app->setRouter( new MVC\Router(Config::get('router')) );

return $app;
