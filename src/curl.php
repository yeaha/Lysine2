<?php
namespace Lysine;

class Curl {
    protected $handler;
    protected $options = array();

    public function __construct() {
        if (!extension_loaded('curl'))
            throw new \RuntimeException('Require curl extension');
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
            throw new \RuntimeException('Curl Error: '. curl_error($handler), curl_errno($handler));

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
