<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use Dynamic\ChangeTracker\Extension\ChangeRecordable;

/**
 * Owner of a many_many through relation, which the tracked list does not cover
 */
class ThroughOwner extends DataObject implements TestOnly
{
    private static $table_name = 'ThroughOwner';

    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $many_many = [
        'Children' => [
            'through' => ThroughJoin::class,
            'from' => 'Owner',
            'to' => 'Child',
        ],
    ];

    private static $extensions = [
        ChangeRecordable::class,
    ];
}
