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

        return \Lysine\Service\Manager::getInstance()->get($name, $args);
    }

    function req() {
        if (!defined('LYSINE_REQUEST_CLASS'))
            return \Lysine\HTTP\Request::getInstance();

        $class = LYSINE_REQUEST_CLASS;
        return $class::getInstance();
    }

    function resp() {
        if (!defined('LYSINE_RESPONSE_CLASS'))
            return \Lysine\HTTP\Response::getInstance();

        $class = LYSINE_RESPONSE_CLASS;
        return $class::getInstance();
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
        $total = (int)$total;
        $page_size = (int)$page_size;
        $current_page = (int)$current_page;

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

    function logger($name = null) {
        return \Lysine\Logging::factory($name ?: '__LYSINE__');
    }

    // 根据参数构建url字符串
    // url('/', array('a' => 1, 'b' => 2));
    // /?a=1&b=2
    // url('/?c=3', array('a' => 1, 'b' => 2, 'c' => false));
    // /?a=1&b=2
    // url('/', array('a' => 1, 'b' => 2, 'c' => 3), array('c' => 4));
    // /?a=1&b=2&c=4
    function url($url, $args = null) {
        $url = parse_url($url);
        if (!isset($url['path']) || !$url['path'])
            $url['path'] = '';

        $query = array();
        if (isset($url['query']))
            parse_str($url['query'], $query);

        if ($args !== null) {
            foreach (array_slice(func_get_args(), 1) as $args) {
                if (!is_array($args)) continue;

                foreach ($args as $k => $v) {
                    if ($v === false) {
                        unset($query[$k]);
                    } else {
                        $query[$k] = $v;
                    }
                }
            }
        }

        $result = '';
        if (isset($url['scheme'])) $result .= $url['scheme'].'://';
        if (isset($url['user'])) {
            $result .= $url['user'];
            if (isset($url['pass'])) $result .= ':'.$url['pass'];
            $result .= '@';
        }

        if (isset($url['host'])) $result .= $url['host'];
        $result .= $url['path'];

        if ($query) $result .= '?'.http_build_query($query);
        if (isset($url['fragment'])) $result .= '#'.$url['fragment'];

        return $result;
    }

    function array_pick(array $data, $key1/*[, $key2[, $key3]]*/) {
        $keys = array_slice(func_get_args(), 1);
        $result = array();

        foreach ($keys as $key) {
            if (isset($data[$key]))
                $result[$key] = $data[$key];
        }

        return $result;
    }

    // 2到62，任意进制转换
    // $number: 转换的数字
    // $from: 本来的进制
    // $to: 转换到进制
    // $use_bcmath: 是否使用bcmath模块处理超大数字
    function base_convert($number, $from, $to, $use_bcmath = null) {
        $base = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $loaded = extension_loaded('bcmath');
        if ($use_bcmath && !$loaded)
            throw new \Lysine\RuntimeError('Require bcmath extension!');

        $use_bcmath = $loaded;

        // 任意进制转换为十进制
        $any2dec = function($number, $from) use ($base, $use_bcmath) {
            if ($from === 10)
                return $number;

            $base = substr($base, 0, $from);
            $dec = 0;
            $number = (string)$number;

            for ($i = 0, $len = strlen($number); $i < $len; $i++) {
                $c = substr($number, $i , 1);
                $n = strpos($base, $c);
                if ($n === false)   // 出现了当前进制不支持的数字
                    trigger_error('Unexpected base character: '. $c, E_USER_ERROR);

                $pos = $len - $i - 1;

                if ($use_bcmath) {
                    $dec = bcadd($dec, bcmul($n, bcpow($from, $pos)));
                } else {
                    $dec += $n * pow($from, $pos);
                }
            }

            return $dec;
        };

        // 十进制转换为任意进制
        $dec2any = function($number, $to) use ($base, $use_bcmath) {
            if ($to === 10)
                return $number;

            $base = substr($base, 0, $to);
            $any = '';

            while ($number >= $to) {
                if ($use_bcmath) {
                    list($number, $c) = array(bcdiv($number, $to), bcmod($number, $to));
                } else {
                    list($number, $c) = array((int)($number / $to), $number % $to);
                }
                $any = substr($base, $c, 1) . $any;
            }

            $any = substr($base, $number, 1) . $any;
            return $any;
        };

        ////////////////////////////////////////////////////////////////////////////////
        $from = (int)$from;
        $to = (int)$to;

        $min_base = 2;
        $max_base = strlen($base);

        if ($from < $min_base || $from > $max_base || $to < $min_base || $to > $max_base)
            trigger_error("Only support base between {$min_base} and {$max_base}", E_USER_ERROR);

        if ($from === $to)
            return $number;

        // 转换为10进制
        $dec = ($from === 10) ? $number : $any2dec($number, $from);
        return $dec2any($dec, $to);
    }
}
