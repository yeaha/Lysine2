<?php
// CREATE TABLE Screenshots (
//   bug_id            BIGINT UNSIGNED NOT NULL,
//   image_id          BIGINT UNSIGNED NOT NULL,
//   screenshot_image  BLOB,
//   caption           VARCHAR(100),
//   PRIMARY KEY      (bug_id, image_id),
//   FOREIGN KEY (bug_id) REFERENCES Bugs(bug_id)
// );

namespace Tests\Fixture\BugTracker;

use Tests\Fixture\BugTracker;

class ScreenShots extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.screenshots';
    static protected $props_meta = array(
        'bug_id' => array('type' => 'integer', 'primary_key' => true),
        'image_id' => array('type' => 'integer', 'primary_key' => true),
        'screenshot_image' => array('type' => 'binary'),
        'caption' => array('type' => 'string'),
    );

    public function getBug() {
        return BugTracker\Bugs::find($this->bug_id);
    }
}
