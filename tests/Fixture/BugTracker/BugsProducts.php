<?php
// CREATE TABLE BugsProducts(
//   bug_id            BIGINT UNSIGNED NOT NULL,
//   product_id        BIGINT UNSIGNED NOT NULL,
//   PRIMARY KEY      (bug_id, product_id),
//   FOREIGN KEY (bug_id) REFERENCES Bugs(bug_id),
//   FOREIGN KEY (product_id) REFERENCES Products(product_id)
// );

namespace Tests\Fixture\BugTracker;

class BugsProducts extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.bugs_products';
    static protected $props_meta = array(
        'bug_id' => array('type' => 'integer', 'primary_key' => true),
        'product_id' => array('type' => 'integer', 'primary_key' => true),
    );
}
