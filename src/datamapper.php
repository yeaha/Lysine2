<?php
namespace Lysine\DataMapper;

use Lysine\Service\IService;

abstract class Data {
    use \Lysine\Traits\Event;

    const AFTER_DELETE_EVENT = 'AFTER DELETE EVENT';
    const AFTER_INSERT_EVENT = 'AFTER INSERT EVENT';
    const AFTER_SAVE_EVENT = 'AFTER SAVE EVENT';
    const AFTER_UPDATE_EVENT = 'AFTER UPDATE EVENT';
    const BEFORE_DELETE_EVENT = 'BEFORE DELETE EVENT';
    const BEFORE_INSERT_EVENT = 'BEFORE INSERT EVENT';
    const BEFORE_SAVE_EVENT = 'BEFORE SAVE EVENT';
    const BEFORE_UPDATE_EVENT = 'BEFORE UPDATE EVENT';

    static protected $storage;
    static protected $collection;
    static protected $props_meta = array();
    static protected $readonly = false;

    protected $is_fresh = true;
    protected $props = array();
    protected $dirty_props = array();

    public function __construct(array $props = array(), $is_fresh = true) {
        // 使用较严格的__set()方法赋值
        foreach ($props as $prop => $val)
            $this->$prop = $val;

        if (!$this->is_fresh = $is_fresh) {
            $this->dirty_props = array();
        } else {
            // 给新对象的属性设置默认值
            foreach ($this->getPropMeta() as $prop => $prop_meta) {
                if (isset($this->props[$prop]) || $prop_meta['allow_null'])
                    continue;

                $default = $this->getDefaultValue($prop_meta);
                if ($default !== null)
                    $this->changeProp($prop, $default);
            }
        }
    }

    public function __get($prop) {
        return $this->getProp($prop);
    }

    public function __set($prop, $val) {
        $this->setProp($prop, $val, true);
    }

    public function __isset($prop) {
        return isset($this->props[$prop]);
    }

    // 此方法是提供给Mapper赋值的快捷方法
    // 除Mapper外都不该调用此方法赋值
    public function __merge(array $props) {
        $this->props = array_merge($this->props, $props);
        $this->is_fresh = false;
        $this->dirty_props = array();

        return $this;
    }

    public function __triggerEvent($event) {
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

        if (!isset($callback[$event]))
            return false;

        $fn = $callback[$event];
        $this->$fn();

        $this->fireEvent($event, array($this));
    }

    // 如果主键是多个字段，返回数组
    // return array(
    //     prop => val,
    //     ...
    // )
    // 否则直接返回值
    public function id() {
        $props = array_keys(static::getMapper()->getMeta()->getPrimaryKey());

        $id = array();
        foreach ($props as $prop)
            $id[$prop] = $this->getProp($prop);

        return (count($id) > 1) ? $id : array_shift($id);
    }

    public function hasProp($prop) {
        return (bool)$this->getPropMeta($prop);
    }

    public function setProps(array $props) {
        foreach ($props as $prop => $val)
            $this->setProp($prop, $val, false);
        return $this;
    }

    public function isFresh() {
        return $this->is_fresh;
    }

    public function isDirty() {
        return (bool)$this->dirty_props;
    }

    public function isReadonly() {
        return static::$readonly;
    }

    public function toArray($only_dirty = false) {
        if (!$only_dirty)
            return $this->props;

        $props = array();
        foreach (array_keys($this->dirty_props) as $prop)
            $props[$prop] = $this->props[$prop];

        return $props;
    }

    public function save() {
        return static::getMapper()->save($this);
    }

    public function destroy() {
        return static::getMapper()->destroy($this);
    }

    public function refresh() {
        return $this->isFresh()
             ? $this
             : static::getMapper()->refresh($this);
    }

    //////////////////// protected method ////////////////////
    protected function getProp($prop, array $prop_meta = null) {
        $prop_meta = $prop_meta ?: $this->getPropMeta($prop);
        if (!$prop_meta)
            throw new UndefinedPropertyError(get_class() .": Undefined property {$prop}");

        return isset($this->props[$prop])
             ? $this->props[$prop]
             : $this->getDefaultValue($prop_meta);
    }

    protected function setProp($prop, $val, $strict) {
        if (!$prop_meta = $this->getPropMeta($prop)) {
            if (!$strict) return false;
            throw new UndefinedPropertyError(get_class() .": Undefined property {$prop}");
        }

        if (!$strict && $prop_meta['strict'])
            return false;

        if (!$this->is_fresh && $prop_meta['refuse_update']) {
            if (!$strict) return false;
            throw new RefuseUpdateError(get_class() .": Property {$prop} refuse update");
        }

        if (!$prop_meta['allow_null'] && $val === null)
            throw new NullNotAllowedError(get_class() .": Property {$prop} not allow null");

        if ($prop_meta['pattern'] && !preg_match($prop_meta['pattern'], $val))
            throw new UnexpectedValueError(get_class() .": Property {$prop} mismatching pattern {$prop_meta['pattern']}");

        $val = $this->formatProp($val, $prop_meta);

        if (!array_key_exists($prop, $this->props) || $val !== $this->props[$prop])
            $this->changeProp($prop, $val);

        return true;
    }

    protected function getDefaultValue(array $prop_meta) {
        return HelperManager::getInstance()
                    ->getHelper($prop_meta['type'])
                    ->getDefaultValue($prop_meta);
    }

    protected function getPropMeta($prop = null) {
        return static::getMapper()->getMeta()->getPropMeta($prop);
    }

    protected function formatProp($val, array $prop_meta) {
        return HelperManager::getInstance()
                    ->getHelper($prop_meta['type'])
                    ->normalize($val, $prop_meta);
    }

    final private function changeProp($prop, $val) {
        $this->props[$prop] = $val;
        $this->dirty_props[$prop] = 1;
    }

    // {{{ 内置事件响应方法
    protected function __before_save() {}
    protected function __after_save() {}

    protected function __before_insert() {}
    protected function __after_insert() {}

    protected function __before_update() {}
    protected function __after_update() {}

    protected function __before_delete() {}
    protected function __after_delete() {}
    // }}}

    //////////////////// static method ////////////////////
    static public function find($id) {
        return static::getMapper()->find($id);
    }

    static public function getMapper() {
        return Mapper::factory( get_called_class() );
    }

    static public function getMeta() {
        $meta = array(
            'storage' => static::$storage,
            'collection' => static::$collection,
            'props' => static::$props_meta,
        );

        $called_class = get_called_class();
        if ($called_class == __CLASS__)
            return $meta;

        $parent_class = get_parent_class($called_class);
        $parent_meta = $parent_class::getMeta();
        $meta['props'] = array_merge($parent_meta['props'], $meta['props']);

        return $meta;
    }
}

abstract class Mapper {
    static private $instance = array();
    protected $class;

    abstract protected function doFind($id, \Lysine\Service\IService $storage = null, $collection = null);
    abstract protected function doInsert(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $storage = null, $collection = null);
    abstract protected function doUpdate(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $storage = null, $collection = null);
    abstract protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $storage = null, $collection = null);

    protected function __construct($class) {
        $this->class = $class;
    }

    public function getMeta() {
        return Meta::factory($this->class);
    }

    public function getStorage() {
        return \Lysine\Service\Manager::getInstance()->get(
            $this->getMeta()->getStorage()
        );
    }

    public function getCollection() {
        return $this->getMeta()->getCollection();
    }

    public function find($id, $refresh = false) {
        $registry = Registry::getInstance();
        $data = $registry->get($this->class, $id);

        if ($data && !$refresh)
            return $data;

        if (!$record = $this->doFind($id))
            return false;

        $data = $this->package($record, $data ?: null);

        // 假设直接使用id通过find()查询的对象是关注度比较高的数据
        // 所以把结果对象在此运行期内缓存在对象注册表内
        $registry->set($data);

        return $data;
    }

    public function save(Data $data) {
        if ($data->isReadonly())
            throw new RuntimeError("{$this->class} is readonly");

        $is_fresh = $data->isFresh();

        if (!$is_fresh && !$data->isDirty())
            return true;

        $data->__triggerEvent(Data::BEFORE_SAVE_EVENT);

        $result = $is_fresh
                ? $this->insert($data)
                : $this->update($data);

        if ($result)
            $data->__triggerEvent(Data::AFTER_SAVE_EVENT);

        return $result;
    }

    public function destroy(Data $data) {
        if ($data->isReadonly())
            throw new RuntimeError("{$this->class} is readonly");

        if ($data->isFresh()) return true;

        $data->__triggerEvent(Data::BEFORE_DELETE_EVENT);
        if (!$this->doDelete($data))
            return false;
        $data->__triggerEvent(Data::AFTER_DELETE_EVENT);

        Registry::getInstance()->remove($this->class, $data->id());

        return true;
    }

    public function refresh(Data $data) {
        return $this->find($data->id(), true);
    }

    public function package(array $record, Data $data = null) {
        if (!$data)
            $data = new $this->class;

        $props = $this->recordToProps($record);
        $data->__merge($props);

        return $data;
    }

    protected function insert(Data $data) {
        $data->__triggerEvent(Data::BEFORE_INSERT_EVENT);

        $this->inspectData($data);

        if (!$id = $this->doInsert($data))
            return false;

        $this->package($id, $data);

        $data->__triggerEvent(Data::AFTER_INSERT_EVENT);

        return true;
    }

    protected function update(Data $data) {
        $data->__triggerEvent(Data::BEFORE_UPDATE_EVENT);

        $this->inspectData($data);

        if (!$this->doUpdate($data))
            return false;

        // 通过调用package方法，清除$data的dirty_props属性记录
        $this->package(array(), $data);
        $data->__triggerEvent(Data::AFTER_UPDATE_EVENT);

        return true;
    }

    protected function inspectData(Data $data) {
        // 如果是新对象，就要检查所有的属性
        // 否则就只检查修改过的属性
        $props_meta = $this->getMeta()->getPropMeta();
        if ($data->isFresh()) {
            $props_data = $data->toArray();
            $props = array_keys($props_meta);
        } else {
            $props_data = $data->toArray(true);
            $props = array_keys($props_data);
        }

        foreach ($props as $prop) {
            $prop_meta = $props_meta[$prop];

            do {
                if ($prop_meta['allow_null'])
                    break;

                if (isset($props_data[$prop]))
                    break;

                throw new NullNotAllowedError($this->class .": Property {$prop} not allow null");
            } while (false);
        }

        return true;
    }

    // 把属性值转换为存储记录
    protected function propsToRecord(array $props) {
        $hm = HelperManager::getInstance();

        foreach ($this->getMeta()->getPropMeta() as $prop => $meta) {
            if (isset($props[$prop]))
                $props[$prop] = $hm->getHelper($meta['type'])->store($props[$prop], $meta);
        }

        return $props;
    }

    // 把存储记录转换为属性值
    protected function recordToProps(array $record) {
        $hm = HelperManager::getInstance();

        foreach ($this->getMeta()->getPropMeta() as $prop => $meta) {
            if (isset($record[$prop]))
                $record[$prop] = $hm->getHelper($meta['type'])->restore($record[$prop], $meta);
        }

        return $record;
    }

    static public function factory($class) {
        if (!isset(self::$instance[$class]))
            self::$instance[$class] = new static($class);
        return self::$instance[$class];
    }
}

class Meta {
    static protected $instance = array();

    static private $default_prop_meta = array(
        'name' => NULL,
        'type' => NULL,
        'primary_key' => FALSE,
        'auto_increase' => FALSE,
        'refuse_update' => FALSE,
        'allow_null' => FALSE,
        'default' => NULL,
        'pattern' => NULL,
        'strict' => FALSE,
    );

    private $class;
    private $storage;
    private $collection;
    private $primary_key = array();
    private $props_meta;

    private function __construct($class) {
        $meta = $class::getMeta();

        $this->class = $class;
        $this->storage = $meta['storage'];
        $this->collection = $meta['collection'];

        foreach ($meta['props'] as $prop => $prop_meta) {
            $prop_meta = array_merge(self::$default_prop_meta, $prop_meta);

            $prop_meta['name'] = $prop;

            if ($prop_meta['primary_key']) {
                $this->primary_key[] = $prop;

                $prop_meta['refuse_update'] = true;
                $prop_meta['allow_null'] = false;
            }

            if ($prop_meta['auto_increase'])
                $prop_meta['allow_null'] = true;

            $meta['props'][$prop] = $prop_meta;
        }

        if (!$this->primary_key)
            throw new RuntimeError("{$class}: Undefined primary key");

        $this->props_meta = $meta['props'];
    }

    public function getStorage() {
        if (!$this->storage)
            throw new RuntimeError("{$this->class}: Undefined storage service");
        return $this->storage;
    }

    public function getCollection() {
        if (!$this->collection)
            throw new RuntimeError("{$this->class}: Undefined collection");
        return $this->collection;
    }

    public function getPrimaryKey() {
        $keys = array();
        foreach ($this->primary_key as $prop)
            $keys[$prop] = $this->props_meta[$prop];

        return $keys;
    }

    public function getPropMeta($prop = null) {
        return $prop === null
             ? $this->props_meta
             : (isset($this->props_meta[$prop]) ? $this->props_meta[$prop] : false);
    }

    static public function factory($class) {
        if (!isset(self::$instance[$class]))
            self::$instance[$class] = new static($class);
        return self::$instance[$class];
    }
}

class HelperManager {
    use \Lysine\Traits\Singleton;

    // 默认helper
    protected $default_helper = '\Lysine\DataMapper\Helper\Mixed';

    // helper实例
    // 每个helper class只产生一个实例
    protected $helper = array();

    // 内置的数据类型helper
    protected $type_helper = array(
        'int' => '\Lysine\DataMapper\Helper\Integer',
        'integer' => '\Lysine\DataMapper\Helper\Integer',
        'numeric' => '\Lysine\DataMapper\Helper\Numeric',
        'text' => '\Lysine\DataMapper\Helper\String',
        'string' => '\Lysine\DataMapper\Helper\String',
        'json' => '\Lysine\DataMapper\Helper\Json',
        'datetime' => '\Lysine\DataMapper\Helper\DateTime',
        'pg_hstore' => '\Lysine\DataMapper\Helper\PgsqlHstore',
        'pg_array' => '\Lysine\DataMapper\Helper\PgsqlArray',
    );

    public function getHelper($type) {
        $type = strtolower($type);
        $class = isset($this->type_helper[$type])
               ? $this->type_helper[$type]
               : $this->default_helper;

        $key = strtolower(trim($class, '\\'));
        if (!isset($this->helper[$key]))
            $this->helper[$key] = new $class;

        return $this->helper[$key];
    }

    // 注册新的数据类型
    public function registerType($type, $helper) {
        $type = strtolower($type);

        if (!is_subclass_of($helper, $this->default_helper))
            throw new \Lysine\RuntimeError('Invalid helper class');

        $this->type_helper[$type] = $helper;
        return $this;
    }
}

class Registry {
    use \Lysine\Traits\Singleton;

    private $enabled = true;
    private $members = array();

    public function enable() {
        $this->enabled = true;
    }

    public function disable() {
        $this->enabled = false;
    }

    public function isEnabled() {
        return $this->enabled;
    }

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

    public function get($class, $id) {
        $class = self::normalizeClassName($class);
        if (!$this->isEnabled())
            return false;

        $key = self::key($class, $id);
        return isset($this->members[$key])
             ? $this->members[$key]
             : false;
    }

    public function remove($class, $id) {
        $class = self::normalizeClassName($class);
        if (!$this->isEnabled())
            return false;

        $key = self::key($class, $id);
        unset($this->members[$key]);
    }

    public function clear() {
        $this->members = array();
    }

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

    static private function normalizeClassName($class) {
        return trim(strtolower($class), '\\');
    }
}

//////////////////// database data-mapper implement ////////////////////

class DBData extends Data {
    static public function getMapper() {
        return DBMapper::factory( get_called_class() );
    }

    static public function select() {
        return static::getMapper()->select();
    }
}

class DBMapper extends Mapper {
    public function select(IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();
        $primary_key = $this->getMeta()->getPrimaryKey();

        if (count($primary_key) == 1) {
            $select = new DBSelect($storage, $collection);
        } else {
            $select = new \Lysine\Service\DB\Select($storage, $collection);
        }

        $mapper = $this;
        $select->setProcessor(function($record) use ($mapper) {
            return $record ? $mapper->package($record) : false;
        });

        return $select;
    }

    protected function doFind($id, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        $select = $storage->select($collection);

        list($where, $params) = $this->whereId($storage, $id);
        $select->where($where, $params);

        return $select->limit(1)->execute()->fetch();
    }

    protected function doInsert(Data $data, IService $storage = null, $collection = null) {
        $record = $this->propsToRecord($data->toArray());
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        if (!$storage->insert($collection, $record))
            return false;

        $id = array();
        foreach ($this->getMeta()->getPrimaryKey() as $prop => $prop_meta) {
            $last_id = $prop_meta['auto_increase']
                     ? $storage->lastId($collection, $prop)
                     : $record[$prop];

            if (!$last_id)
                throw new RuntimeError("{$this->class}: Insert record success, but get last-id failed!");

            $id[$prop] = $last_id;
        }

        return $id;
    }

    protected function doUpdate(Data $data, IService $storage = null, $collection = null) {
        $record = $this->propsToRecord($data->toArray(true));
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        list($where, $params) = $this->whereId($storage, $data->id());

        return $storage->update($collection, $record, $where, $params);
    }

    protected function doDelete(Data $data, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        list($where, $params) = $this->whereId($storage, $data->id());

        return $storage->delete($collection, $where, $params);
    }

    protected function whereId(IService $storage, $id) {
        $primary_key = $this->getMeta()->getPrimaryKey();
        $key_count = count($primary_key);

        if ($key_count == 1 && !is_array($id)) {
            $prop = array_keys($primary_key);
            $id = array($prop[0] => $id);
        }

        if (!is_array($id) || count($id) < $key_count)
            throw new RuntimeError("{$this->class}: Illegal id value");

        $where = $params = array();
        foreach (array_keys($primary_key) as $prop) {
            $where[] = $storage->quoteColumn($prop) .' = ?';

            if (!isset($id[$prop]))
                throw new RuntimeError("{$this->class}: Illegal id value");

            $params[] = $id[$prop];
        }
        $where = implode(' AND ', $where);

        return array($where, $params);
    }
}

abstract class CacheDBMapper extends DBMapper {
    abstract protected function getCache($id);
    abstract protected function deleteCache($id);
    abstract protected function saveCache($id, array $record);

    public function refresh(Data $data) {
        $this->deleteCache($data->id());

        parent::refresh($data);
    }

    protected function doFind($id, IService $storage = null, $collection = null) {
        if ($record = $this->getCache($id))
            return $record;

        if (!$record = parent::doFind($id, $storage, $collection))
            return $record;

        // 值为NULL的字段不用缓存
        foreach ($record as $key => $val) {
            if ($val === null)
                unset($record[$key]);
        }

        $this->saveCache($id, $record);

        return $record;
    }

    protected function doUpdate(Data $data, Iservice $storage = null, $collection = null) {
        $id = $data->id();

        if ($result = parent::doUpdate($data, $storage, $collection))
            $this->deleteCache($id);

        return $result;
    }

    protected function doDelete(Data $data, Iservice $storage = null, $collection = null) {
        $id = $data->id();

        if ($result = parent::doDelete($data, $storage, $collection))
            $this->deleteCache($id);

        return $result;
    }
}

class DBSelect extends \Lysine\Service\DB\Select {
    public function get($limit = null) {
        $result = array();
        foreach (parent::get($limit) as $data)
            $result[$data->id()] = $data;

        return $result;
    }
}

namespace Lysine\DataMapper\Helper;

class Mixed {
    // 格式化数据
    public function normalize($data, array $meta) {
        if ($data === '')
            return NULL;

        return $data;
    }

    // 转换为存储格式
    public function store($data, array $meta) {
        return $data;
    }

    // 从存储格式恢复
    public function restore($data, array $meta) {
        return $data;
    }

    // 获取默认值
    public function getDefaultValue(array $meta) {
        return $meta['default'];
    }
}

class Integer extends Mixed {
    public function normalize($data, array $meta) {
        if ($data === NULL || $data === '')
            return NULL;

        return (int)$data;
    }
}

class Numeric extends Mixed {
    public function normalize($data, array $meta) {
        if ($data === NULL || $data === '')
            return NULL;

        return $data * 1;
    }
}

class String extends Mixed {
    public function normalize($data, array $meta) {
        if ($data === NULL || $data === '')
            return NULL;

        return (string)$data;
    }
}

class Json extends Mixed {
    public function store($data, array $meta) {
        return ($data === NULL || $data === array()) ? NULL : json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function restore($data, array $meta) {
        return ($data === NULL) ? NULL : json_decode($data, true);
    }
}

class DateTime extends Mixed {
    public function normalize($data, array $meta) {
        return $this->create($data, $meta);
    }

    public function store($data, array $meta) {
        if ( !($data instanceof \DateTime) )
            return $data;

        $format = isset($meta['format']) ? $meta['format'] : 'c'; // ISO 8601
        return $data->format($format);
    }

    public function restore($data, array $meta) {
        return $this->create($data, $meta);
    }

    public function getDefaultValue(array $meta) {
        return ($meta['default'] === NULL) ? NULL : new \DateTime($meta['default']);
    }

    protected function create($data, array $meta) {
        if ($data === NULL || $data === '')
            return NULL;

        if ($data instanceof \DateTime)
            return $data;

        if (!isset($meta['format']))
            return new \DateTime($data);

        if (!$data = \DateTime::createFromFormat($meta['format'], $data))
            throw new \Exception('Create datetime from format ['.$meta['format'].'] failed!');

        return $data;
    }
}

class PgsqlHstore extends Mixed {
    public function store($data, array $meta) {
        return \Lysine\Service\DB\Adapter\Pgsql::encodeHstore($data);
    }

    public function restore($data, array $meta) {
        return \Lysine\Service\DB\Adapter\Pgsql::decodeHstore($data);
    }
}

class PgsqlArray extends Mixed {
    public function store($data, array $meta) {
        return \Lysine\Service\DB\Adapter\Pgsql::encodeArray($data);
    }

    public function restore($data, array $meta) {
        return \Lysine\Service\DB\Adapter\Pgsql::decodeArray($data);
    }
}
