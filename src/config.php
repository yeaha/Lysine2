<?php
namespace Lysine;

class Config {
    static private $config = array();

    static public function import(array $config) {
        self::$config = array_merge(self::$config, $config);
    }

    static public function get($key) {
        if (strpos($key, ',') === false)
             return isset(self::$config[$key]) ? self::$config[$key] : false;

        $config = &self::$config;
        $path = preg_split('/\s?,\s?/', $key, NULL, PREG_SPLIT_NO_EMPTY);

        foreach ($path as $key) {
            if (!isset($config[$key]))
                return false;
            $config = &$config[$key];
        }

        return $config;
    }
}
