<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Join object of a many_many through relation
 */
class ThroughJoin extends DataObject implements TestOnly
{
    private static $table_name = 'ThroughJoin';

    private static $db = [
        'Label' => 'Varchar(100)',
    ];

    private static $has_one = [
        'Owner' => ThroughOwner::class,
        'Child' => TrackedChild::class,
    ];
}
