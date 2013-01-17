<?php
namespace Test\Mock\DataMapper;

use \Lysine\Service\IService;

class Mapper extends \Lysine\DataMapper\Mapper {
    public function getMeta() {
        return Meta::factory($this->class);
    }

    protected function doFind($id, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        return $storage->find($collection, $id);
    }

    protected function doInsert(\Lysine\DataMapper\Data $data, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        if (!$storage->insert($collection, $data->toArray(), $data->id()))
            return false;

        $id = array();
        foreach ($this->getMeta()->getPrimaryKey() as $prop => $prop_meta) {
            $last_id = $prop_meta['auto_increase']
                     ? $storage->getLastId($collection, $prop)
                     : $record[$prop];

            if (!$last_id)
                throw new RuntimeError("{$this->class}: Insert record success, but get last-id failed!");

            $id[$prop] = $last_id;
        }

        return $id;
    }

    protected function doUpdate(\Lysine\DataMapper\Data $data, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        return $storage->update($collection, $data->toArray(true), $data->id());
    }

    protected function doDelete(\Lysine\DataMapper\Data $data, IService $storage = null, $collection = null) {
        $storage = $storage ?: $this->getStorage();
        $collection = $collection ?: $this->getCollection();

        return $storage->delete($collection, $data->id());
    }
}
