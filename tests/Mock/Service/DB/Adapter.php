<?php
namespace Test\Mock\Service\DB;

class Adapter extends \Lysine\Service\DB\Adapter {
    private $history = array();
    private $last_id = 1;

    public function execute($sql, $params = null) {
        $this->history[] = $sql;
    }

    public function lastId($table = null, $column = null) {
        return $this->last_id++;
    }

    public function getLastStatement() {
        $count = count($this->history);
        return $this->history[$count-1];
    }
}
