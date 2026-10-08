<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TrackedChild extends DataObject implements TestOnly
{
    private static $table_name = 'TrackedChild';

    private static $db = [
        'Title' => 'Text',
    ];

    private static $belongs_many_many = [
        'Parents' => TrackedPage::class . '.Kids',
        'SortedParents' => TrackedPage::class . '.SortedKids',
        'ObjectParents' => TrackedObject::class . '.Kids',
    ];

    private static $extensions = [
        Versioned::class,
    ];
}
