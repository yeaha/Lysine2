<?php
// CREATE TABLE Products (
//   product_id        SERIAL PRIMARY KEY,
//   product_name      VARCHAR(50)
// );

namespace Tests\Fixture\BugTracker;

use Tests\Fixture\BugTracker;

class Products extends \Lysine\DataMapper\DBData {
    static protected $storage = 'pgsql.local';
    static protected $collection = 'bug_tracker.products';
    static protected $props_meta = array(
        'product_id' => array('type' => 'integer', 'primary_key' => true, 'auto_increase' => true),
        'product_name' => array('type' => 'string'),
    );

    public function selectBugs() {
        // select * from bugs where bug_id in (select bug_id from bugs_products where product_id = ?)

        $select = BugTracker\BugsProducts::select()->setCols('bug_id')->where('product_id = ?', $this->product_id);
        return BugTracker\Bugs::select()->whereIn('bug_id', $select);
    }
}
