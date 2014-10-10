<?php
namespace Lysine\DataMapper;

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

    protected $class;
    protected $options = array();

    abstract protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null);
    abstract protected function doInsert(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);
    abstract protected function doUpdate(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);
    abstract protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null);

    public function __construct($class) {
        $this->class = $class;
        $this->options = $this->normalizeOptions($class::getOptions());
    }

    public function hasOption($key) {
        return isset($this->options[$key]);
    }

    public function getOption($key) {
        if (!isset($this->options[$key])) {
            throw new \RuntimeException('Mapper: undefined option "'.$key.'"');
        }

        return $this->options[$key];
    }

    public function getOptions() {
        return $this->options;
    }

    public function getService() {
        $service = $this->getOption('service');

        return \Lysine\Service\Manager::getInstance()->get($service);
    }

    public function getCollection() {
        return $this->getOption('collection');
    }

    public function getPrimaryKey() {
        return $this->getOption('primary_key');
    }

    public function getAttribute($key) {
        return isset($this->options['attributes'][$key])
             ? $this->options['attributes'][$key]
             : false;
    }

    public function getAttributes() {
        return $this->getOption('attributes');
    }

    public function hasAttribute($key) {
        return isset($this->options['attributes'][$key]);
    }

    public function isReadonly() {
        return $this->getOption('readonly');
    }

    public function pack(array $record, Data $data = null) {
        $types = Types::getInstance();
        $values = array();

        foreach ($record as $key => $value) {
            if (!$attribute = $this->getAttribute($key)) {
                continue;
            }

            if ($value !== null) {
                $value = $types->get($attribute['type'])->restore($value, $attribute);
            }

            $values[$key] = $value;
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

    public function unpack(Data $data, array $options = null) {
        $defaults = array('dirty' => false);
        $options = $options ? array_merge($defaults, $options) : $defaults;

        $types = Types::getInstance();
        $record = array();

        foreach($data->pick() as $key => $value) {
            if ($options['dirty'] && !$data->isDirty($key)) {
                continue;
            }

            if (!$attribute = $this->getAttribute($key)) {
                continue;
            }

            if ($value !== null) {
                $value = $types->get($attribute['type'])->store($value, $attribute);
            }

            $record[$key] = $value;
        }

        return $record;
    }

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

    public function refresh(Data $data) {
        if ($data->isFresh()) {
            return $data;
        }

        return $this->find($data->id(), $data);
    }

    public function save(Data $data) {
        if ($this->isReadonly()) {
            throw new \RuntimeException($this->class.' is readonly');
        }

        $is_fresh = $data->isFresh();
        if (!$is_fresh && !$data->isDirty()) {
            return true;
        }

        $this->triggerEvent(self::BEFORE_SAVE_EVENT, $data);

        $result = $is_fresh ? $this->insert($data) : $this->update($data);
        if (!$result) {
            throw new \RuntimeException($this->class.' save failed');
        }

        $this->triggerEvent(self::AFTER_SAVE_EVENT, $data);

        return true;
    }

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

    protected function insert(Data $data) {
        $this->triggerEvent(self::BEFORE_INSERT_EVENT, $data);
        $this->validateData($data);

        if (!is_array($id = $this->doInsert($data))) {
            return false;
        }

        $this->pack($id, $data);
        $this->triggerEvent(self::AFTER_INSERT_EVENT, $data);

        return true;
    }

    protected function update(Data $data) {
        $this->triggerEvent(self::BEFORE_UPDATE_EVENT, $data);
        $this->validateData($data);

        if (!$this->doUpdate($data)) {
            return false;
        }

        $this->pack(array(), $data);
        $this->triggerEvent(self::AFTER_UPDATE_EVENT, $data);

        return true;
    }

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

            if ($attribute['primary_key']) {
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

    static private $instance = array();
    final static public function factory($class) {
        if (!isset(self::$instance[$class])) {
            self::$instance[$class] = new static($class);
        }
        return self::$instance[$class];
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

class DBSelect extends \Lysine\Service\DB\Select {
    public function get($limit = null) {
        $result = array();

        foreach (parent::get($limit) as $row) {
            $result[$data->id()] = $row;
        }

        return $result;
    }
}

class DBData extends \Lysine\DataMapper\Data {
    static public function getMapper() {
        return DBMapper::factory(get_called_class());
    }

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

        $mapper = $this;
        $select->setProcessor(function($record) use ($mapper) {
            return $record ? $mapper->pack($record) : false;
        });

        return $select;
    }

    protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null) {
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

        list($where, $params) = $this->whereID($service, $id);

        return $service->update($collection, $record, $where, $params);
    }

    protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();

        list($where, $params) = $this->whereID($service, $id);

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
            $where[] = $service->quoteColumn($key) .' = ?';

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

        $this->onEvent(static::AFTER_UPDATE_EVENT, function($data) {
            $this->deleteCache($data->id());
        });

        $this->onEvent(static::AFTER_DELETE_EVENT, function($data) {
            $this->deleteCache($data->id());
        });
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
