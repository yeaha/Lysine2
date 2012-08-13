<?php
namespace Lysine;

class HTTP {
    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NO_CONTENT = 204;
    const MOVED_PERMANENTLY = 301;
    const FOUND = 302;
    const SEE_OTHER = 303;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_ACCEPTABLE = 406;
    const REQUEST_TIMEOUT = 408;
    const CONFLICT = 409;
    const GONE = 410;
    const LENGTH_REQUIRED = 411;
    const PRECONDITION_FAILED = 412;
    const REQUEST_ENTITY_TOO_LARGE = 413;
    const UNSUPPORTED_MEDIA_TYPE = 415;
    const EXPECTATION_FAILED = 417;
    const INTERNAL_SERVER_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
    const BAD_GATEWAY = 502;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT = 504;

    static protected $status = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
    );

    static public function getStatusMessage($code) {
        return self::$status[$code];
    }

    static public function getStatusHeader($code) {
        $message = self::$status[$code];

        return strpos(PHP_SAPI, 'cgi') === 0
             ? sprintf('Status: %d %s', $code, $message)
             : sprintf('HTTP/1.1 %d %s', $code, $message);
    }
}

////////////////////////////////////////////////////////////////////////////////
namespace Lysine\HTTP;

class Request {
    static private $instance;

    private $method;
    private $request_uri;

    protected function __construct() {
    }

    public function header($key) {
        $key = 'http_'. str_replace('-', '_', $key);
        return server($key);
    }

    public function requestUri() {
        if ($this->request_uri)
            return $this->request_uri;

        if ($uri = server('request_uri'))
            return $this->request_uri = $uri;

        throw new \Lysine\RuntimeError('Unknown request URI');
    }

    public function method() {
        if ($this->method)
            return $this->method;

        $method = strtoupper($this->header('x-http-method-override') ?: server('request_method'));
        if ($method != 'POST') return $this->method = $method;

        // 某些js库的ajax封装使用这种方式
        $method = post('_method') ?: $method;
        unset($_POST['_method']);
        return $this->method = strtoupper($method);
    }

    public function isGET() {
        return ($this->method() === 'GET') ?: $this->isHEAD();
    }

    public function isPOST() {
        return $this->method() === 'POST';
    }

    public function isPUT() {
        return $this->method() === 'PUT';
    }

    public function isDELETE() {
        return $this->method() === 'DELETE';
    }

    public function isHEAD() {
        return $this->method() === 'HEAD';
    }

    public function isAJAX() {
        return strtolower($this->header('X_REQUESTED_WITH')) == 'xmlhttprequest';
    }

    public function referer() {
        return server('http_referer');
    }

    public function ip($proxy = false) {
        $ip = $proxy
            ? server('http_client_ip') ?: server('http_x_forwarded_for') ?: server('remote_addr')
            : server('remote_addr');

        if (strpos($ip, ',') === false)
            return $ip;

        // private ip range, ip2long()
        $private = array(
            array(0, 50331647),             // 0.0.0.0, 2.255.255.255
            array(167772160, 184549375),    // 10.0.0.0, 10.255.255.255
            array(2130706432, 2147483647),  // 127.0.0.0, 127.255.255.255
            array(2851995648, 2852061183),  // 169.254.0.0, 169.254.255.255
            array(2886729728, 2887778303),  // 172.16.0.0, 172.31.255.255
            array(3221225984, 3221226239),  // 192.0.2.0, 192.0.2.255
            array(3232235520, 3232301055),  // 192.168.0.0, 192.168.255.255
            array(4294967040, 4294967295),  // 255.255.255.0 255.255.255.255
        );

        $ip_set = array_map('trim', explode(',', $ip));

        // 检查是否私有地址，如果不是就直接返回
        foreach ($ip_set as $ip) {
            $long = ip2long($ip);
            $is_private = false;

            foreach ($private as $m) {
                list($min, $max) = $m;
                if ($long >= $min && $long <= $max) {
                    $is_private = true;
                    break;
                }
            }

            if (!$is_private) return $ip;
        }

        return array_shift($ip_set);
    }

    //////////////////// static method ////////////////////
    static public function instance() {
        return self::$instance ?: (self::$instance = new static);
    }
}

class Response {
    static private $instance;

    protected $code = \Lysine\HTTP::OK;
    protected $header = array();
    protected $cookie = array();
    protected $body;

    protected function __construct() {
    }

    public function execute() {
        list($header, $body) = $this->compile();

        \Lysine\Session::instance()->commit();

        array_map('header', $header);
        $this->header = array();

        foreach ($this->cookie as $config) {
            list($name, $value, $expire, $path, $domain, $secure, $httponly) = $config;
            setCookie($name, $value, $expire, $path, $domain, $secure, $httponly);
        }
        $this->cookie = array();

        echo $body;
    }

    public function setCode($code) {
        $this->code = (int)$code;
        return $this;
    }

    public function getCode() {
        return $this->code;
    }

    public function setCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = false, $httponly = true) {
        $key = sprintf('%s@%s:%s', $name, $domain, $path);
        $this->cookie[$key] = array($name, $value, $expire, $path, $domain, $secure, $httponly);
        return $this;
    }

    public function setHeader($header) {
        if (strpos($header, ':')) {
            list($key, $val) = explode(':', $header);
            $this->header[trim($key)] = trim($val);
        } else {
            $this->header[$header] = null;
        }
        return $this;
    }

    public function setBody($body) {
        $this->body = $body;
        return $this;
    }

    // return array($header, $body);
    public function compile() {
        $body = in_array($this->getCode(), array(204, 301, 302, 303, 304))
              ? ''
              : (string)$this->body;

        return array(
            $this->compileHeader(),
            $body,
        );
    }

    public function reset() {
        $this->code = \Lysine\HTTP::OK;
        $this->header = array();
        $this->cookie = array();
        $this->body = null;

        \Lysine\Session::instance()->reset();

        return $this;
    }

    public function redirect($url, $code = 303) {
        $this->reset()
             ->setCode($code)
             ->setHeader('Location: '. $url);

        return $this;
    }

    //////////////////// protected method ////////////////////
    protected function compileHeader() {
        $header = array();
        $header[] = \Lysine\HTTP::getStatusHeader($this->code ?: 200);

        foreach ($this->header as $key => $val)
            $header[] = $val === null
                      ? $key
                      : $key .': '. $val;

        return $header;
    }

    //////////////////// static method ////////////////////
    static public function instance() {
        return self::$instance ?: (self::$instance = new static);
    }
}
