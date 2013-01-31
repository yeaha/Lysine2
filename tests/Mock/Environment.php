<?php
namespace Test\Mock;

class Environment {
    static protected $current_path = '/';

    static public function begin($uri = '/', $method = 'GET', array $params = array()) {
        self::reset();

        $uri = parse_url($uri);

        static::$current_path = $uri['path'];

        $method = strtoupper($method);

        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;

        if (isset($uri['query'])) {
            $_SERVER['QUERY_STRING'] = $uri['query'];
            parse_str($uri['query'], $_GET);
        }

        if ($method == 'GET') {
            $_GET = $params;
        } else {
            $_POST = $params;
        }

        \Test\Mock\Cookie::getInstance()->apply( static::$current_path );

        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
    }

    static public function end() {
        \Test\Mock\Cookie::getInstance()->apply( static::$current_path );
    }

    static public function reset() {
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        $_SERVER = array();
        $_SESSION = \Lysine\Session::getInstance();

        \Test\Mock\Cookie::getInstance()->reset();

        resp()->reset();
    }

    static public function useAjax() {
        self::setHeader('X-REQUESTED-WITH', 'xmlhttprequest');
    }

    static public function setHeader($key, $val) {
        $key = strtoupper('http_' . str_replace('-', '_', $key));
        $_SERVER[$key] = $val;
    }
}
