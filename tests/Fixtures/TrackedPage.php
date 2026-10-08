<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A page with a has_one, two many_many relations (one with extra fields) and ownership
 */
class TrackedPage extends SiteTree implements TestOnly
{
    private static $table_name = 'TrackedPage';

    private static $db = [
        'Subtitle' => 'Varchar(100)',
    ];

    private static $has_one = [
        'Feature' => TrackedObject::class,
        'Related' => PlainRecordable::class,
    ];

    private static $many_many = [
        'Kids' => TrackedChild::class,
        'SortedKids' => TrackedChild::class,
        'Plains' => PlainRecordable::class,
    ];

    private static $many_many_extraFields = [
        'SortedKids' => [
            'Sort' => 'Int',
        ],
    ];

    private static $owns = [
        'Feature',
    ];
}
