<?php
namespace Lysine\DataMapper;

use Lysine\Service\IService;

abstract class Data {
    const AFTER_DELETE_EVENT = 'AFTER DELETE EVENT';
    const AFTER_INSERT_EVENT = 'AFTER INSERT EVENT';
    const AFTER_SAVE_EVENT = 'AFTER SAVE EVENT';
    const AFTER_UPDATE_EVENT = 'AFTER UPDATE EVENT';
    const BEFORE_DELETE_EVENT = 'BEFORE DELETE EVENT';
    const BEFORE_INSERT_EVENT = 'BEFORE INSERT EVENT';
    const BEFORE_SAVE_EVENT = 'BEFORE SAVE EVENT';
    const BEFORE_UPDATE_EVENT = 'BEFORE UPDATE EVENT';

    const TYPE_INT = 'integer';
    const TYPE_FLOAT = 'float';
    const TYPE_STRING = 'string';
    const TYPE_ARRAY = 'array';

    static protected $storage;
    static protected $collection;
    static protected $props_meta = array();
    static protected $readonly = false;

    protected $is_fresh = true;
    protected $props = array();
    protected $dirty_props = array();

    public function __construct(array $props = array(), $is_fresh = true) {
        $this->__merge($props, $is_fresh);
    }

    public function __get($prop) {
        return $this->getProp($prop);
    }

    public function __set($prop, $val) {
        $this->setProp($prop, $val);
    }

    public function __merge(array $props, $is_fresh) {
        foreach ($props as $prop => $val)
            $this->$prop = $val;

        $this->is_fresh = $is_fresh;
        if (!$is_fresh)
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

        \Lysine\Event::instance()->fire($this, $event, array($this));

        $fn = $callback[$event];
        $this->$fn();
    }

    public function id() {
        $prop = static::getMapper()->getMeta()->getPrimaryKey(true);
        return $this->getProp($prop);
    }

    public function hasProp($prop) {
        return (bool)static::getMapper()->getMeta()->getPropMeta($prop);
    }

    public function setProps(array $props) {
        $meta = static::getMapper()->getMeta();

        foreach ($props as $prop => $val) {
            if (!$prop_meta = $meta->getPropMeta($prop))
                continue;

            if ($prop_meta['strict'])
                continue;

            $this->setProp($prop, $val, $prop_meta);
        }
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
        $prop_meta = $prop_meta ?: static::getMapper()->getMeta()->getPropMeta($prop);
        if (!$prop_meta)
            throw new UndefinedPropertyError(get_class() .": Undefined property {$prop}");

        return isset($this->props[$prop])
             ? $this->props[$prop]
             : $prop_meta['default'];
    }

    protected function setProp($prop, $val, array $prop_meta = null) {
        $prop_meta = $prop_meta ?: static::getMapper()->getMeta()->getPropMeta($prop);
        if (!$prop_meta)
            throw new UndefinedPropertyError(get_class() .": Undefined property {$prop}");

        if (!$this->is_fresh && ($prop_meta['refuse_update'] || $prop_meta['primary_key']))
            throw new RuntimeError(get_class() .": Property {$prop} refuse update");

        if (!$prop_meta['allow_null'] && $val === null)
            throw new NullNotAllowedError(get_class() .": Property {$prop} not allow null");

        if ($prop_meta['pattern'] && !preg_match($prop_meta['pattern'], $val))
            throw new UnexpectedValueError(get_class() .": Property {$prop} mismatching pattern {$prop_meta['pattern']}");

        $val = $this->formatProp($val, $prop_meta);

        if ($val !== $this->getProp($prop, $prop_meta)) {
            $this->props[$prop] = $val;
            $this->dirty_props[$prop] = 1;
        }

        return true;
    }

    protected function formatProp($val, array $prop_meta) {
        if ($val === null || $val === '')
            return null;

        switch ($prop_meta['type']) {
            case Data::TYPE_INT:
                return (int)$val;
            case Data::TYPE_FLOAT:
                return (float)$val;
            case Data::TYPE_STRING:
                return (string)$val;
        }

        return $val;
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

    abstract protected function doFind($id, IService $storage = null, $collection = null);
    abstract protected function doInsert(Data $data, IService $storage = null, $collection = null);
    abstract protected function doUpdate(Data $data, IService $storage = null, $collection = null);
    abstract protected function doDelete(Data $data, IService $storage = null, $collection = null);

    protected function __construct($class) {
        $this->class = $class;
    }

    public function getMeta() {
        return Meta::factory($this->class);
    }

    public function getStorage() {
        return \Lysine\Service\Manager::instance()->get(
            $this->getMeta()->getStorage()
        );
    }

    public function getCollection() {
        return $this->getMeta()->getCollection();
    }

    public function find($id, $refresh = false) {
        $data = Registry::get($this->class, $id);

        if ($data && !$refresh)
            return $data;

        if (!$record = $this->doFind($id))
            return false;

        return $this->package($record, $data ?: null);
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

        Registry::remove($this->class, $data->id());

        return true;
    }

    public function refresh(Data $data) {
        return $this->find($data->id(), true);
    }

    public function package(array $record, Data $data = null) {
        if (!$data)
            $data = new $this->class;

        $props = $this->recordToProps($record);
        $data->__merge($props, false);

        Registry::set($data);
        return $data;
    }

    protected function insert(Data $data) {
        $data->__triggerEvent(Data::BEFORE_INSERT_EVENT);

        $this->inspectData($data);

        if (!$id = $this->doInsert($data))
            return false;

        $field = $this->getMeta()->getPrimaryKey();
        $record = array($field => $id);
        $this->package($record, $data);

        $data->__triggerEvent(Data::AFTER_INSERT_EVENT);

        return true;
    }

    protected function update(Data $data) {
        $data->__triggerEvent(Data::BEFORE_UPDATE_EVENT);

        $this->inspectData($data);

        if (!$this->doUpdate($data))
            return false;

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

                if ($prop_meta['default'] !== null)
                    break;

                if ($prop_meta['primary_key'] && $prop_meta['auto_increase'])
                    break;

                throw new NullNotAllowedError($this->class .": Property {$prop} not allow null");
            } while (false);
        }

        return true;
    }

    protected function recordToProps(array $record) {
        $props = array();
        foreach ($this->getMeta()->getPropOfField() as $field => $prop) {
            if (isset($record[$field]))
                $props[$prop] = $record[$field];
        }

        return $props;
    }

    protected function propsToRecord(array $props) {
        $record = array();
        $field_of_prop = $this->getMeta()->getFieldOfProp();

        foreach ($props as $prop => $val)
            $record[ $field_of_prop[$prop] ] = $val;

        return $record;
    }

    static public function factory($class) {
        if (!isset(self::$instance[$class]))
            self::$instance[$class] = new static($class);
        return self::$instance[$class];
    }
}

class Meta {
    static private $instance = array();
    static private $default_prop_meta = array(
        'name' => NULL,
        'field' => NULL,
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
    private $primary_key;
    private $props_meta;
    private $prop_field = array();
    private $field_prop = array();

    private function __construct($class) {
        $meta = $class::getMeta();

        $this->class = $class;
        $this->storage = $meta['storage'];
        $this->collection = $meta['collection'];

        foreach ($meta['props'] as $prop => &$prop_meta) {
            $prop_meta = array_merge(self::$default_prop_meta, $prop_meta);

            $prop_meta['name'] = $prop;

            if (!$prop_meta['field'])
                $prop_meta['field'] = $prop;

            if ($prop_meta['primary_key'])
                $this->primary_key = $prop_meta['field'];

            $this->prop_field[$prop] = $prop_meta['field'];
        }

        if (!$this->primary_key)
            throw new RuntimeError("{$class}: Undefined primary key");

        $this->props_meta = $meta['props'];
        $this->field_prop = array_flip($this->prop_field);
    }

    public function getStorage() {
        return $this->storage;
    }

    public function getCollection() {
        if (!$this->collection)
            throw new RuntimeError("{$this->class}: Undefined collection");
        return $this->collection;
    }

    public function getPrimaryKey($as_prop = false) {
        $field = $this->primary_key;
        return $as_prop ? $this->getPropOfField($field) : $field;
    }

    public function getPropMeta($prop = null) {
        return $prop === null ? $this->props_meta : $this->props_meta[$prop];
    }

    public function getFieldOfProp($prop = null) {
        return $prop === null ? $this->prop_field : $this->prop_field[$prop];
    }

    public function getPropOfField($field = null) {
        return $field === null ? $this->field_prop : $this->field_prop[$field];
    }

    static public function factory($class) {
        if (!isset(self::$instance[$class]))
            self::$instance[$class] = new static($class);
        return self::$instance[$class];
    }
}

class Registry {
    static private $members = array();

    static public function set(Data $data) {
        if (!$id = $data->id())
            return false;

        $class = get_class($data);
        $key = $class.'@'.$id;

        self::$members[$key] = $data;
    }

    static public function get($class, $id) {
        $key = $class.'@'.$id;

        return isset(self::$members[$key])
             ? self::$members[$key]
             : false;
    }

    static public function remove($class, $id) {
        $key = $class.'@'.$id;
        unset(self::$members[$key]);
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

        $select = new DBSelect($storage, $collection);

        $mapper = $this;
        $select->setProcessor(function($record) use ($mapper) {
            return $record ? $mapper->package($record) : false;
        });

        return $select;
    }

    protected function doFind($id, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();
        $primary_key = $storage->qcol($this->getMeta()->getPrimaryKey());

        return $storage->select($collection)
                       ->where("{$primary_key} = ?", $id)
                       ->limit(1)
                       ->execute()
                       ->fetch();
    }

    protected function doInsert(Data $data, IService $storage = null, $collection = null) {
        $record = $this->propsToRecord($data->toArray());
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        if (!$storage->insert($collection, $record))
            return false;

        $primary_key = $this->getMeta()->getPrimaryKey();
        if (isset($record[$primary_key]))
            return $record[$primary_key];

        if (!$last_id = $storage->lastId($collection, $primary_key))
            throw new RuntimeError("{$this->class}: Insert record success, but get last-id failed!");

        return $last_id;
    }

    protected function doUpdate(Data $data, IService $storage = null, $collection = null) {
        $record = $this->propsToRecord($data->toArray(true));
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();
        $primary_key = $this->getMeta()->getPrimaryKey();

        unset($record[$primary_key]);
        $primary_key = $storage->qcol($primary_key);

        return $storage->update($collection, $record, "{$primary_key} = ?", $data->id());
    }

    protected function doDelete(Data $data, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();
        $primary_key = $storage->qcol($this->getMeta()->getPrimaryKey());

        return $storage->delete($collection, "{$primary_key} = ?", $data->id());
    }
}

abstract class CacheDBMapper extends DBMapper {
    abstract protected function getCache($id);
    abstract protected function deleteCache($id);
    abstract protected function saveCache($id, array $record);

    protected function doFind($id, IService $storage = null, $collection = null) {
        if (!$record = $this->getCache($id)) {
            if ($record = parent::doFind($id, $storage, $collection))
                $this->saveCache($id, $record);
        }
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
