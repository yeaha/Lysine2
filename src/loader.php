<?php
defined('DEBUG') or define('DEBUG', false);

if (!defined('LYSINE_NO_ERROR_HANDLER'))
    set_error_handler(function($errno, $error, $file = null, $line = null) {
        if (error_reporting() & $errno)
            throw new \ErrorException($error, $errno, $errno, $file, $line);
        return true;
    });

spl_autoload_register(function ($class) {
    static $files = null;
    if ($files === null)
        $files = require __DIR__ . '/class_files.php';

    $class = strtolower(ltrim($class, '\\'));

    if (stripos($class, 'lysine\\') !== 0) return false;
    if (!array_key_exists($class, $files)) return false;

    require __DIR__ .'/'. $files[$class];

    return class_exists($class, false) || interface_exists($class, false);
});

require __DIR__ .'/functions.php';
