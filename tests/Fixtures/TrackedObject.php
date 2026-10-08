<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;

/**
 * A versioned record tracked the way the sites track their own models
 */
class TrackedObject extends DataObject implements TestOnly
{
    private static $table_name = 'TrackedObject';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Body' => 'Text',
        'Password' => 'Varchar(100)',
        'Secret' => 'Varchar(100)',
        'LongTitle' => 'Text',
    ];

    private static $has_one = [
        'Page' => TrackedPage::class,
        'BasePage' => SiteTree::class,
    ];

    private static $has_many = [
        'FeaturedOn' => TrackedPage::class . '.Feature',
    ];

    private static $many_many = [
        'Kids' => TrackedChild::class,
        'RelatedPages' => TrackedPage::class,
    ];

    private static $extensions = [
        Versioned::class,
        SiteTreeChangeRecordable::class,
    ];
}
