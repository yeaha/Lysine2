<?php
namespace Lysine\DataMapper {
    /**
     * 数据类型管理
     */
    class Types {
        use \Lysine\Traits\Singleton;

        /**
         * 数据类型helper实例缓存
         *
         * @var array
         */
        protected $types = array();

        /**
         * 数据类型对应类名数组
         *
         * @var array
         */
        protected $type_classes = array();

        /**
         * 根据数据类型名字获得对应的数据类型helper
         *
         * @param string $type
         * @return object 数据类型helper实例
         */
        public function get($type) {
            $type = strtolower($type);

            if ($type == 'int') {
                $type = 'integer';
            } elseif ($type == 'text') {
                $type = 'string';
            }

            if (!isset($this->type_classes[$type]))
                $type = 'mixed';

            if (isset($this->types[$type]))
                return $this->types[$type];

            $class = $this->type_classes[$type];
            return $this->types[$type] = new $class;
        }

        /**
         * 注册一个新的数据类型helper
         *
         * @param string $type 数据类型名字
         * @param string $class helper类名
         * @return $this
         */
        public function register($type, $class) {
            $type = strtolower($type);
            $this->type_classes[$type] = $class;

            return $this;
        }

        /**
         * 工厂方法
         *
         * @param string $name
         * @return object
         */
        static public function factory($name) {
            return static::getInstance()->get($name);
        }

        /**
         * 格式化并补全属性定义数组
         *
         * @param array $attribute
         * @return array
         */
        static public function normalizeAttribute(array $attribute) {
            $defaults = array(
                // 是否允许为空
                'allow_null' => false,

                // 是否自动生成属性值，例如mysql里面的auto increase
                'auto_generate' => false,

                // 默认值
                'default' => null,

                // 标记为“废弃”属性
                'deprecated' => false,

                // 正则表达式检查
                'pattern' => null,

                // 是否主键
                'primary_key' => false,

                // 安全特性
                // 标记为protected的属性会在输出时被自动忽略
                // 避免不小心把敏感数据泄漏到客户端
                'protected' => false,

                // 保存之后不允许修改
                'refuse_update' => false,

                // 安全特性
                // 标记为strict的属性只能在严格开关被打开的情况下才能够赋值
                // 避免不小心被误修改到
                'strict' => null,

                // 数据类型
                'type' => null,
            );

            $type = isset($attribute['type']) ? $attribute['type'] : null;

            $attribute = array_merge(
                $defaults,
                self::factory($type)->normalizeAttribute($attribute)
            );

            if ($attribute['allow_null']) {
                $attribute['default'] = null;
            }

            if ($attribute['primary_key']) {
                $attribute['allow_null'] = false;
                $attribute['refuse_update'] = true;
                $attribute['strict'] = true;
            }

            if ($attribute['protected'] && $attribute['strict'] === null) {
                $attribute['strict'] = true;
            }

            return $attribute;
        }
    }

    \Lysine\DataMapper\Types::getInstance()
        ->register('mixed', '\Lysine\DataMapper\Types\Mixed')
        ->register('datetime', '\Lysine\DataMapper\Types\DateTime')
        ->register('integer', '\Lysine\DataMapper\Types\Integer')
        ->register('json', '\Lysine\DataMapper\Types\Json')
        ->register('numeric', '\Lysine\DataMapper\Types\Numeric')
        ->register('pg_array', '\Lysine\DataMapper\Types\PgsqlArray')
        ->register('pg_hstore', '\Lysine\DataMapper\Types\PgsqlHstore')
        ->register('string', '\Lysine\DataMapper\Types\String')
        ->register('uuid', '\Lysine\DataMapper\Types\UUID');
}

namespace Lysine\DataMapper\Types {
    /**
     * 默认数据类型
     */
    class Mixed {
        /**
         * 格式化属性定义
         *
         * @param array $attribute
         * @return array
         */
        public function normalizeAttribute(array $attribute) {
            return $attribute;
        }

        /**
         * 格式化属性值
         *
         * @see \Lysine\DataMapper\Data::set()
         * @param mixed $value
         * @param array $attribute
         * @return mixed
         */
        public function normalize($value, array $attribute) {
            return $value;
        }

        /**
         * 把值转换为存储格式
         *
         * @param mixed $value
         * @param array $attribute
         * @return mixed
         */
        public function store($value, array $attribute) {
            return $value;
        }

        /**
         * 把存储格式的值转换为属性值
         *
         * @param mixed $value
         * @param array $attribute
         * @return mixed
         */
        public function restore($value, array $attribute) {
            if ($value === null) {
                return null;
            }

            return $this->normalize($value, $attribute);
        }

        /**
         * 获取默认值
         *
         * @param array $attribute
         * @return mixed
         */
        public function getDefaultValue(array $attribute) {
            return $attribute['default'];
        }

        /**
         * 转换为对json_encode友好的格式
         *
         * @param mixed $value
         * @param array $attribute
         * @return mixed
         */
        public function toJSON($value, array $attribute) {
            return $value;
        }
    }

    /**
     * 数字类型
     */
    class Numeric extends Mixed {
        public function normalize($value, array $attribute) {
            return $value * 1;
        }
    }

    /**
     * 整数类型
     */
    class Integer extends Numeric {
        public function normalize($value, array $attribute) {
            return (int)$value;
        }
    }

    /**
     * 字符串类型
     */
    class String extends Mixed {
        public function normalize($value, array $attribute) {
            return (string)$value;
        }
    }

    /**
     * JSON类型
     */
    class Json extends Mixed {
        public function normalizeAttribute(array $attribute) {
            return array_merge(array(
                'strict' => true,
            ), $attribute);
        }

        public function normalize($value, array $attribute) {
            if (is_array($value)) {
                return $value;
            }

            if ($value === null) {
                return array();
            }

            $value = json_decode($value, true);

            if ($value === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \UnexpectedValueException(json_last_error_msg(), json_last_error());
            }

            return $value;
        }

        public function store($value, array $attribute) {
            if ($value === array()) {
                return null;
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        public function restore($value, array $attribute) {
            if ($value === null) {
                return array();
            }

            return $this->normalize($value, $attribute);
        }

        public function getDefaultValue(array $attribute) {
            return array();
        }
    }

    /**
     * 时间类型
     */
    class Datetime extends Mixed {
        public function normalize($value, array $attribute) {
            if ($value instanceof \DateTime) {
                return $value;
            }

            if (!isset($attribute['format'])) {
                return new \DateTime($value);
            }

            if (!$value = \DateTime::createFromFormat($attribute['format'], $value)) {
                throw new \UnexpectedValueException('Create datetime from format "'.$attribute['format'].'" failed!');
            }

            return $value;
        }

        public function store($value, array $attribute) {
            if ($value instanceof \DateTime) {
                $format = isset($attribute['format']) ? $attribute['format'] : 'c'; // ISO 8601
                $value = $value->format($format);
            }

            return $value;
        }

        public function getDefaultValue(array $attribute) {
            return ($attribute['default'] === null)
                 ? null
                 : new \DateTime($attribute['default']);
        }
    }

    /**
     * UUID字符串类型
     */
    class UUID extends Mixed {
        public function normalizeAttribute(array $attribute) {
            $attribute = array_merge(array(
                'upper' => false,
            ), $attribute);

            if (isset($attribute['primary_key']) && $attribute['primary_key']) {
                $attribute['auto_generate'] = true;
            }

            return $attribute;
        }

        public function getDefaultValue(array $attribute) {
            if (!$attribute['auto_generate']) {
                return $attribute['default'];
            }

            $uuid = self::generate();

            if (isset($attribute['upper']) && $attribute['upper']) {
                $uuid = strtoupper($uuid);
            }

            return $uuid;
        }

        // http://php.net/manual/en/function.uniqid.php#94959
        static public function generate() {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }

    /**
     * PostgreSQL数组类型
     */
    class PgsqlArray extends Mixed {
        public function normalizeAttribute(array $attribute) {
            return array_merge(array(
                'strict' => true,
            ), $attribute);
        }

        public function normalize($value, array $attribute) {
            if ($value === null) {
                return array();
            }

            if (!is_array($value)) {
                throw new \UnexpectedValueException('Postgresql array must be of the type array');
            }

            return $value;
        }

        public function store($value, array $attribute) {
            if ($value === array()) {
                return null;
            }

            return \Lysine\Service\DB\Adapter\Pgsql::encodeArray($value);
        }

        public function restore($value, array $attribute) {
            if ($value === null) {
                return array();
            }

            return \Lysine\Service\DB\Adapter\Pgsql::decodeArray($value);
        }

        public function getDefaultValue(array $attribute) {
            return array();
        }
    }

    /**
     * PostgreSQL hstore类型
     */
    class PgsqlHstore extends Mixed {
        public function normalizeAttribute(array $attribute) {
            return array_merge(array(
                'strict' => true,
            ), $attribute);
        }

        public function normalize($value, array $attribute) {
            if ($value === null) {
                return array();
            }

            if (!is_array($value)) {
                throw new \UnexpectedValueException('Postgresql hstore must be of the type array');
            }

            return $value;
        }

        public function store($value, array $attribute) {
            if ($value === array()) {
                return null;
            }

            return \Lysine\Service\DB\Adapter\Pgsql::encodeHstore($value);
        }

        public function restore($value, array $attribute) {
            if ($value === null) {
                return array();
            }

            return \Lysine\Service\DB\Adapter\Pgsql::decodeHstore($value);
        }

        public function getDefaultValue(array $attribute) {
            return array();
        }
    }
}
