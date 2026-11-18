<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use Dynamic\ChangeTracker\Extension\ChangeRecordable;

/**
 * Tracked, but not versioned
 */
class PlainRecordable extends DataObject implements TestOnly
{
    private static $table_name = 'PlainRecordable';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Notes' => 'Text',
        'Password' => 'Varchar(100)',
        'Secret' => 'Varchar(100)',
        'SecurityID' => 'Varchar(100)',
    ];

    private static $has_one = [
        'Page' => TrackedPage::class,
    ];

    private static $extensions = [
        ChangeRecordable::class,
    ];
}
