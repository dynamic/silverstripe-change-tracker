<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;

/**
 * Pins exact-class matching in the affected pages lookup
 */
class TrackedPageSubclass extends TrackedPage implements TestOnly
{
    private static $table_name = 'TrackedPageSubclass';

    private static $db = [
        'Extra' => 'Varchar(100)',
    ];
}
