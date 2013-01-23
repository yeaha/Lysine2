<?php
namespace Model;

class User extends \Lysine\DataMapper\DBData {
    static protected $current;
    static protected $remember_ttl = 604800; // 3600 * 24 * 7
    static protected $storage = 'db';
    static protected $collection = 'rbac.users';
    static protected $props_meta = array(
        'user_id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
        'email' => array('type' => 'string', 'refuse_update' => true),
        'passwd' => array('type' => 'string', 'strict' => true),
        'create_time' => array('type' => 'datetime', 'refuse_update' => true, 'default' => \Lysine\DataMapper\Data::CURRENT_DATETIME),
    );

    // 角色信息
    protected $roles;

    // 获得角色列表
    public function getRoles() {
        if ($this->isFresh())
            return array(ROLE_ANONYMOUS);

        if ($this->roles !== null)
            return $this->roles;

        $select = \Model\User\Role::select()
                    ->where('user_id = ?', $this->id())
                    ->where('expire_time is null OR expire_time > now()');

        $roles = $select->setCols('role')->execute()->getCols();

        return $this->roles = ($roles ?: array());
    }

    // 是否具有指定角色
    public function hasRole($roles) {
        if (!is_array($roles))
            $roles = array($roles);

        return (bool)array_intersect($roles, $this->getRoles());
    }

    // 检查密码是否正确
    public function authPasswd($passwd) {
        return $this->passwd
            && $this->passwd == $this->encodePasswd($passwd);
    }

    //////////////////// protected methods ////////////////////
    // 把密码转换为hash字符串
    protected function encodePasswd($passwd) {
        return md5($passwd .'@'. $this->create_time);
    }

    protected function formatProp($val, array $prop_meta) {
        $val = parent::formatProp($val, $prop_meta);

        if ($prop_meta['name'] == 'passwd') {
            $val = $this->encodePasswd($val);
        } elseif ($prop_meta['name'] == 'email') {
            $val = strtolower($val);
        }

        return $val;
    }

    //////////////////// static methods ////////////////////
    // 登录
    static public function login($email, $passwd, $remember = false) {
        if (!$email || !$passwd)
            return false;

        if (!$user = static::select()->where('email = ?', strtolower($email))->getOne())
            return false;

        if (!$user->authPasswd($passwd))
            return false;

        return static::setCurrent($user, $remember);
    }

    static public function logout() {
        static::getLoginContext()->clear();
        static::$current = new static;
    }

    // 设置当前用户
    static public function setCurrent(User $user, $remember = false) {
        $context = static::getLoginContext();

        $expire = time() + static::$remember_ttl;
        if ($remember)
            $context->setConfig('expire_at', $expire);

        $context->set('id', $user->id());
        $context->set('expire', $expire);

        return static::$current = $user;
    }

    // 获得当前用户
    static public function current() {
        if (static::$current)
            return static::$current;

        $context = static::getLoginContext();

        do {
            if (!$id = $context->get('id'))
                break;

            if (!$user = static::find($id))
                break;

            return static::$current = $user;
        } while (false);

        return static::$current = new static;
    }

    // 登录上下文信息
    static public function getLoginContext() {
        $config = array(
            'token' => '_user_',
            'path' => '/',
            'sign_salt' => function($context) {
                do {
                    if (!isset($context['id']) || !$context['id'])
                        break;

                    if (!$user = \Model\User::find($context['id']))
                        break;

                    // 以用户密码作为salt
                    return $user->passwd;
                } while (false);

                // 如果用户不存在，就用固定的随机字符串
                return 'fdq0rj32jr0dsjfwf';
            },
        );

        $handler = new \Lysine\CookieContextHandler($config);
        $expire = (int)$handler->get('expire');

        if ($expire && $expire < time())
            $handler->clear();

        return $handler;
    }

    // 去掉注释就可以使用redis缓存user数据
    //static public function getMapper() {
    //    return UserMapper::factory(get_called_class());
    //}
}

class UserMapper extends \Lysine\DataMapper\CacheDBMapper {
    static protected $ttl = 3600;

    protected function getCacheService() {
        return service('redis');
    }

    protected function getCacheKey($id) {
        return 'user:'. $id;
    }

    protected function getCache($id) {
        $cache = $this->getCacheService();
        $key = $this->getCacheKey($id);

        return $cache->hGetAll($key);
    }

    protected function deleteCache($id) {
        $cache = $this->getCacheService();
        $key = $this->getCacheKey($id);

        return $cache->delete($key);
    }

    protected function saveCache($id, array $record) {
        $cache = $this->getCacheService();
        $key = $this->getCacheKey($id);

        return $cache->multi()
                     ->hMSet($key, $record)
                     ->setTimeout($key, static::$ttl)
                     ->exec();
    }
}
