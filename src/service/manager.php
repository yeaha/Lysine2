<?php

namespace Lysine\Service;

/**
 * 外部服务连接管理器.
 *
 * @example
 * $manager = \Lysine\Service\Manager::getInstace();
 *
 * $manager->importConfig(array(
 *     'foo' => array(...),
 *     'bar' => array(...),
 * ));
 *
 * $foo = $manager->get('foo');
 * $bar = $manager->get('bar');
 *
 * $manager->setDispatcher('baz', function($id) {
 *     return $id % 2 ? 'foo' : 'bar';
 * });
 *
 * $foo = $manager->get('baz', 1);
 * $bar = $manager->get('baz', 2);
 */
class Manager
{
    use \Lysine\Traits\Event;
    use \Lysine\Traits\Singleton;

    const BEFORE_CREATE_EVENT = 'before create service instance';
    const AFTER_CREATE_EVENT = 'after create service instance';

    /**
     * 服务连接对象缓存数组.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * 自定义服务路由函数.
     *
     * @var array
     */
    protected $dispatcher = array();

    /**
     * 服务配置信息.
     *
     * @var array
     */
    protected $config = array();

    /**
     * 导入服务配置信息.
     *
     * @param array $config
     *
     * @return $this
     */
    public function importConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 设置自定义服务路由函数.
     *
     * @param string   $name
     * @param callable $callback
     *
     * @return $this
     */
    public function setDispatcher($name, $callback)
    {
        $this->dispatcher[$name] = $callback;

        return $this;
    }

    /**
     * 根据名字和自定义参数获取服务连接对象
     *
     * @param string   $name
     * @param mixed... $args
     *
     * @return \Lysine\Service\IService
     */
    public function get($name, $args = null)
    {
        if (isset($this->dispatcher[$name])) {
            $callback = $this->dispatcher[$name];

            $args = $args === null
                  ? null
                  : is_array($args) ? $args : array_slice(func_get_args(), 1);

            $dispatcher_name = $name;
            $name = $args === null
                  ? call_user_func($callback)
                  : call_user_func_array($callback, $args);

            if (!$name) {
                throw new \RuntimeException('Service dispatcher ['.$dispatcher_name.'] MUST return a service name');
            }

            if ($name instanceof IService) {
                return $name;
            }
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $config = $this->getConfig($name);

        $this->fireEvent(self::BEFORE_CREATE_EVENT, array($name, $config));

        $class = $config['class'];
        unset($config['class']);
        $service = new $class($config);

        $this->instances[$name] = $service;

        $this->fireEvent(self::AFTER_CREATE_EVENT, array($name, $config, $service));

        return $service;
    }

    /**
     * 获得所有的已连接服务实例.
     *
     * @return array
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * 根据名字获取服务配置信息.
     *
     * @param string $name
     *
     * @return array
     *
     * @throws \Lysine\Service\RuntimeError 当指定名字的服务不存在时
     */
    protected function getConfig($name)
    {
        if (!isset($this->config[$name])) {
            throw new \UnexpectedValueException('Undefined Service: '.$name);
        }

        $config = $this->config[$name];
        if (!isset($config['__IMPORT__'])) {
            return $config;
        }

        $import_config = $this->getConfig($config['__IMPORT__']);

        $config = array_merge($import_config, $config);
        unset($config['__IMPORT__']);

        return $config;
    }
}

/**
 * 外部服务连接对象接口
 * 确保这些对象的构造函数必须使用数组形式
 * 这样管理器就能够以一致的方式来初始化它们.
 */
interface IService
{
    public function __construct(array $config = array());

    public function disconnect();
}
