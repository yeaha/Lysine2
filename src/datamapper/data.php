<?php
namespace Lysine\DataMapper;

abstract class Data {
    static protected $mapper = '\Lysine\DataMapper\Mapper';
    static protected $service;
    static protected $collection;
    static protected $attributes = array();
    static protected $readonly = false;
    static protected $strict = false;

    protected $fresh;
    protected $values = array();
    protected $dirty = array();

    public function __before_save() {}
    public function __after_save() {}

    public function __before_insert() {}
    public function __after_insert() {}

    public function __before_update() {}
    public function __after_update() {}

    public function __before_delete() {}
    public function __after_delete() {}

    public function __construct(array $values = null, array $options = null) {
        $defaults = array('fresh' => true);
        $options = $options ? array_merge($defaults, $options) : $defaults;

        $attributes = static::getMapper()->getAttributes();

        $this->fresh = $options['fresh'];

        if ($values) {
            foreach ($values as $key => $value) {
                if (isset($attributes[$key])) {
                    $this->set($key, $value, array('strict' => true, 'force' => true));
                }
            }
        }

        if ($this->isFresh()) {
            foreach ($attributes as $key => $attribute) {
                if (isset($this->values[$key])) {
                    continue;
                }

                $default = Types::getInstance()->get($attribute['type'])->getDefaultValue($attribute);
                if ($default !== null) {
                    $this->change($key, $default);
                }
            }
        } else {
            $this->dirty = array();
        }
    }

    public function __get($key) {
        return $this->get($key);
    }

    public function __set($key, $value) {
        $this->set($key, $value, array('strict' => true));
    }

    public function __isset($key) {
        return isset($this->values[$key]);
    }

    final public function __pack(array $values, $replace) {
        $this->values = $replace ? $values : array_merge($this->values, $values);
        $this->dirty = array();
        $this->fresh = false;

        return $this;
    }

    public function has($key) {
        $mapper = static::getMapper();
        return (bool)$mapper->hasAttribute($key);
    }

    public function set($key, $value, array $options = null) {
        $defaults = array('force' => false, 'strict' => true);
        $options = $options ? array_merge($defaults, $options) : $defaults;

        $attribute = static::getMapper()->getAttribute($key);

        if (!$attribute) {
            if ($options['strict']) {
                throw new \UnexpectedValueException(get_class() .": Undefined property {$key}");
            }

            return $this;
        }

        if ($attribute['strict'] && !$options['strict']) {
            return $this;
        }

        if (!$options['force'] && $attribute['refuse_update'] && !$this->isFresh()) {
            if ($options['strict']) {
                throw new \RuntimeException(get_class() .": Property {$key} refuse update");
            }

            return $this;
        }

        if ($value === '') {
            $value = null;
        }

        if ($value === null) {
            if (!$attribute['allow_null']) {
                throw new \UnexpectedValueException(get_class() .": Property {$key} not allow null");
            }
        } else {
            $value = $this->normalize($key, $value, $attribute);

            if ($attribute['pattern'] && !preg_match($attribute['pattern'], $value)) {
                throw new \UnexpectedValueException(get_class() .": Property {$key} mismatching pattern {$attribute['pattern']}");
            }
        }

        if ($this->get($key) === $value) {
            return $this;
        }

        $this->change($key, $value);

        return $this;
    }

    public function merge(array $values) {
        foreach ($values as $key => $value) {
            $this->set($key, $value, array('strict' => false));
        }

        return $this;
    }

    public function get($key) {
        if (!$attribute = static::getMapper()->getAttribute($key)) {
            throw new \UnexpectedValueException(get_class() .": Undefined property {$key}");
        }

        return array_key_exists($key, $this->values)
             ? $this->values[$key]
             : Types::getInstance()->get($attribute['type'])->getDefaultValue($attribute);
    }

    public function pick($keys = null) {
        $attributes = static::getMapper()->getAttributes();

        if ($keys === null) {
            $keys = array();
            foreach ($attributes as $key => $attribute) {
                if (!$attribute['protected']) {
                    $keys[] = $key;
                }
            }
        } else {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        $values = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->values)) {
                $values[$key] = $this->get($key);
            }
        }

        return $values;
    }

    public function toJSON() {
        $attributes = static::getMapper()->getAttributes();
        $type = Types::getInstance();
        $json = array();

        foreach ($this->values as $key => $value) {
            $attribute = $attributes[$key];

            if (!$attribute['protected']) {
                $json[$key] = $type->get($attribute['type'])->toJSON($value, $attribute);
            }
        }

        return $json;
    }

    public function isFresh() {
        return $this->fresh;
    }

    public function isDirty($key = null) {
        return $key === null
             ? (bool)$this->dirty
             : isset($this->dirty[$key]);
    }

    public function id() {
        $keys = static::getMapper()->getPrimaryKey();
        $id = array();

        foreach ($keys as $key) {
            $value = $this->get($key);

            if (count($keys) === 1) {
                return $value;
            }

            $id[$key] = $value;
        }

        return $id;
    }

    public function refresh() {
        return static::getMapper()->refresh($this);
    }

    public function save() {
        return static::getMapper()->save($this);
    }

    public function destroy() {
        return static::getMapper()->destroy($this);
    }

    protected function normalize($key, $value, array $attribute) {
        return Types::getInstance()->get($attribute['type'])->normalize($value, $attribute);
    }

    final protected function change($key, $value) {
        $this->values[$key] = $value;
        $this->dirty[$key] = true;
    }

    static public function find($id) {
        return static::getMapper()->find($id);
    }

    final static public function getMapper() {
        $class = static::$mapper;
        return $class::factory( get_called_class() );
    }

    final static public function getOptions() {
        $options = array(
            'service' => static::$service,
            'collection' => static::$collection,
            'attributes' => static::$attributes,
            'readonly' => static::$readonly,
            'strict' => static::$strict,
        );

        $called_class = get_called_class();
        if ($called_class == __CLASS__) {
            return $options;
        }

        $parent_class = get_parent_class($called_class);
        $parent_options = $parent_class::getOptions();

        $options['attributes'] = array_merge($parent_options['attributes'], $options['attributes']);
        $options = array_merge($parent_options, $options);

        return $options;
    }
}
