<?php
// CREATE TABLE Bugs (
//   bug_id            SERIAL PRIMARY KEY,
//   date_reported     DATE NOT NULL,
//   summary           VARCHAR(80),
//   description       VARCHAR(1000),
//   resolution        VARCHAR(1000),
//   reported_by       BIGINT UNSIGNED NOT NULL,
//   assigned_to       BIGINT UNSIGNED,
//   verified_by       BIGINT UNSIGNED,
//   status            VARCHAR(20) NOT NULL DEFAULT 'NEW' ,
//   priority          VARCHAR(20),
//   hours             NUMERIC(9,2),
//   FOREIGN KEY (reported_by) REFERENCES Accounts(account_id),
//   FOREIGN KEY (assigned_to) REFERENCES Accounts(account_id),
//   FOREIGN KEY (verified_by) REFERENCES Accounts(account_id),
//   FOREIGN KEY (status) REFERENCES BugStatus(status)
// );

namespace Tests\Fixture\BugTracker;

use Tests\Fixture\BugTracker;

class Bugs extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.bugs';
    static protected $props_meta = array(
        'bug_id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
        'date_reported' => array('type' => 'date'),
        'summary' => array('type' => 'string'),
        'description' => array('type' => 'string'),
        'resolution' => array('type' => 'string'),
        'reported_by' => array('type' => 'integer'),
        'assigned_to' => array('type' => 'integer'),
        'verified_by' => array('type' => 'integer'),
        'status' => array('type' => 'string'),
        'priority' => array('type' => 'string'),
        'hours' => array('type' => 'numeric'),
    );

    public function getReportedBy() {
        return BugTracker\Accounts::find($this->reported_by);
    }

    public function getAssignedTo() {
        return BugTracker\Accounts::find($this->assigned_to);
    }

    public function getVerifiedBy() {
        return BugTracker\Accounts::find($this->verified_by);
    }

    public function getTags() {
        return BugTracker\Tags::select()
                            ->setCols('tag')
                            ->where('bug_id = ?', $this->bug_id)
                            ->execute()->getCols();
    }

    public function selectComments() {
        return BugTracker\Comments::select()->where('bug_id = ?', $this->bug_id);
    }
}
