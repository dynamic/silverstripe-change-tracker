<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use SilverStripe\Core\Injector\Injector;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Service\AffectedPagesService;
use Dynamic\ChangeTracker\Tests\Fixtures\CountingAffectedPagesService;

/**
 * How often the affected pages lookup runs. Each run scans every page in the site.
 */
class AffectedPagesLookupCountTest extends CharacterizationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(new CountingAffectedPagesService(), AffectedPagesService::class);
        CountingAffectedPagesService::$lookups = 0;
    }

    public function testComputedOncePerRecordForAChange()
    {
        $object = $this->makeObject('Counted');
        CountingAffectedPagesService::$lookups = 0;

        $object->Title = 'Counted, renamed';
        $object->write();

        $this->assertSame(1, CountingAffectedPagesService::$lookups);
    }

    public function testComputedOncePerPublishOfARecordThatIsNotAPage()
    {
        $object = $this->makeObject('Counted publish');
        CountingAffectedPagesService::$lookups = 0;

        $object->publishRecursive();

        // the update of the live page after the write and the join rows written by track() share one lookup
        $this->assertSame('Publish', $this->lastRecord()->ChangeType);
        $this->assertSame(1, CountingAffectedPagesService::$lookups);
    }

    public function testComputedOncePerPublishOfAPage()
    {
        $page = $this->makePage('Counted page');
        CountingAffectedPagesService::$lookups = 0;

        $page->publishRecursive();

        $this->assertSame(1, CountingAffectedPagesService::$lookups);
    }

    public function testRenderingTheColumnLooksUpAgainForRecordsWithoutJoinRows()
    {
        $plain = $this->makePlain('Belongs to no page');
        $plain->Notes = 'changed';
        $plain->write();
        $record = DataChangeRecord::get()->byID($this->lastRecord()->ID);
        CountingAffectedPagesService::$lookups = 0;

        $record->PageURL;

        $this->assertSame(1, CountingAffectedPagesService::$lookups);
    }

    public function testRenderingTheColumnDoesNotLookUpForRecordsWithJoinRows()
    {
        $page = $this->makePage('Has a record');
        $page->Title = 'Has a record, renamed';
        $page->write();
        $record = DataChangeRecord::get()->byID($this->lastRecord()->ID);
        CountingAffectedPagesService::$lookups = 0;

        $record->PageURL;

        $this->assertSame(0, CountingAffectedPagesService::$lookups);
    }

    public function testAWriteOfTheSameRecordObjectLooksUpAgain()
    {
        $plain = $this->makePlain('Tracked twice');
        $record = DataChangeRecord::create();
        $plain->Notes = 'one';
        $record->track($plain, 'Change');
        CountingAffectedPagesService::$lookups = 0;

        $record->getAffectedPageRecords();
        $this->assertSame(0, CountingAffectedPagesService::$lookups, 'Kept from track()');

        $record->write();
        $record->getAffectedPageRecords();
        $this->assertSame(1, CountingAffectedPagesService::$lookups, 'Dropped by the write');
    }
}
