<?php
// CREATE TABLE BugStatus (
//   status            VARCHAR(20) PRIMARY KEY
// );

namespace Tests\Fixture\BugTracker;

class BugStatus extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.bugs_status';
    static protected $props_meta = array(
        'status' => array('type' => 'string', 'primary_key' => true),
    );
}
