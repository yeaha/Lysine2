<?php
namespace Test\Mock;

class Response extends \Lysine\HTTP\Response {
    public function setCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = true) {
        \Test\Mock\Cookie::instance()->set($name, $value, $expire, $path, $domain, $secure, $httponly);
    }
}
