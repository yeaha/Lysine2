<?php
// CREATE TABLE Tags (
//   bug_id            BIGINT UNSIGNED NOT NULL,
//   tag               VARCHAR(20) NOT NULL,
//   PRIMARY KEY      (bug_id, tag),
//   FOREIGN KEY (bug_id) REFERENCES Bugs(bug_id)
// );

namespace Tests\Fixture\BugTracker;

class Tags extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.tags';
    static protected $props_meta = array(
        'bug_id' => array('type' => 'integer', 'primary_key' => true),
        'tag' => array('type' => 'string', 'primary_key' => true),
    );
}
