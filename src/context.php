<?php // README: 会话之间的上下文数据封装

namespace Lysine;

use Lysine\RuntimeError;

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
//     'salt' => (string),          // 必须，用于计算数字签名的随机字符串
//     'salt_func' => (callback),   // 可选，获取salt字符串自定义方法，设置了salt_func就可以不设置salt
//     'encrypt' => array(          // 可选，加密方法配置
//         (string),                //   必须，ciphers name，例如MCRYPT_3DES
//         (string),                //   可选，ciphers mode, 默认MCRYPT_MODE_ECB
//         (integer),               //   可选，random device，默认MCRYPT_RAND
//     ),
//     'domain' => (string),        // 可选，cookie 域名，默认：null
//     'path' => (string),          // 可选，cookie 路径，默认：/
//     'ttl' => (integer),          // 可选，生存期，单位：秒，默认：0
//     'bind_ip' => (bool),         // 可选，是否绑定到IP，默认：false
//     'zip' => (bool),             // 可选，是否将数据压缩保存，默认：false
// );
//
// $handler = ContextHandler::factory('cookie', $config);
// $handler = new CookieContextHandler($config);
class CookieContextHandler extends ContextHandler {
    protected $data;
    protected $salt;

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

    // 保存到cookie
    protected function save() {
        $token = $this->getToken();
        $expire = ($ttl = (int)$this->getConfig('ttl')) ? (time() + $ttl) : 0;
        $path = $this->getConfig('path') ?: '/';
        $domain = $this->getConfig('domain');

        if ($this->data) {
            $data = array('c' => $this->data);
            if ($expire) $data['t'] = $expire;

            $data = $this->encode($data);
        } else {
            $data = '';
        }

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

            if ($this->getConfig('ttl') && (!isset($data['t']) || $data['t'] <= time()))
                break;

            return $this->data = isset($data['c']) ? $data['c'] : array();
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
        $config = $this->getConfig('encrypt');
        $cipher = $config[0];
        $mode = isset($config[1]) ? $config[1] : MCRYPT_MODE_ECB;
        $device = isset($config[2]) ? $config[2] : MCRYPT_RAND;

        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv = mcrypt_create_iv($iv_size, $device);
        $key = $this->getSalt();

        return mcrypt_encrypt($cipher, $key, $string, $mode, $iv);
    }

    // 解密字符串
    protected function decrypt($string) {
        $config = $this->getConfig('encrypt');
        $cipher = $config[0];
        $mode = isset($config[1]) ? $config[1] : MCRYPT_MODE_ECB;
        $device = isset($config[2]) ? $config[2] : MCRYPT_RAND;

        $iv_size = mcrypt_get_iv_size($cipher, $mode);
        $iv = mcrypt_create_iv($iv_size, $device);
        $key = $this->getSalt();

        if ($decrypted = mcrypt_decrypt($cipher, $key, $string, $mode, $iv))
            $decrypted = rtrim($decrypted, "\0");   // remove padding

        return $decrypted;
    }

    // 生成数字签名
    // $string = json_encode(array(
    //     'c' => (array),      // 上下文数据
    //     't' => (integer),    // 过期时间
    // ));
    protected function getSign($string) {
        $salt = $this->getSalt();
        $data = array($string, $salt);

        if ($this->getConfig('bind_ip')) {
            $ip = req()->ip();
            $data[] = long2ip(ip2long($ip) & ip2long('255.255.255.0'));     // 192.168.1.123 -> 192.168.1.0
        }

        return sha1(implode(',', $data), true);
    }

    protected function getSalt() {
        if ($this->salt)
            return $this->salt;

        // salt function可以实现运行期间动态获取salt字符串
        // 例如，把用户id保存在上下文中，以用户密码作为salt
        if ($salt_func = $this->getConfig('salt_func')) {
            $salt = call_user_func($salt_func, $this->data);
        } else {
            $salt = $this->getConfig('salt');
        }

        if (!$salt)
            throw new RuntimeError('Require context encrypt salt string');

        return $this->salt = $salt;
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
