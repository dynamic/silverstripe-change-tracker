<?php

namespace Dynamic\ChangeTracker\Tests;

use SilverStripe\ORM\DataObject;
use Dynamic\ChangeTracker\Extension\ChangeRecordable;
use SilverStripe\Dev\TestOnly;

/**
 *
 *
 * @author marcus
 */
class TestTrackedUnderscoreObject extends DataObject implements TestOnly
{
    private static $table_name = 'Dynamic_ChangeTracker_Tests_TestTrackedUnderscoreObject';

    private static $db = [
        'Title'     => 'Varchar',
    ];

    private static $many_many = [
        'Kids'      => TestTrackedUnderscoreChild::class,
    ];

    private static $extensions = [
        ChangeRecordable::class,
    ];
}
