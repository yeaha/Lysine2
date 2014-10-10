<?php
namespace Test\Mock\DataMapper;

use \Lysine\Service\IService;

class Mapper extends \Lysine\DataMapper\Mapper {
    public function setAttributes(array $attributes) {
        $options = $this->getOptions();
        $options['attributes'] = $attributes;

        $this->options = $this->normalizeOptions($options);
    }

    protected function doFind($id, IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();

        return $service->find($collection, $id);
    }

    protected function doInsert(\Lysine\DataMapper\Data $data, IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();
        $record = $this->unpack($data);

        if (!$service->insert($collection, $record, $data->id())) {
            return false;
        }

        $id = array();
        foreach ($this->getPrimaryKey() as $key) {
            if (!isset($record[$key])) {
                if (!$last_id = $service->getLastId($collection, $key)) {
                    throw new \Exception("{$this->class}: Insert record success, but get last-id failed!");
                }
                $id[$key] = $last_id;
            }
        }

        return $id;
    }

    protected function doUpdate(\Lysine\DataMapper\Data $data, IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();
        $record = $this->unpack($data, array('dirty' => true));

        return $service->update($collection, $record, $data->id());
    }

    protected function doDelete(\Lysine\DataMapper\Data $data, IService $service = null, $collection = null) {
        $service = $service ?: $this->getService();
        $collection = $collection ?: $this->getCollection();

        return $service->delete($collection, $data->id());
    }
}
