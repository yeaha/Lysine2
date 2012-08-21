<?php
namespace {
    function get($key = null) {
        if ($key === null) return $_GET;
        return isset($_GET[$key]) ? $_GET[$key] : null;
    }

    function post($key = null) {
        if ($key === null) return $_POST;
        return isset($_POST[$key]) ? $_POST[$key] : null;
    }

    function cookie($key = null) {
        if ($key === null) return $_COOKIE;
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
    }

    function put($key = null) {
        static $_PUT = null;

        if ($_PUT === null) {
            if (req()->isPUT()) {
                if (strtoupper(server('request_method')) == 'PUT') {
                    parse_str(file_get_contents('php://input'), $_PUT);
                } else {
                    $_PUT =& $_POST;
                }
            } else {
                $_PUT = array();
            }
        }

        if ($key === null) return $_PUT;
        return isset($_PUT[$key]) ? $_PUT[$key] : null;
    }

    function request($key = null) {
        if ($key === null) return array_merge(put(), $_REQUEST);
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : put($key);
    }

    function has_get($key) {
        return array_key_exists($key, $_GET);
    }

    function has_post($key) {
        return array_key_exists($key, $_POST);
    }

    function has_put($key) {
        return array_key_exists($key, put());
    }

    function has_request($key) {
        return array_key_exists($key, $_REQUEST);
    }

    function env($key = null) {
        if ($key === null) return $_ENV;
        $key = strtoupper($key);
        return isset($_ENV[$key]) ? $_ENV[$key] : false;
    }

    function server($key = null) {
        if ($key === null) return $_SERVER;
        $key = strtoupper($key);
        return isset($_SERVER[$key]) ? $_SERVER[$key] : false;
    }

    function service($name, $args = null) {
        if ($args !== null)
            $args = array_slice(func_get_args(), 1);

        return \Lysine\Service\Manager::instance()->get($name, $args);
    }

    function req() {
        if (!defined('LYSINE_REQUEST_CLASS'))
            return \Lysine\HTTP\Request::instance();

        $class = LYSINE_REQUEST_CLASS;
        return $class::instance();
    }

    function resp() {
        if (!defined('LYSINE_RESPONSE_CLASS'))
            return \Lysine\HTTP\Response::instance();

        $class = LYSINE_RESPONSE_CLASS;
        return $class::instance();
    }

    function cfg($keys = null) {
        $keys = $keys === null
              ? null
              : is_array($keys) ? $keys : func_get_args();

        return \Lysine\Config::get($keys);
    }
}

namespace Lysine {
    // 计算分页 calculate page
    function cal_page($total, $page_size, $current_page = 1) {
        $page_count = ceil($total / $page_size) ?: 1;

        if ($current_page > $page_count) {
            $current_page = $page_count;
        } elseif ($current_page < 1) {
            $current_page = 1;
        }

        $page = array(
            'total' => $total,
            'size' => $page_size,
            'from' => 0,
            'to' => 0,
            'first' => 1,
            'prev' => null,
            'current' => $current_page,
            'next' => null,
            'last' => $page_count,
        );

        if ($current_page > $page['first'])
            $page['prev'] = $current_page - 1;

        if ($current_page < $page['last'])
            $page['next'] = $current_page + 1;

        if ($total) {
            $page['from'] = ($current_page - 1) * $page_size + 1;
            $page['to'] = $current_page == $page['last']
                        ? $total
                        : $current_page * $page_size;
        }

        return $page;
    }

    // 是关联数组还是普通数组
    function is_assoc_array($array) {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }

    function logger() {
        return \Lysine\Logging::factory('__LYSINE__');
    }
}
