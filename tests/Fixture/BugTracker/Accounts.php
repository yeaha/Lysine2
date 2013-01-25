<?php
// CREATE TABLE Accounts (
//   account_id        SERIAL PRIMARY KEY,
//   account_name      VARCHAR(20),
//   first_name        VARCHAR(20),
//   last_name         VARCHAR(20),
//   email             VARCHAR(100),
//   password_hash     CHAR(64),
//   portrait_image    BLOB,
//   hourly_rate       NUMERIC(9,2)
// );

namespace Tests\Fixture\BugTracker;

use Tests\Fixture\BugTracker;

class Accounts extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.accounts';
    static protected $props_meta = array(
        'account_id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
        'account_name' => array('type' => 'string'),
        'first_name' => array('type' => 'string'),
        'last_name' => array('type' => 'string'),
        'email' => array('type' => 'string'),
        'password_hash' => array('type' => 'string'),
        'portrait_image' => array('type' => 'binary', 'allow_null' => true),
        'hourly_rate' => array('type' => 'numeric'),
    );

    public function selectReportedBugs() {
        return BugTracker\Bugs::select()->where('reported_by = ?', $this->account_id);
    }

    public function selectAssignedBugs() {
        return BugTracker\Bugs::select()->where('assigned_to = ?', $this->account_id);
    }

    public function selectVerifiedBugs() {
        return BugTracker\Bugs::select()->where('verified_by = ?', $this->account_id);
    }
}
