<?php
namespace Lysine;

use Lysine\RuntimeError;

// 会话之间的上下文数据封装
abstract class ContextHandler {
    protected $config;

    abstract public function set($key, $val);
    abstract public function get($key = null);
    abstract public function has($key);
    abstract public function remove($key);
    abstract public function clear();

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function setConfig($key, $val) {
        $this->config[$key] = $val;
    }

    public function getConfig($key = null) {
        return ($key === null)
             ? $this->config
             : isset($this->config[$key]) ? $this->config[$key] : null;
    }

    public function getToken() {
        if (!$token = $this->getConfig('token'))
            throw new RuntimeError('Undefined context save token');

        return $token;
    }

    static public function factory($type, array $config) {
        switch (strtolower($type)) {
            case 'session': return new SessionContextHandler($config);
            case 'cookie': return new CookieContextHandler($config);
            case 'redis': return new RedisContextHandler($config);
            default:
                throw new RuntimeError('Unknown context handler type: '. $type);
        }
    }
}

// $config = array(
//     'token' => (string),     // 必须，上下文存储唯一标识
// );
//
// $handler = ContextHandler::factory('session', $config);
// $handler = new SessionContextHandler($config);
class SessionContextHandler extends ContextHandler {
    public function set($key, $val) {
        $token = $this->getToken();

        $_SESSION[$token][$key] = $val;
    }

    public function get($key = null) {
        $token = $this->getToken();
        $context = isset($_SESSION[$token]) ? $_SESSION[$token] : array();

        return ($key === null)
             ? $context
             : (isset($context[$key]) ? $context[$key] : null);
    }

    public function has($key) {
        $token = $this->getToken();

        return isset($_SESSION[$token][$key]);
    }

    public function remove($key) {
        $token = $this->getToken();

        unset($_SESSION[$token][$key]);
    }

    public function clear() {
        $token = $this->getToken();

        unset($_SESSION[$token]);
    }
}

// 默认使用明文加数字签名防伪方式保存数据
// 如果要存放敏感信息，注意使用加密存储
//
// $config = array(
//     'token' => (string),         // 必须，上下文存储唯一标识
//     'sign_salt' => (mixed),      // 必须，用于计算数字签名的salt，可以为字符串或者callable方法
//     'encrypt' => array(          // 可选，加密方法配置
//         (string),                //   必须，salt string，随机字符串
//         (string),                //   必须，ciphers name，例如MCRYPT_3DES
//         (string),                //   可选，ciphers mode, 默认MCRYPT_MODE_ECB
//         (integer),               //   可选，random device，默认MCRYPT_RAND
//     ),
//     'domain' => (string),        // 可选，cookie 域名，默认：null
//     'path' => (string),          // 可选，cookie 路径，默认：/
//     'expire_at' => (integer),    // 可选，过期时间，优先级高于ttl
//     'ttl' => (integer),          // 可选，生存期，单位：秒，默认：0
//     'bind_ip' => (bool),         // 可选，是否绑定到IP，默认：false
//     'zip' => (bool),             // 可选，是否将数据压缩保存，默认：false
// );
//
// $handler = ContextHandler::factory('cookie', $config);
// $handler = new CookieContextHandler($config);
class CookieContextHandler extends ContextHandler {
    protected $data;

    public function set($key, $val) {
        $this->restore();

        $this->data[$key] = $val;
        $this->save();
    }

    public function get($key = null) {
        $data = $this->restore();

        return ($key === null)
             ? $data
             : (isset($data[$key]) ? $data[$key] : null);
    }

    public function has($key) {
        $data = $this->restore();

        return isset($data[$key]);
    }

    public function remove($key) {
        $this->restore();

        unset($this->data[$key]);
        $this->save();
    }

    public function clear() {
        $this->data = array();
        $this->save();
    }

    public function reset() {
        $this->data = null;
        $this->salt = null;
    }

    // 保存到cookie
    protected function save() {
        $token = $this->getToken();
        $data = $this->data ? $this->encode($this->data) : '';
        if (!$expire = (int)$this->getConfig('expire_at'))
            $expire = ($ttl = (int)$this->getConfig('ttl')) ? (time() + $ttl) : 0;
        $path = $this->getConfig('path') ?: '/';
        $domain = $this->getConfig('domain');

        resp()->setCookie($token, $data, $expire, $path, $domain);

        return $data;
    }

    // 从cookie恢复数据
    protected function restore() {
        if ($this->data !== null)
            return $this->data;

        do {
            if (!$data = cookie($this->getToken()))
                break;

            if (!$data = $this->decode($data))
                break;

            return $this->data = $data;
        } while (false);

        return $this->data = array();
    }

    // 把上下文数据编码为字符串
    // return string
    protected function encode($data) {
        $data = json_encode($data);

        // 添加数字签名
        $data = $data . $this->getSign($data);

        if ($this->getConfig('encrypt')) {      // 加密，加密数据不需要压缩
            $data = $this->encrypt($data);
        } elseif ($this->getConfig('zip')) {    // 压缩
            // 压缩文本最前面有'_'，用于判断是否压缩数据
            // 否则在运行期间切换压缩配置时，错误的数据格式会导致gzcompress()报错
            $data = '_'. gzcompress($data, 9);
        }

        return $data;
    }

    // 把保存为字符串的上下文数据恢复为数组
    // return array('c' => (array), 't' => (integer));
    protected function decode($string) {
        if ($this->getConfig('encrypt')) {      // 解密
            $string = $this->decrypt($string);
        } elseif ($this->getConfig('zip')) {    // 解压
            $string = (substr($string, 0, 1) == '_')
                    ? gzuncompress(substr($string, 1))
                    : $string;
        }

        // sha1 raw binary length is 20
        $hash_length = 20;

        // 数字签名校验
        do {
            if (!$string || strlen($string) <= $hash_length)
                break;

            $hash = substr($string, $hash_length * -1);
            $string = substr($string, 0, strlen($string) - $hash_length);

            if ($this->getSign($string) !== $hash)
                break;

            return json_decode($string, true) ?: array();
        } while (false);

        return array();
    }

    // 加密字符串
    protected function encrypt($string) {
        list($salt, $cipher, $mode, $device) = $this->getEncryptConfig();

        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv = mcrypt_create_iv($iv_size, $device);

        $salt = substr($salt, 0, $iv_size);

        return mcrypt_encrypt($cipher, $salt, $string, $mode, $iv);
    }

    // 解密字符串
    protected function decrypt($string) {
        list($salt, $cipher, $mode, $device) = $this->getEncryptConfig();

        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv = mcrypt_create_iv($iv_size, $device);

        $salt = substr($salt, 0, $iv_size);

        if ($decrypted = mcrypt_decrypt($cipher, $salt, $string, $mode, $iv))
            $decrypted = rtrim($decrypted, "\0");   // remove padding

        return $decrypted;
    }

    // 获得加密配置
    protected function getEncryptConfig() {
        $config = $this->getConfig('encrypt') ?: array();

        if (!isset($config[0]) || !$config[0])
            throw new RuntimeError('Require encrypt salt string');
        $salt = $config[0];

        if (!isset($config[1]) || !$config[1])
            throw new RuntimeError('Require encrypt cipher name');
        $cipher = $config[1];

        if (!in_array($cipher, mcrypt_list_algorithms()))
            throw new RuntimeError('Unsupport encrypt cipher: '. $cipher);

        $mode = isset($config[2]) ? $config[2] : MCRYPT_MODE_ECB;
        $device = isset($config[3]) ? $config[3] : MCRYPT_RAND;

        return array($salt, $cipher, $mode, $device);
    }

    // 生成数字签名
    protected function getSign($string) {
        $salt = $this->getSignSalt($string);
        return sha1($string . $salt, true);
    }

    // 获得计算数字签名的salt字符串
    protected function getSignSalt($string) {
        do {
            if (!$salt = $this->getConfig('sign_salt'))
                break;

            if (is_callable($salt))
                $salt = call_user_func($salt, $string);

            if (!$salt)
                break;

            if ($this->getConfig('bind_ip')) {
                $ip = req()->ip();
                $salt .= long2ip(ip2long($ip) & ip2long('255.255.255.0'));     // 192.168.1.123 -> 192.168.1.0
            }

            return $salt;
        } while (false);

        throw new RuntimeError('Require signature salt string');
    }
}

// $config = array(
//     'token' => (string),     // 必须，上下文存储唯一标识
//     'service' => (string),   // 必须，用于存储的redis服务名
//     'ttl' => (integer),      // 可选，生存期，单位：秒，默认：0
// );
//
// $handler = ContextHandler::factory('redis', $config);
// $handler = new RedisContextHandler($config);
class RedisContextHandler extends ContextHandler {
    public function set($key, $val) {
        $redis = $this->getService();
        $token = $this->getToken();

        if ($ttl = (int)$this->getConfig('ttl')) {
            $redis->multi(\Redis::PIPELINE)
                  ->hSet($token, $key, $val)
                  ->setTimeout($token, $ttl)
                  ->exec();
        } else {
            $redis->hSet($token, $key, $val);
        }
    }

    public function get($key = null) {
        $redis = $this->getService();
        $token = $this->getToken();

        return $key === null
             ? $redis->hGetAll($token)
             : $redis->hGet($token, $key);
    }

    public function has($key) {
        $redis = $this->getService();
        $token = $this->getToken();

        return $redis->hExists($token, $key);
    }

    public function remove($key) {
        $redis = $this->getService();
        $token = $this->getToken();

        if ($ttl = (int)$this->getConfig('ttl')) {
            $redis->multi(\Redis::PIPELINE)
                  ->hDel($token, $key)
                  ->setTimeout($token, $ttl)
                  ->exec();
        } else {
            $redis->hDel($token, $key);
        }
    }

    public function clear() {
        $redis = $this->getService();
        $token = $this->getToken();

        $redis->delete($token);
    }

    protected function getService() {
        if (!$service = $this->getConfig('service'))
            throw new RuntimeError('Require redis service for context');

        return service($service);
    }
}

class Session implements \ArrayAccess {
    use \Lysine\Traits\Singleton;

    protected $start;
    protected $data = array();
    protected $snapshot = array();

    protected function __construct() {
        $this->start = (session_status() === PHP_SESSION_ACTIVE);

        if ($this->start)
            $this->data = $_SESSION instanceof Session
                        ? $_SESSION->toArray()
                        : $_SESSION;

        $this->snapshot = $this->data;
    }

    public function offsetExists($offset) {
        $this->start();
        return isset($this->data[$offset]);
    }

    // 返回引用，否则会发生"Indirect modification of overloaded element of $class has no effect"错误
    public function &offsetGet($offset) {
        $this->start();
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->start();
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        $this->start();
        unset($this->data[$offset]);
    }

    public function commit() {
        if (!$this->start)
            return false;

        $_SESSION = $this->data;
        session_write_close();

        $this->snapshot = $this->data;
        $_SESSION = $this;

        $this->start = (session_status() === PHP_SESSION_ACTIVE);
    }

    public function reset() {
        $this->data = $this->snapshot;
        $this->start = (session_status() === PHP_SESSION_ACTIVE);
    }

    public function destroy() {
        if ($this->start())
            session_destroy();

        $this->reset();
    }

    public function start() {
        if ($this->start)
            return true;

        if (session_status() === PHP_SESSION_DISABLED || PHP_SAPI == 'cli')
            return false;

        session_start();
        $this->data = $_SESSION;
        $this->snapshot = $_SESSION;

        $_SESSION = $this;
        return $this->start = true;
    }

    public function toArray() {
        return $this->data;
    }

    //////////////////// static method ////////////////////

    static public function initialize() {
        if (!isset($GLOBALS['_SESSION']) or !($GLOBALS['_SESSION'] instanceof Session))
            $GLOBALS['_SESSION'] = self::getInstance();
        return self::getInstance();
    }
}

class Curl {
    protected $handler;
    protected $options = array();

    public function __construct() {
        if (!extension_loaded('curl'))
            throw new \Lysine\RuntimeError('Require curl extension');
    }

    public function __destruct() {
        $this->close();
    }

    public function close() {
        if ($this->handler) {
            curl_close($this->handler);
            $this->handler = null;
        }
        return $this;
    }

    public function setOptions(array $options) {
        foreach ($options as $key => $val)
            $this->options[$key] = $val;
        return $this;
    }

    public function execute($url, array $options = array()) {
        $this->close();

        $curl_options = $this->options;
        foreach ($options as $key => $val)
            $curl_options[$key] = $val;
        $curl_options[CURLOPT_URL] = $url;

        $handler = curl_init();

        curl_setopt_array($handler, $curl_options);

        $result = curl_exec($handler);
        if ($result === false)
            throw new \Lysine\RuntimeError('Curl Error: '. curl_error($handler), curl_errno($handler));

        $this->handler = $handler;

        return $result;
    }

    public function getInfo($info = null) {
        if (!$this->handler)
            return false;

        return ($info === null)
             ? curl_getinfo($this->handler)
             : curl_getinfo($this->handler, $info);
    }
}

////////////////////////////////////////////////////////////////////////////////
namespace Lysine\Curl;

// $c = new Lysine\Curl\Http;
// $msg = $c->get('https://github.com/yeaha/Lysine2');
class Http extends \Lysine\Curl {
    static public $method_emulate = true;

    public function head($url, array $params = array()) {
        return $this->send($url, 'HEAD', $params);
    }

    public function get($url, array $params = array()) {
        return $this->send($url, 'GET', $params);
    }

    public function post($url, array $params = array()) {
        return $this->send($url, 'POST', $params);
    }

    public function put($url, array $params = array()) {
        return $this->send($url, 'PUT', $params);
    }

    public function delete($url, array $params = array()) {
        return $this->send($url, 'DELETE', $params);
    }

    protected function send($url, $method, array $params) {
        $method = strtoupper($method);

        // 数组必须用http_build_query转换为字符串
        // 否则会使用multipart/form-data而不是application/x-www-form-urlencoded
        $params = http_build_query($params) ?: null;

        $options = array();

        if ($method == 'GET' || $method == 'HEAD') {
            if ($params)
                $url = strpos($url, '?')
                     ? $url .'&'. $params
                     : $url .'?'. $params;

            if ($method == 'GET') {
                $options[CURLOPT_HTTPGET] = true;
            } else {
                $options[CURLOPT_CUSTOMREQUEST] = 'HEAD';
                $options[CURLOPT_NOBODY] = true;
            }
        } else {
            if ($method == 'POST') {
                $options[CURLOPT_POST] = true;
            } elseif (static::$method_emulate) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_HTTPHEADER][] = 'X-HTTP-METHOD-OVERRIDE: '. $method;
                $options[CURLOPT_POSTFIELDS] = $params;
            } else {
                $options[CURLOPT_CUSTOMREQUEST] = $method;
            }

            if ($params)
                $options[CURLOPT_POSTFIELDS] = $params;
        }

        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_HEADER] = true;

        $result = $this->execute($url, $options);

        $message = array();
        $message['info'] = $this->getInfo();

        $header_size = $message['info']['header_size'];
        $message['header'] = preg_split('/\r\n/', substr($result, 0, $header_size), 0, PREG_SPLIT_NO_EMPTY);
        $message['body'] = substr($result, $header_size);

        return $message;
    }
}
