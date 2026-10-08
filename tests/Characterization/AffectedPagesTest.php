<?php

namespace Symbiote\DataChange\Tests\Characterization;

use SilverStripe\ORM\DB;
use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Tests\Fixtures\PlainRecordableSubclass;
use Symbiote\DataChange\Tests\Fixtures\ElementLike;
use Symbiote\DataChange\Tests\Fixtures\TrackedObject;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;
use Symbiote\DataChange\Tests\Fixtures\TrackedPageSubclass;

/**
 * Which pages the site extension attaches to a record, and the ways it gets that wrong
 */
class AffectedPagesTest extends CharacterizationTestCase
{
    /**
     * @param DataChangeRecord $record
     * @return int[] ids of the pages in the AffectedPages relation, oldest join row first
     */
    private function joined(DataChangeRecord $record): array
    {
        return array_map('intval', $record->AffectedPages()->sort('ID', 'ASC')->column('ID'));
    }

    /**
     * @param DataChangeRecord $record
     * @return int[] ids returned by a fresh lookup, in the order returned
     */
    private function computed(DataChangeRecord $record): array
    {
        return array_map(function ($page) {
            return (int)$page->ID;
        }, array_values($record->getAffectedPageRecords()));
    }

    public function testSiteTreeSelf()
    {
        $page = TrackedPageSubclass::create(['Title' => 'A page']);
        $page->write();

        $record = $this->recordsFor($page)[0];

        $this->assertSame([(int)$page->ID], $this->joined($record));
        $this->assertSame([0], array_keys($record->getAffectedPageRecords()));
    }

    public function testHasOneToPage()
    {
        $page = $this->makePage('Linked');
        $object = $this->makeObject('Points at a page', ['PageID' => $page->ID]);

        $object->Title = 'Points at a page, renamed';
        $object->write();

        $record = $this->lastRecord();
        $this->assertSame('Change', $record->ChangeType);
        $this->assertSame([(int)$page->ID], $this->joined($record));
    }

    public function testChangeRecordSeesTheStoredRelationNotTheValueBeingWritten()
    {
        $page = $this->makePage('Linked');
        $object = $this->makeObject('Points at nothing yet');

        // the Change record is built before the row is saved, so it reads the old, empty, relation
        $object->PageID = $page->ID;
        $object->write();
        $this->assertSame([], $this->joined($this->lastRecord()));

        $this->resetTracking();
        $object->Title = 'Points at a page now';
        $object->write();
        $this->assertSame([(int)$page->ID], $this->joined($this->lastRecord()));

        // New records are built after the save and do see the relation
        $this->resetTracking();
        $fresh = TrackedObject::create(['Title' => 'Born linked', 'PageID' => $page->ID]);
        $fresh->write();
        $this->assertSame('New', $this->lastRecord()->ChangeType);
        $this->assertSame([(int)$page->ID], $this->joined($this->lastRecord()));
    }

    public function testHasOneToBaseSiteTreeIgnored()
    {
        $page = $this->makePage('Base class target');
        $object = $this->makeObject('Points at SiteTree', ['BasePageID' => $page->ID]);
        $object->Title = 'Points at SiteTree, renamed';
        $object->write();

        $record = $this->lastRecord();

        $this->assertSame('Change', $record->ChangeType);
        $this->assertSame([], $this->joined($record));
        $this->assertSame([], $record->getAffectedPageRecords());
    }

    public function testHasManyPages()
    {
        $one = $this->makePage('Features it one');
        $two = $this->makePage('Features it two');
        $object = $this->makeObject('Featured');
        foreach ([$one, $two] as $page) {
            $page->FeatureID = $object->ID;
            $page->write();
            $this->resetTracking();
        }

        $object->Title = 'Featured, renamed';
        $object->write();

        // the has_many is declared with a dot ("TrackedPage.Feature"), and hasMany() strips it, so it is found
        $this->assertSame(['FeaturedOn' => TrackedPage::class], $object->hasMany());
        $this->assertEqualsCanonicalizing([(int)$one->ID, (int)$two->ID], $this->joined($this->lastRecord()));
    }

    public function testManyManyPages()
    {
        $page = $this->makePage('Related');
        $other = $this->makePage('Not related');
        $object = $this->makeObject('Has pages');
        $object->RelatedPages()->add($page);
        $this->resetTracking();

        $object->Title = 'Has pages, renamed';
        $object->write();

        $record = $this->lastRecord();
        $this->assertSame([(int)$page->ID], $this->joined($record));
        $this->assertNotContains((int)$other->ID, $this->joined($record));
    }

    public function testGetPageMethod()
    {
        $page = $this->makePage('Owns the element');
        $element = ElementLike::create(['Title' => 'Element', 'PageRef' => $page->ID]);
        $element->write();
        $this->resetTracking();

        $element->Title = 'Element, renamed';
        $element->write();

        $this->assertSame([(int)$page->ID], $this->joined($this->lastRecord()));
    }

    public function testReverseHasOneScan()
    {
        $plain = $this->makePlain('Referenced');
        $page = $this->makePage('References it');
        $page->RelatedID = $plain->ID;
        $page->write();
        $this->resetTracking();
        $this->makePage('References nothing');

        $plain->Notes = 'changed';
        $plain->write();

        // nothing on the record points at the page, so it is found by scanning every page
        $this->assertSame([(int)$page->ID], $this->joined($this->lastRecord()));
    }

    public function testReverseManyManyScan()
    {
        $child = $this->makeChild('Child');
        $parent = $this->makePage('Parent');
        $parent->Kids()->add($child);
        $this->makePage('Other');
        $this->resetTracking();

        // TrackedChild is not tracked itself; a forced record exercises the lookup
        $record = $this->trackService()->track($child, 'Publish');

        $this->assertSame([(int)$parent->ID], $this->joined($record));
    }

    public function testExactClassOnly()
    {
        $page = $this->makePage('References it');
        $plain = $this->makePlain('Base class');
        $subclass = PlainRecordableSubclass::create(['Title' => 'Subclass']);
        $subclass->write();
        $this->resetTracking();
        $page->RelatedID = $plain->ID;
        $page->write();
        $this->resetTracking();

        $plain->Notes = 'changed';
        $plain->write();
        $this->assertSame([(int)$page->ID], $this->joined($this->lastRecord()));

        // the page has_one is declared to the base class and the scan compares class names exactly, so a page that
        // points at an instance of a subclass is not found
        $this->resetTracking();
        $page->RelatedID = $subclass->ID;
        $page->write();
        $this->resetTracking();
        $subclass->Flavour = 'changed';
        $subclass->write();
        $this->assertSame([], $this->joined($this->lastRecord()));
    }

    public function testKeyCollisionSkipsPageLegacy()
    {
        $plain = $this->makePlain('Collides');
        $first = $this->makePage('First', ['RelatedID' => $plain->ID]);
        $second = $this->makePage('Second');
        $third = $this->makePage('Third', ['RelatedID' => $plain->ID]);
        $this->assertEquals($first->ID + 1, $second->ID);
        $this->assertEquals($second->ID + 1, $third->ID);
        $plain->PageID = $second->ID;
        $plain->write();
        $this->resetTracking();

        $plain->Notes = 'changed';
        $plain->write();

        // The page from the has_one is stored under its id (the second page). The pages found by scanning are then
        // appended after the highest key, so the first page takes the key that equals the id of the third page, and
        // the third page is skipped as "already present"
        $record = $this->lastRecord();
        $this->assertSame([(int)$second->ID, (int)$first->ID], $this->computed($record));
        $this->assertEqualsCanonicalizing([(int)$second->ID, (int)$first->ID], $this->joined($record));
        $this->assertNotContains((int)$third->ID, $this->joined($record));
    }

    public function testIdZeroNeverAdded()
    {
        $plain = $this->makePlain('No page set');

        $plain->Notes = 'changed';
        $plain->write();

        $record = $this->lastRecord();
        $this->assertEquals(0, $plain->PageID);
        $this->assertSame([], $this->joined($record));
        $this->assertSame([], $record->getAffectedPageRecords());
    }

    public function testJoinRowsWritten()
    {
        $page = $this->makePage('Joined');
        $object = $this->makeObject('Joins', ['PageID' => $page->ID]);
        $object->Title = 'Joins, renamed';
        $object->write();

        $record = $this->lastRecord();

        $rows = DB::query(
            'SELECT "DataChangeRecordID", "SiteTreeID" FROM "DataChangeRecord_AffectedPages"'
            . ' WHERE "DataChangeRecordID" = ' . (int)$record->ID
        )->map();
        $this->assertEquals([$record->ID => $page->ID], $rows);
    }

    public function testJoinRowsAreAFrozenCopyOfTheLookup()
    {
        $page = $this->makePage('Linked later');
        $object = $this->makeObject('Unlinked at first');
        $object->Title = 'Unlinked, renamed';
        $object->write();
        $record = $this->lastRecord();
        $this->assertSame([], $this->joined($record));

        $object->PageID = $page->ID;
        $object->write();

        // the older record keeps no pages, while a fresh lookup for it now finds one
        $this->assertSame([], $this->joined(DataChangeRecord::get()->byID($record->ID)));
        $this->assertSame([(int)$page->ID], $this->computed(DataChangeRecord::get()->byID($record->ID)));
    }

    public function testPublishOfAPlainRecordAttachesPages()
    {
        $page = $this->makePage('Owner');
        $object = $this->makeObject('Published thing');
        $object->PageID = $page->ID;
        $object->write();
        $this->resetTracking();

        $object->publishRecursive();

        $record = $this->lastRecord();
        $this->assertSame('Publish', $record->ChangeType);
        $this->assertSame([(int)$page->ID], $this->joined($record));
        $this->assertInstanceOf(TrackedObject::class, $record->ChangeRecord());
    }
}
