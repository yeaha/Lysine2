<?php
require __DIR__.'/../src/loader.php';

\Lysine\Service\Manager::getInstance()->importConfig(array(
    'master' => array(
        'class' => '\Lysine\Service\DB\Mysql',
        'dsn' => 'mysql:host=192.168.1.2;dbname=foo',
        'user' => 'dev',
        'password' => 'abc',
    ),
    'slave' => array(
        'class' => '\Lysine\Service\DB\Mysql',
        'dsn' => 'mysql:host=192.168.1.3;dbname=foo',
        'user' => 'dev',
        'password' => 'abc',
    ),
    'cache' => array(
        'class' => 'Lysine\Service\Redis',
        'host' => '192.168.1.4',
        'database' => 1,
        'timeout' => 3,
        'persistent_id' => 'cache',
    ),
));

class Topic extends \Lysine\DataMapper\Data {
    static protected $mapper = '\TopicMapper';
    static protected $service = 'slave';
    static protected $collection = 'tipic';
    static protected $attributes = array(
        'topic_id' => array(
            'type' => 'integer',
            'primary_key' => true,
            'auto_generate' => true,
        ),
        'subject' => array(
            'type' => 'string'
        ),
        'content' => array(
            'type' => 'string',
        ),
        'create_time' => array(
            'type' => 'datetime',
            'refuse_update' => true,
            'default' => 'now',
        ),
    );

    static public function select() {
        return static::getMapper()->select();
    }
}

class TopciMapper extends \Lysine\DataMapper\DBMapper {
    public function __construct($class) {
        parent::__construct($class);

        $this->onEvent(static::AFTER_INSERT_EVENT, function($data) {
            $this->setStatus($data->id(), 'at_master');
        });

        $this->onEvent(static::AFTER_UPDATE_EVENT, function($data) {
            $this->setStatus($data->id(), 'at_master');
        });

        $this->onEvent(static::AFTER_DELETE_EVENT, function($data) {
            $this->setStatus($data->id(), 'deleted');
        });
    }

    public function selectMaster() {
        return parent::select(service('master'));
    }

    public function selectSlave() {
        return parent::select(service('slave'));
    }

    public function select(\Lysine\Service\IService $service = null, $collection = null) {
        return $this->selectSlave();
    }

    protected function doFind($id, \Lysine\Service\IService $service = null, $collection = null) {
        $status = $this->getDataStatus($id);
        $service = ($status == 'at_master') ? service('master') : service('slave');
        return parent::doFind($id, $service);
    }

    protected function doInsert(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = service('master');
        return parent::doInsert($data, $service);
    }

    protected function doUpdate(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = service('master');
        return parent::doUpdate($data, $service);
    }

    protected function doDelete(\Lysine\DataMapper\Data $data, \Lysine\Service\IService $service = null, $collection = null) {
        $service = service('master');
        return parent::doDelete($data, $service);
    }

    protected function setDataStatus($id, $status) {
        $redis = service('cache');
        $key = 'topic_status_'.$id;
        return $redis->setex($key, 300, $status);
    }

    protected function getDataStatus($id) {
        $redis = service('cache');
        $key = 'topic_status_'.$id;
        return $redis->get($key);
    }
}
