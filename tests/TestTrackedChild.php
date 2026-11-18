<?php

namespace Dynamic\ChangeTracker\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 *
 *
 * @author marcus
 */
class TestTrackedChild extends DataObject implements TestOnly
{
    private static $table_name = 'TestTrackedChild';

    private static $db = [
        'Title'     => 'Varchar',
    ];
}
