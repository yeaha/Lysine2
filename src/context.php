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
// $handler = ContextHandler::factory('session', $config);
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
        unset($_SESSION[$token]);
    }
}

// 上下文信息将以明文访问保存在cookie内
// 不要存放敏感信息（如密码等）
// 数据将会附带数字签名，防止客户端伪造
//
// $config = array(
//     'token' => (string),         // 必须，上下文存储唯一标识
//     'salt' => (string),          // 必须，用于计算数字签名的随机字符串
//     'salt_func' => (callback),   // 可选，获取salt字符串自定义方法，设置了salt_func就可以不设置salt
//     'domain' => (string),        // 可选，cookie 域名，默认：null
//     'path' => (string),          // 可选，cookie 路径，默认：/
//     'ttl' => (integer),          // 可选，生存期，单位：秒，默认：0
//     'bind_ip' => (bool),         // 可选，是否绑定到IP，默认：false
//     'zip' => (bool),             // 可选，是否将数据压缩保存，默认：false
// );
// $handler = ContextHandler::factory('cookie', $config);
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

    // 从cookie恢复上下文信息
    protected function restore() {
        if ($this->data !== null)
            return $this->data;

        if (($data = cookie($this->getToken())) && $this->getConfig('zip')) {
            // 压缩文本最前面有'_'
            // 否则在运行期间切换压缩配置时，未压缩文本会导致解压报错
            $data = (substr($data, 0, 1) == '_')
                  ? gzuncompress(substr($data, 1))
                  : $data;
        }

        return $this->data = $this->decode($data);
    }

    // 保存到cookie
    protected function save() {
        $data = $this->encode($this->data) ?: null;

        if ($data && $this->getConfig('zip'))
            $data = '_'.gzcompress($data, 9);

        $token = $this->getToken();
        $expire = ($ttl = (int)$this->getConfig('ttl')) ? (time() + $ttl) : 0;
        $path = $this->getConfig('path') ?: '/';
        $domain = $this->getConfig('domain');

        resp()->setCookie($token, $data, $expire, $path, $domain);
    }

    // 编码上下文信息
    protected function encode($context) {
        if (!$context) return false;

        $data = array('c' => $context);
        if ($ttl = (int)$this->getConfig('ttl'))
            $data['t'] = time() + $ttl;

        $data = json_encode($data);
        return $data . $this->getSign($data);
    }

    // 解码上下文信息
    protected function decode($string) {
        // sha1() hash length is 40
        $hash_length = 40;

        do {
            if (!$string || strlen($string) <= $hash_length)
                break;

            $hash = substr($string, $hash_length * -1);
            $data = substr($string, 0, strlen($string) - $hash_length);

            if ($this->getSign($data) !== $hash)
                break;

            $data = json_decode($data, true);

            if ($this->getConfig('ttl')) {
                if (!isset($data['t']) || $data['t'] <= time())
                    break;
            }

            return isset($data['c']) ? $data['c'] : array();
        } while (false);

        return array();
    }

    // 生成数字签名
    // $string = json_encode(array(
    //     'c' => (array),      // 上下文数据
    //     't' => (integer),    // 过期时间
    // ));
    protected function getSign($string) {
        $salt = $this->getSalt($string);
        $data = array($string, $salt);

        if ($this->getConfig('bind_ip')) {
            $ip = req()->ip();
            $data[] = substr($ip, 0, strrpos($ip, '.')).'.0';    // 192.168.1.123 -> 192.168.1.0
        }

        return sha1(implode(',', $data));
    }

    protected function getSalt($string) {
        if ($this->salt)
            return $this->salt;

        // salt function可以实现运行期间动态获取salt字符串
        // 例如，把用户id保存在上下文中，以用户密码作为salt
        $salt = ($salt_func = $this->getConfig('salt_func'))
              ? call_user_func($salt_func, $string)
              : $this->getConfig('salt');

        if (!$salt)
            throw new RuntimeError('Require context encrypt salt string');

        return $this->salt = $salt;
    }
}

// $config = array(
//     'token' => (string),     // 必须，上下文存储唯一标识
//     'service' => (string),   // 必须，用于存储的redis服务名
//     'ttl' => (integer),      // 可选，生存期，单位：秒，默认：300
// );
// $handler = ContextHandler::factory('redis', $config);
class RedisContextHandler extends ContextHandler {
    public function set($key, $val) {
        $redis = $this->getService();
        $token = $this->getToken();

        if ($ttl = $this->getConfig('ttl')) {
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

        if ($ttl = $this->getConfig('ttl')) {
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
