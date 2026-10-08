<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use Symbiote\DataChange\Extension\ChangeRecordable;

/**
 * Stands in for an element: it has no relation to a page but answers getPage()
 */
class ElementLike extends DataObject implements TestOnly
{
    private static $table_name = 'ElementLike';

    private static $db = [
        'Title' => 'Varchar(255)',
        'PageRef' => 'Int',
    ];

    private static $extensions = [
        ChangeRecordable::class,
    ];

    public function getPage()
    {
        return TrackedPage::get()->byID($this->PageRef);
    }
}
