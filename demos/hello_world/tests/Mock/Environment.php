<?php
namespace Test\Mock;

class Environment {
    static public function init($uri, $method, array $params = array(), array $options = array()) {
        self::reset();

        $uri = parse_url($uri);
        $method = strtoupper($method);

        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;

        if (isset($uri['query'])) {
            $_SERVER['QUERY_STRING'] = $uri['query'];
            parse_str($uri['query'], $_GET);
        }

        if ($method != 'GET')
            $_POST = $params;

        if (isset($options['ajax']) && $options['ajax'])
            self::useAjax();

        if (isset($options['header']))
            foreach ($options['header'] as $key => $val)
                self::setHeader($key, $val);

        $_COOKIE = \Test\Mock\Cookie::instance()->get( $uri['path'] );
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
    }

    static protected function reset() {
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        $_SERVER = array();
        $_SESSION = \Lysine\Session::instance();
    }

    static protected function useAjax() {
        self::setHeader('X-REQUESTED-WITH', 'xmlhttprequest');
    }

    static protected function setHeader($key, $val) {
        $key = strtoupper('http_' . str_replace('-', '_', $key));
        $_SERVER[$key] = $val;
    }
}
