<?php

namespace Symbiote\DataChange\Tests\Characterization;

use SilverStripe\CMS\Model\SiteTree;
use Symbiote\DataChange\Extension\SiteTreeChangeRecordable;
use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Tests\Fixtures\CountingLegacySiteDataChangeRecordExtension;

/**
 * How often the affected pages lookup runs. Each run scans every page in the site.
 */
class AffectedPagesLookupCountTest extends CharacterizationTestCase
{
    protected static $required_extensions = [
        SiteTree::class => [
            SiteTreeChangeRecordable::class,
        ],
        DataChangeRecord::class => [
            CountingLegacySiteDataChangeRecordExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;
    }

    public function testComputedOncePerRecordForAChange()
    {
        $object = $this->makeObject('Counted');
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;

        $object->Title = 'Counted, renamed';
        $object->write();

        $this->assertSame(1, CountingLegacySiteDataChangeRecordExtension::$lookups);
    }

    public function testComputedTwicePerPublishOfARecordThatIsNotAPage()
    {
        $object = $this->makeObject('Counted publish');
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;

        $object->publishRecursive();

        // once in the extension's onAfterWrite, to bump the live page, and once in track()
        $this->assertSame('Publish', $this->lastRecord()->ChangeType);
        $this->assertSame(2, CountingLegacySiteDataChangeRecordExtension::$lookups);
    }

    public function testComputedOncePerPublishOfAPage()
    {
        $page = $this->makePage('Counted page');
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;

        $page->publishRecursive();

        $this->assertSame(1, CountingLegacySiteDataChangeRecordExtension::$lookups);
    }

    public function testRenderingTheColumnLooksUpAgainForRecordsWithoutJoinRows()
    {
        $plain = $this->makePlain('Belongs to no page');
        $plain->Notes = 'changed';
        $plain->write();
        $record = DataChangeRecord::get()->byID($this->lastRecord()->ID);
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;

        $record->PageURL;

        $this->assertSame(1, CountingLegacySiteDataChangeRecordExtension::$lookups);
    }

    public function testRenderingTheColumnDoesNotLookUpForRecordsWithJoinRows()
    {
        $page = $this->makePage('Has a record');
        $page->Title = 'Has a record, renamed';
        $page->write();
        $record = DataChangeRecord::get()->byID($this->lastRecord()->ID);
        CountingLegacySiteDataChangeRecordExtension::$lookups = 0;

        $record->PageURL;

        $this->assertSame(0, CountingLegacySiteDataChangeRecordExtension::$lookups);
    }
}
