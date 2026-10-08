<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ManyManyThroughList;
use SilverStripe\Security\Security;
use Dynamic\ChangeTracker\ORM\TrackedManyManyList;
use Dynamic\ChangeTracker\Tests\Fixtures\ThroughOwner;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedChild;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedPage;
use Dynamic\ChangeTracker\Tests\TestTrackedUnderscoreChild;
use Dynamic\ChangeTracker\Tests\TestTrackedUnderscoreObject;

/**
 * What the replacement many_many list records today
 */
class TrackedManyManyListTest extends CharacterizationTestCase
{
    private const OLD_LIVE = '2001-02-03 04:05:06';

    /**
     * @return string[] the change types of the add and remove rows, oldest first
     */
    private function mmTypes(): array
    {
        return array_values(array_filter($this->types(), function ($type) {
            return strpos($type, 'Add ') === 0 || strpos($type, 'Remove ') === 0;
        }));
    }

    private function questions(): int
    {
        return (int)DB::query("SHOW SESSION STATUS LIKE 'Questions'")->record()['Value'];
    }

    private function liveEdited(TrackedPage $page): ?string
    {
        return DB::query('SELECT "LastEdited" FROM "SiteTree_Live" WHERE "ID" = ' . (int)$page->ID)->value();
    }

    private function ageLive(TrackedPage $page): void
    {
        DB::query(
            'UPDATE "SiteTree_Live" SET "LastEdited" = \'' . self::OLD_LIVE . '\' WHERE "ID" = ' . (int)$page->ID
        );
    }

    public function testSwapAppliesToAllManyMany()
    {
        $page = $this->makePage('Owner');
        $this->assertSame(TrackedManyManyList::class, get_class($page->Kids()), 'The module installs the swap itself');

        $this->trackRelationships(['TrackedPage_Kids']);

        $this->assertInstanceOf(TrackedManyManyList::class, $page->Kids());
        $this->assertInstanceOf(TrackedManyManyList::class, $page->Plains());
        // Member has its own list class for Groups, which the swap does not reach
        $this->assertNotInstanceOf(TrackedManyManyList::class, Security::getCurrentUser()->Groups());
        $this->assertInstanceOf(ManyManyList::class, Security::getCurrentUser()->Groups());
        $this->assertInstanceOf(TrackedManyManyList::class, $this->lastRecord()->AffectedPages());
        $this->assertSame('TrackedPage_Kids', $page->Kids()->getJoinTable());
    }

    public function testUntrackedJoinNoRecordButByIdQueryStillRuns()
    {
        $page = $this->makePage('Owner');
        $warmUp = $this->makeChild('Warm up');
        $first = $this->makeChild('First');
        $second = $this->makeChild('Second');

        Injector::inst()->load([ManyManyList::class => ['class' => ManyManyList::class]]);
        $page->Kids()->add($warmUp);
        $before = $this->questions();
        $page->Kids()->add($first);
        $stock = $this->questions() - $before;

        $this->trackRelationships([]);
        $page = TrackedPage::get()->byID($page->ID);
        $page->Kids()->add($warmUp);
        $before = $this->questions();
        $page->Kids()->add($second);
        $tracked = $this->questions() - $before;

        $this->assertSame([], $this->mmTypes());
        $this->assertSame(1, $tracked - $stock, 'Every add looks the item up before it checks the join is tracked');
    }

    public function testAddObjectExactChangeType()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid one');
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Kids()->add($child);

        $this->assertSame(['Add "kid one" to Kids'], $this->mmTypes());
        $record = $this->lastRecord();
        $this->assertSame(TrackedPage::class, $record->ChangeRecordClass);
        $this->assertEquals($page->ID, $record->ChangeRecordID);
        $this->assertSame('Owner', $record->ObjectTitle);
        $this->assertSame('null', $record->Before);
        $this->assertSame('null', $record->After);
        $this->assertSame([(string)$page->ID], array_map('strval', $record->AffectedPages()->column('ID')));
        $this->assertSame([$child->ID], array_map('intval', $page->Kids()->column('ID')));
    }

    public function testAddIntId()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid two');
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Kids()->add($child->ID);

        $this->assertSame(['Add "kid two" to Kids'], $this->mmTypes());
        $this->assertSame([], $this->takeWarnings());
    }

    public function testAddNumericStringId()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid three');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        // is_int() rejects form style numeric strings, which then read ->ID on a string. The lookup fails, so an
        // item that is already linked is recorded again
        $page->Kids()->add((string)$child->ID);

        $this->assertSame(['Add "kid three" to Kids', 'Add "kid three" to Kids'], $this->mmTypes());
        $this->assertSame(
            ['TrackedManyManyList.php: Attempt to read property "ID" on string'],
            $this->describeWarnings($this->takeWarnings())
        );
        $this->assertCount(1, $page->Kids());
    }

    public function testAddExistingNoExtraNoRecord()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        $page->Kids()->add($child);

        $this->assertSame(['Add "kid" to Kids'], $this->mmTypes());
    }

    public function testAddExistingSameExtraNoRecord()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_SortedKids']);
        $page->SortedKids()->add($child, ['Sort' => 1]);

        $page->SortedKids()->add($child, ['Sort' => 1]);

        $this->assertSame(['Add "kid" to SortedKids'], $this->mmTypes());
    }

    public function testAddExistingChangedExtraRecords()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_SortedKids']);
        $page->SortedKids()->add($child, ['Sort' => 1]);

        $page->SortedKids()->add($child, ['Sort' => 2]);

        $this->assertSame(['Add "kid" to SortedKids', 'Add "kid" to SortedKids'], $this->mmTypes());
        $this->assertEquals(['Sort' => 2], $page->SortedKids()->getExtraData('SortedKids', $child->ID));
    }

    public function testAddExistingByIdAlwaysRecords()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_SortedKids']);
        $page->SortedKids()->add($child, ['Sort' => 1]);

        // With an id the extra data lookup reads ->ID on an int, finds nothing, and counts as a change
        $page->SortedKids()->add($child->ID, ['Sort' => 1]);

        $this->assertSame(['Add "kid" to SortedKids', 'Add "kid" to SortedKids'], $this->mmTypes());
        $this->assertSame(
            ['TrackedManyManyList.php: Attempt to read property "ID" on int'],
            $this->describeWarnings($this->takeWarnings())
        );
    }

    public function testRemoveExactChangeType()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        $page->Kids()->remove($child);

        $this->assertSame(['Add "kid" to Kids', 'Remove "kid" from Kids'], $this->mmTypes());
        $this->assertCount(0, $page->Kids());
    }

    public function testRemoveOfUnlinkedItemStillRecords()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('never linked');
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Kids()->remove($child);

        $this->assertSame(['Remove "never linked" from Kids'], $this->mmTypes());
    }

    public function testRemoveIntRecordsThenParentThrows()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        try {
            $page->Kids()->remove($child->ID);
            $this->fail('The stock list rejects an id in remove()');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('remove()', $e->getMessage());
        }

        $this->assertSame(['Add "kid" to Kids', 'Remove "kid" from Kids'], $this->mmTypes());
        $this->assertCount(1, $page->Kids(), 'The link is still there');
    }

    public function testRemoveByIdNotRecorded()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        $page->Kids()->removeByID($child->ID);

        $this->assertSame(['Add "kid" to Kids'], $this->mmTypes());
        $this->assertCount(0, $page->Kids());
    }

    public function testSetByIdListAddsRecordedRemovalsNot()
    {
        $page = $this->makePage('Owner');
        $one = $this->makeChild('one');
        $two = $this->makeChild('two');
        $three = $this->makeChild('three');
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Kids()->setByIDList([$one->ID, $two->ID]);
        $page->Kids()->setByIDList([$two->ID, $three->ID]);

        $this->assertSame(['Add "one" to Kids', 'Add "two" to Kids', 'Add "three" to Kids'], $this->mmTypes());
        $this->assertEqualsCanonicalizing([$two->ID, $three->ID], array_map('intval', $page->Kids()->column('ID')));
        $this->assertSame([], $this->takeWarnings());
    }

    public function testSetByIdListWithStringIdsWarns()
    {
        $page = $this->makePage('Owner');
        $one = $this->makeChild('one');
        $two = $this->makeChild('two');
        $this->trackRelationships(['TrackedPage_Kids']);

        // what a CheckboxSetField or TagField submits
        $page->Kids()->setByIDList([(string)$one->ID, (string)$two->ID]);

        $this->assertSame(['Add "one" to Kids', 'Add "two" to Kids'], $this->mmTypes());
        $this->assertSame(
            array_fill(0, 2, 'TrackedManyManyList.php: Attempt to read property "ID" on string'),
            $this->describeWarnings($this->takeWarnings())
        );
    }

    public function testRemoveAllNotRecorded()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);

        $page->Kids()->removeAll();

        $this->assertSame(['Add "kid" to Kids'], $this->mmTypes());
        $this->assertCount(0, $page->Kids());
    }

    public function testUnderscoreJoinTables()
    {
        $owner = TestTrackedUnderscoreObject::create(['Title' => 'Underscore owner']);
        $owner->write();
        $this->resetTracking();
        $kid = TestTrackedUnderscoreChild::create(['Title' => 'underscore kid']);
        $kid->write();
        $this->trackRelationships(['Dynamic_ChangeTracker_Tests_TestTrackedUnderscoreObject_Kids']);

        $this->assertSame(
            'Dynamic_ChangeTracker_Tests_TestTrackedUnderscoreObject_Kids',
            $owner->Kids()->getJoinTable()
        );
        $owner->Kids()->add($kid);

        $this->assertSame(['New', 'Add "underscore kid" to Kids'], $this->types($owner));
    }

    public function testBelongsManyManyCurrentBehaviour()
    {
        $ownerA = $this->makePage('Page A');
        $ownerB = $this->makePage('Page B');
        // a child that happens to share its id with page A
        $child = TrackedChild::create(['Title' => 'Shared id']);
        $child->ID = $ownerA->ID;
        $child->write(false, true);
        $this->resetTracking();
        $this->trackRelationships(['TrackedPage_Kids']);

        // adding from the child's side of the relation: the list's foreign id is the child's id, but the tracker
        // treats it as the id of the owning page
        $child->Parents()->add($ownerB);

        $this->assertCount(1, $ownerB->Kids(), 'The link itself is made correctly');
        $this->assertSame(['Add "Page B" to Kids'], $this->mmTypes());
        $record = $this->lastRecord();
        $this->assertEquals($ownerA->ID, $record->ChangeRecordID);
        $this->assertSame('Page A', $record->ObjectTitle);
    }

    public function testBelongsManyManyWithoutMatchingOwnerRecordsNothing()
    {
        $this->makePage('Page A');
        $ownerB = $this->makePage('Page B');
        // an id that no page has
        $child = TrackedChild::create(['Title' => 'Someone else']);
        $child->ID = 900000;
        $child->write(false, true);
        $this->resetTracking();
        $this->trackRelationships(['TrackedPage_Kids']);

        $child->Parents()->add($ownerB);

        $this->assertCount(1, $ownerB->Kids());
        $this->assertSame([], $this->mmTypes());
    }

    public function testThroughListNotTracked()
    {
        $owner = ThroughOwner::create(['Title' => 'Through owner']);
        $owner->write();
        $this->resetTracking();
        $child = $this->makeChild('through kid');
        $this->trackRelationships(['ThroughOwner_Children']);

        $this->assertInstanceOf(ManyManyThroughList::class, $owner->Children());
        $this->assertNotInstanceOf(TrackedManyManyList::class, $owner->Children());
        $owner->Children()->add($child);

        $this->assertCount(1, $owner->Children());
        $this->assertSame(['New'], $this->types());
    }

    public function testLiveBumpOnVersionedPublishedItem()
    {
        $page = $this->makePage('Owner');
        $page->publishRecursive();
        $child = $this->makeChild('kid');
        $child->publishRecursive();
        $this->resetTracking();
        $this->ageLive($page);
        $this->trackRelationships(['TrackedPage_Kids']);

        DBDatetime::set_mock_now('2030-06-01 12:00:00');
        try {
            $page->Kids()->add($child);
        } finally {
            DBDatetime::clear_mock_now();
        }

        $record = $this->lastRecord();
        $this->assertSame('2030-06-01 12:00:00', $record->Created);
        $this->assertSame('2030-06-01 12:00:00', $this->liveEdited($page));
    }

    public function testLiveBumpAlsoOnRemove()
    {
        $page = $this->makePage('Owner');
        $page->publishRecursive();
        $child = $this->makeChild('kid');
        $child->publishRecursive();
        $this->resetTracking();
        $this->trackRelationships(['TrackedPage_Kids']);
        $page->Kids()->add($child);
        $this->resetTracking();
        $this->ageLive($page);

        DBDatetime::set_mock_now('2031-01-02 03:04:05');
        try {
            $page->Kids()->remove($child);
        } finally {
            DBDatetime::clear_mock_now();
        }

        $this->assertSame('2031-01-02 03:04:05', $this->liveEdited($page));
    }

    public function testNoBumpWhenUnpublishedOrUnversioned()
    {
        $published = $this->makePage('Published owner');
        $published->publishRecursive();
        $draftOnly = $this->makePage('Draft owner');
        $unpublishedChild = $this->makeChild('draft kid');
        $publishedChild = $this->makeChild('published kid');
        $publishedChild->publishRecursive();
        $plain = $this->makePlain('unversioned');
        $this->resetTracking();
        $this->ageLive($published);
        $this->trackRelationships(['TrackedPage_Kids', 'TrackedPage_Plains']);

        // an item that was never published
        $published->Kids()->add($unpublishedChild);
        // an item without the Versioned extension
        $published->Plains()->add($plain);
        // an owner that is not published: there is no live row to update
        $draftOnly->Kids()->add($publishedChild);

        $this->assertSame(
            ['Add "draft kid" to Kids', 'Add "unversioned" to Plains', 'Add "published kid" to Kids'],
            $this->mmTypes()
        );
        $this->assertSame(self::OLD_LIVE, $this->liveEdited($published));
        $this->assertNull($this->liveEdited($draftOnly));
    }

    public function testLongTitleTruncated()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild(str_repeat('t', 400));
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Kids()->add($child);

        $type = $this->lastRecord()->ChangeType;
        $this->assertSame(255, strlen($type));
        $this->assertStringStartsWith('Add "ttt', $type);
        $this->assertSame([], $this->takeWarnings());
    }

    public function testNonVersionedOwnerIsTrackedToo()
    {
        $plain = $this->makePlain('plain owner');
        $page = $this->makePage('Linked page');
        $this->trackRelationships(['TrackedPage_Plains']);

        $page->Plains()->add($plain);

        $this->assertSame(['Add "plain owner" to Plains'], $this->mmTypes());
    }

    public function testTrackedRelationshipsPropertyIsReadFromTheInjectorService()
    {
        $page = $this->makePage('Owner');
        $child = $this->makeChild('kid');
        Injector::inst()->load([
            ManyManyList::class => [
                'class' => TrackedManyManyList::class,
                'properties' => ['trackedRelationships' => ['TrackedPage_Kids']],
            ],
        ]);

        $list = $page->Kids();

        $this->assertSame(['TrackedPage_Kids'], $list->trackedRelationships);
        $list->add($child);
        $this->assertSame(['Add "kid" to Kids'], $this->mmTypes());
    }
}
