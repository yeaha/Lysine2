<?php
namespace Test\Mock\Context;

class RedisHandler extends \Lysine\RedisContextHandler {
    public function isDirty() {
        return $this->dirty;
    }

    public function getTimeout() {
        $redis = $this->getService();
        $token = $this->getToken();

        return $redis->ttl($token);
    }
}
