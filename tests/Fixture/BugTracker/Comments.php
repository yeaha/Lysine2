<?php
// CREATE TABLE Comments (
//   comment_id        SERIAL PRIMARY KEY,
//   bug_id            BIGINT UNSIGNED NOT NULL,
//   author            BIGINT UNSIGNED NOT NULL,
//   comment_date      DATETIME NOT NULL,
//   comment           TEXT NOT NULL,
//   FOREIGN KEY (bug_id) REFERENCES Bugs(bug_id),
//   FOREIGN KEY (author) REFERENCES Accounts(account_id)
// );

namespace Tests\Fixture\BugTracker;

use Tests\Fixture\BugTracker;

class Comments extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.comments';
    static protected $props_meta = array(
        'comment_id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
        'bug_id' => array('type' => 'integer'),
        'author' => array('type' => 'integer'),
        'comment_date' => array('type' => 'date'),
        'comment' => array('type' => 'string'),
    );

    public function getBug() {
        return BugTracker\Bugs::find($this->bug_id);
    }

    public function getAuthor() {
        return BugTracker\Accounts::find($this->author);
    }
}
