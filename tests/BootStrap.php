<?php
define('LYSINE_REQUEST_CLASS', '\Test\Mock\Request');
define('LYSINE_RESPONSE_CLASS', '\Test\Mock\Response');

require __DIR__ .'/../vendor/autoload.php';

\Lysine\Service\Manager::getInstance()->importConfig(require __DIR__.'/Fixture/config/services.php');
