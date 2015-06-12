<?php
namespace Lysine\DataMapper;

/**
 * 存储服务CRUD细节
 * 处理存储集合内的数据和Data实例之间的映射关系
 */
abstract class Mapper {
    use \Lysine\Traits\Event;

    const AFTER_DELETE_EVENT = 'after:delete';
    const AFTER_INSERT_EVENT = 'after:insert';
    const AFTER_SAVE_EVENT = 'after:save';
    const AFTER_UPDATE_EVENT = 'after:update';
    const BEFORE_DELETE_EVENT = 'before:delete';
    const BEFORE_INSERT_EVENT = 'before:insert';
    const BEFORE_SAVE_EVENT = 'before:save';
    const BEFORE_UPDATE_EVENT = 'before:update';

    /**
     * Data class名
     * @var string
     */
    protected $class;

    /**
     * 配置，存储服务、存储集合、属性定义等等
     * @var array
     */
    protected $options = array();

    /**
     * 根据主键值返回查询到的单条记录
     *
     * @param string|integer|array $id 主键值
     * @param IService [$service] 存储服务连接
     * @param string [$collection] 存储集合名
     * @return array 数据结果
     */
    abstract protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null);

    /**
     * 插入数据到存储服务
     *
     * @param Data $data Data实例
     * @param IService [$service] 存储服务连接
     * @param string [$collection] 存储集合名
     * @return array 新的主键值
     */
    abstract protected function doInsert(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);

    /**
     * 更新数据到存储服务
     *
     * @param Data $data Data实例
     * @param IService [$service] 存储服务连接
     * @param string [$collection] 存储集合名
     * @return boolean
     */
    abstract protected function doUpdate(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);

    /**
     * 从存储服务删除数据
     *
     * @param Data $data Data实例
     * @param IService [$service] 存储服务连接
     * @param string [$collection] 存储集合名
     * @return boolean
     */
    abstract protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);

    /**
     * @param string $class
     */
    public function __construct($class) {
        $this->class = $class;
        $this->options = $this->normalizeOptions($class::getOptions());
    }

    /**
     * 指定的配置是否存在
     *
     * @param string $key
     * @return boolean
     */
    public function hasOption($key) {
        return isset($this->options[$key]);
    }

    /**
     * 获取指定的配置内容
     *
     * @param string $key
     * @return mixed
     * @throws \RuntimeException 指定的配置不存在
     */
    public function getOption($key) {
        if (!isset($this->options[$key])) {
            throw new \RuntimeException('Mapper: undefined option "'.$key.'"');
        }

        return $this->options[$key];
    }

    /**
     * 获取所有的配置内容
     *
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * 获得存储服务连接实例
     *
     * @return IService
     * @throws \RuntimeException Data class没有配置存储服务
     */
    public function getService() {
        $service = $this->getOption('service');

        return \Lysine\Service\Manager::getInstance()->get($service);
    }

    /**
     * 获得存储集合的名字
     * 对于数据库来说，就是表名
     *
     * @return string
     * @throws \RuntimeException 存储集合名未配置
     */
    public function getCollection() {
        return $this->getOption('collection');
    }

    /**
     * 获得主键定义
     *
     * @return
     * array(
     *     (string) => array,  // 主键字段名 => 属性定义
     * )
     */
    public function getPrimaryKey() {
        return $this->getOption('primary_key');
    }

    /**
     * 获得指定属性的定义
     *
     * @param string $key 属性名
     * @return array|false
     */
    public function getAttribute($key) {
        return isset($this->options['attributes'][$key])
             ? $this->options['attributes'][$key]
             : false;
    }

    /**
     * 获得所有的属性定义
     * 默认忽略被标记为“废弃”的属性
     *
     * @param boolean $without_deprecated 不包含废弃属性
     * @return array(
     *     (string) => (array),  // 属性名 => 属性定义
     *     ...
     * )
     */
    public function getAttributes($without_deprecated = true) {
        $attributes = $this->getOption('attributes');

        if ($without_deprecated) {
            foreach ($attributes as $key => $attribute) {
                if ($attribute['deprecated']) {
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    /**
     * 是否定义了指定的属性
     * 如果定义了属性，但被标记为“废弃”，也返回未定义
     *
     * @param string $key 属性名
     * @return boolean
     */
    public function hasAttribute($key) {
        $attribute = $this->getAttribute($key);
        return $attribute ? !$attribute['deprecated'] : false;
    }

    /**
     * Mapper是否只读
     *
     * @return boolean
     */
    public function isReadonly() {
        return $this->getOption('readonly');
    }

    /**
     * 把存储服务内获取的数据，打包成Data实例
     *
     * @param array $record
     * @param Data [$data]
     * @return Data
     */
    public function pack(array $record, Data $data = null) {
        $types = Types::getInstance();
        $values = array();

        foreach ($record as $key => $value) {
            $attribute = $this->getAttribute($key);

            if ($attribute && !$attribute['deprecated']) {
                $values[$key] = $types->get($attribute['type'])->restore($value, $attribute);
            }
        }

        if ($data) {
            $data->__pack($values, false);
        } else {
            $class = $this->class;
            $data = new $class(null, array('fresh' => false));
            $data->__pack($values, true);
        }

        return $data;
    }

    /**
     * 把Data实例内的数据，转换为适用于存储的格式
     *
     * @param Data $data
     * @param array [$options]
     * @return array
     */
    public function unpack(Data $data, array $options = null) {
        $defaults = array('dirty' => false);
        $options = $options ? array_merge($defaults, $options) : $defaults;

        $attributes = $this->getAttributes();

        $record = array();
        foreach ($data->pick(array_keys($attributes)) as $key => $value) {
            if ($options['dirty'] && !$data->isDirty($key)) {
                continue;
            }

            if ($value !== null) {
                $attribute = $attributes[$key];
                $value = Types::factory($attribute['type'])->store($value, $attribute);
            }

            $record[$key] = $value;
        }

        return $record;
    }

    /**
     * 根据指定的主键值生成Data实例
     *
     * @param string|integer|array $id 主键值
     * @param Data [$data]
     * @return Data|false
     */
    public function find($id, Data $data = null) {
        $registry = Registry::getInstance();

        if (!$data) {
            if ($data = $registry->get($this->class, $id)) {
                return $data;
            }
        }

        if (!$record = $this->doFind($id)) {
            return false;
        }

        $data = $this->pack($record, $data ?: null);
        $registry->set($data);

        return $data;
    }

    /**
     * 从存储服务内重新获取数据并刷新Data实例
     *
     * @param Data $data
     * @return Data
     */
    public function refresh(Data $data) {
        if ($data->isFresh()) {
            return $data;
        }

        return $this->find($data->id(), $data);
    }

    /**
     * 保存Data
     *
     * @param Data $data
     * @return boolean
     */
    public function save(Data $data) {
        if ($this->isReadonly()) {
            throw new \RuntimeException($this->class.' is readonly');
        }

        $is_fresh = $data->isFresh();
        if (!$is_fresh && !$data->isDirty()) {
            return true;
        }

        $this->triggerEvent(self::BEFORE_SAVE_EVENT, $data);

        if ($is_fresh) {
            $this->insert($data);
        } else {
            $this->update($data);
        }

        $this->triggerEvent(self::AFTER_SAVE_EVENT, $data);

        return true;
    }

    /**
     * 删除Data
     *
     * @param Data $data
     * @return boolean
     */
    public function destroy(Data $data) {
        if ($this->isReadonly()) {
            throw new \RuntimeException($this->class.' is readonly');
        }

        if ($data->isFresh()) {
            return true;
        }

        $this->triggerEvent(self::BEFORE_DELETE_EVENT, $data);

        if (!$this->doDelete($data)) {
            throw new \Exception($this->class.' destroy failed');
        }

        $this->triggerEvent(self::AFTER_DELETE_EVENT, $data);

        Registry::getInstance()->remove($this->class, $data->id());

        return true;
    }

    /**
     * 把新的Data数据插入到存储集合中
     *
     * @param Data $data
     * @return boolean
     */
    protected function insert(Data $data) {
        $this->triggerEvent(self::BEFORE_INSERT_EVENT, $data);
        $this->validateData($data);

        $id = $this->doInsert($data);

        $this->pack($id, $data);
        $this->triggerEvent(self::AFTER_INSERT_EVENT, $data);

        return true;
    }

    /**
     * 更新Data数据到存储集合内
     *
     * @param Data $data
     * @return boolean
     */
    protected function update(Data $data) {
        $this->triggerEvent(self::BEFORE_UPDATE_EVENT, $data);
        $this->validateData($data);

        $this->doUpdate($data);

        $this->pack(array(), $data);
        $this->triggerEvent(self::AFTER_UPDATE_EVENT, $data);

        return true;
    }

    /**
     * Data属性值有效性检查
     *
     * @param Data $data
     * @return boolean
     * @throws \UnexpectedValueException 不允许为空的属性没有被赋值
     */
    protected function validateData(Data $data) {
        $is_fresh = $data->isFresh();
        $attributes = $this->getAttributes();

        if ($is_fresh) {
            $record = $this->unpack($data);
            $keys = array_keys($attributes);
        } else {
            $record = $this->unpack($data, array('dirty' => true));
            $keys = array_keys($record);
        }

        foreach ($keys as $key) {
            $attribute = $attributes[$key];

            do {
                if ($attribute['allow_null']) {
                    break;
                }

                if ($attribute['auto_generate'] && $is_fresh) {
                    break;
                }

                if (isset($record[$key])) {
                    break;
                }

                throw new \UnexpectedValueException($this->class.' property '.$key.' not allow null');
            } while (false);
        }

        return true;
    }

    /**
     * 触发事件，执行事件钩子方法
     *
     * @param string $event 事件名
     * @param Data $data
     * @return void
     */
    protected function triggerEvent($event, Data $data) {
        $callback = array(
            self::AFTER_DELETE_EVENT => '__after_delete',
            self::AFTER_INSERT_EVENT => '__after_insert',
            self::AFTER_SAVE_EVENT => '__after_save',
            self::AFTER_UPDATE_EVENT => '__after_update',
            self::BEFORE_DELETE_EVENT => '__before_delete',
            self::BEFORE_INSERT_EVENT => '__before_insert',
            self::BEFORE_SAVE_EVENT => '__before_save',
            self::BEFORE_UPDATE_EVENT => '__before_update',
        );

        if (isset($callback[$event])) {
            $fn = $callback[$event];
            $data->$fn();
        }

        $this->fireEvent($event, [$data]);
    }

    /**
     * 格式化从Data class获得的配置信息
     *
     * @param array $options
     * @return array
     */
    protected function normalizeOptions(array $options) {
        $options = array_merge(array(
            'service' => null,
            'collection' => null,
            'attributes' => array(),
            'readonly' => false,
            'strict' => false,
        ), $options);

        $primary_key = array();
        foreach ($options['attributes'] as $key => $attribute) {
            $attribute = Types::normalizeAttribute($attribute);

            if ($attribute['strict'] === null) {
                $attribute['strict'] = $options['strict'];
            }

            if ($attribute['primary_key'] && !$attribute['deprecated']) {
                $primary_key[] = $key;
            }

            $options['attributes'][$key] = $attribute;
        }

        if (!$primary_key) {
            throw new \RuntimeException('Mapper: undefined primary key');
        }

        $options['primary_key'] = $primary_key;

        return $options;
    }

    /**
     * Mapper实例缓存数组
     * @var array
     */
    static private $instance = array();

    /**
     * 获得指定Data class的Mapper实例
     *
     * @param string $class
     * @return Mapper
     */
    final static public function factory($class) {
        if (!isset(self::$instance[$class])) {
            self::$instance[$class] = new static($class);
        }
        return self::$instance[$class];
    }
}

/**
 * Data实例缓存注册表
 * 通过Mapper find获得的Data实例都会被注册到这个缓存内
 * 在其它地方再次调用Mapper find时，就不需要再从存储服务查询，直接从这里返回结果
 * 也保证了在任何地方find，都能拿到同一个Data实例
 */
class Registry {
    use \Lysine\Traits\Singleton;

    /**
     * 是否开启DataMapper的Data注册表功能
     * @var boolean
     */
    private $enabled = true;

    /**
     * 缓存的Data实例
     * @var array
     */
    private $members = array();

    /**
     * 开启缓存
     * @return void
     */
    public function enable() {
        $this->enabled = true;
    }

    /**
     * 关闭缓存
     * @return void
     */
    public function disable() {
        $this->enabled = false;
    }

    /**
     * 缓存是否开启
     * @return boolean
     */
    public function isEnabled() {
        return $this->enabled;
    }

    /**
     * 把Data实例缓存起来
     *
     * @param Data $data
     * @return void
     */
    public function set(Data $data) {
        $class = self::normalizeClassName(get_class($data));
        if (!$this->isEnabled())
            return false;

        if ($data->isFresh())
            return false;

        if (!$id = $data->id())
            return false;

        $key = self::key($class, $id);
        $this->members[$key] = $data;
    }

    /**
     * 根据类名和主键值，获得缓存结果
     *
     * @param string class
     * @param string|integer|array $id
     * @return Data|false
     */
    public function get($class, $id) {
        $class = self::normalizeClassName($class);
        if (!$this->isEnabled())
            return false;

        $key = self::key($class, $id);
        return isset($this->members[$key])
             ? $this->members[$key]
             : false;
    }

    /**
     * 删除缓存结果
     *
     * @param string $class
     * @param mixed $id
     * @return void
     */
    public function remove($class, $id) {
        $class = self::normalizeClassName($class);
        if (!$this->isEnabled())
            return false;

        $key = self::key($class, $id);
        unset($this->members[$key]);
    }

    /**
     * 把所有的缓存都删除掉
     *
     * @return void
     */
    public function clear() {
        $this->members = array();
    }

    /**
     * 生成缓存数组的key
     *
     * @param string $class
     * @param mixed $id
     * @return string
     */
    static private function key($class, $id) {
        $key = '';
        if (is_array($id)) {
            ksort($id);

            foreach ($id as $prop => $val) {
                if ($key) $key .= ';';
                $key .= "{$prop}:{$val}";
            }
        } else {
            $key = $id;
        }

        return $class.'@'.$key;
    }

    /**
     * 格式化类名字符串
     *
     * @param string $class
     * @return string
     */
    static private function normalizeClassName($class) {
        return trim(strtolower($class), '\\');
    }
}

class DBSelect extends \Lysine\Service\DB\Select {
    public function get($limit = null) {
        $result = array();

        foreach (parent::get($limit) as $data) {
            $result[$data->id()] = $data;
        }

        return $result;
    }
}

class DBData extends \Lysine\DataMapper\Data {
    static protected $mapper = '\Lysine\DataMapper\DBMapper';

    static public function select() {
        return static::getMapper()->select();
    }
}

class DBMapper extends \Lysine\DataMapper\Mapper {
    public function select(\Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();
        $primary_key = $this->getPrimaryKey();

        // 只有一个主键，就可以返回以主键为key的数组结果
        if (count($primary_key) === 1) {
            $select = new DBSelect($service, $collection);
        } else {
            $select = new \Lysine\Service\DB\Select($service, $collection);
        }

        $select->setCols(array_keys($this->getAttributes()));

        $mapper = $this;
        $select->setProcessor(function($record) use ($mapper) {
            return $record ? $mapper->pack($record) : false;
        });

        return $select;
    }

    protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();

        $select = $this->select($service, $collection);

        list($where, $params) = $this->whereID($service, $id);
        $select->where($where, $params);

        return $select->limit(1)->execute()->fetch();
    }

    protected function doInsert(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();
        $record = $this->unpack($data);

        if (!$service->insert($collection, $record)) {
            return false;
        }

        $id = array();
        foreach ($this->getPrimaryKey() as $key) {
            if (!isset($record[$key])) {
                if (!$last_id = $service->lastId($collection, $key)) {
                    throw new \Exception("{$this->class}: Insert record success, but get last-id failed!");
                }
                $id[$key] = $last_id;
            }
        }

        return $id;
    }

    protected function doUpdate(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();
        $record = $this->unpack($data, array('dirty' => true));

        list($where, $params) = $this->whereID($service, $data->id());

        return $service->update($collection, $record, $where, $params);
    }

    protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();

        list($where, $params) = $this->whereID($service, $data->id());

        return $service->delete($collection, $where, $params);
    }

    protected function whereID(\Lysine\Service\IService $service, $id) {
        $primary_key = $this->getPrimaryKey();
        $key_count = count($primary_key);

        if ($key_count === 1 && !is_array($id)) {
            $key = $primary_key[0];
            $id = array($key => $id);
        }

        if (!is_array($id)) {
            throw new \Exception("{$this->class}: Illegal id value");
        }

        $where = $params = array();
        foreach ($primary_key as $key) {
            $where[] = $service->quoteIdentifier($key) .' = ?';

            if (!isset($id[$key])) {
                throw new \Exception("{$this->class}: Illegal id value");
            }

            $params[] = $id[$key];
        }
        $where = implode(' AND ', $where);

        return array($where, $params);
    }
}

abstract class CacheDBMapper extends DBMapper {
    abstract protected function getCache($id);
    abstract protected function deleteCache($id);
    abstract protected function saveCache($id, array $record);

    public function __construct($class) {
        parent::__construct($class);

        $delete_cache = function($data) {
            $this->deleteCache($data->id());
        };

        $this->onEvent(static::AFTER_UPDATE_EVENT, $delete_cache);
        $this->onEvent(static::AFTER_DELETE_EVENT, $delete_cache);
    }

    public function refresh(Data $data) {
        $this->deleteCache($data->id());
        return parent::refresh($data);
    }

    protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null) {
        if ($record = $this->getCache($id)) {
            return $record;
        }

        if (!$record = parent::doFind($id, $service, $collection)) {
            return $record;
        }

        // 值为NULL的字段不用缓存
        foreach ($record as $key => $val) {
            if ($val === null) {
                unset($record[$key]);
            }
        }

        $this->saveCache($id, $record);

        return $record;
    }
}
