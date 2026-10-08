<?php

namespace Dynamic\ChangeTracker\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 *
 *
 * @author marcus
 */
class TestTrackedUnderscoreChild extends DataObject implements TestOnly
{
    private static $table_name = 'Dynamic_ChangeTracker_Tests_TestTrackedUnderscoreChild';

    private static $db = [
        'Title'     => 'Varchar',
    ];
}
