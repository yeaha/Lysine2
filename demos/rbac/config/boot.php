<?php
use Lysine\Config;
use Lysine\MVC;

define('ROOT_DIR', realpath(__DIR__ .'/../'));
define('DEBUG', true);

require __DIR__ .'/../../../src/loader.php';
require ROOT_DIR .'/lib/mvc.php';

// 加载配置文件
$config = require __DIR__ .'/_config.php';
Config::import($config);

// 初始化外部存储服务管理
Lysine\Service\Manager::getInstance()
    ->importConfig(Config::get('services'));

// 系统日志
Lysine\logger()
    ->setLevel(DEBUG ? Lysine\Logging::DEBUG : Lysine\Logging::ERROR)
    ->addHandler(new Lysine\Logging\FileHandler(Config::get('logging')));

// 使用内置SESSION封装
Lysine\Session::initialize();

// MVC环境初始化
$app = new MVC\Application(Config::get('application'));
$app->setRouter( new MVC\Router(Config::get('router')) );

// 在每次router dispatch之前调用rbac检查
$app->getRouter()->onEvent(MVC\Router::BEFORE_DISPATCH_EVENT, function($class, $path) {
    $rules = Config::get('router', 'rbac');
    $rbac = new \Model\Rbac($rules);

    $rbac->check($class, $path);
});

return $app;
