<?php

namespace Symbiote\DataChange\Tests\Characterization;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\DataChange\Tests\Fixtures\TrackedPage;

/**
 * The site extension writes the time of a Publish record into the live row of the affected pages
 */
class LiveLastEditedBumpTest extends CharacterizationTestCase
{
    private const OLD_LIVE = '2001-02-03 04:05:06';

    private function live(TrackedPage $page): ?string
    {
        return DB::query('SELECT "LastEdited" FROM "SiteTree_Live" WHERE "ID" = ' . (int)$page->ID)->value();
    }

    private function draft(TrackedPage $page): ?string
    {
        return DB::query('SELECT "LastEdited" FROM "SiteTree" WHERE "ID" = ' . (int)$page->ID)->value();
    }

    private function agedLivePage(): TrackedPage
    {
        $page = $this->makePage('Published page');
        $page->publishRecursive();
        $this->resetTracking();
        DB::query('UPDATE "SiteTree_Live" SET "LastEdited" = \'' . self::OLD_LIVE . '\' WHERE "ID" = ' . (int)$page->ID);

        return $page;
    }

    public function testPublishNonPageBumpsLiveEqualsDcrCreated()
    {
        $page = $this->agedLivePage();
        $object = $this->makeObject('Published thing', ['PageID' => $page->ID]);

        DBDatetime::set_mock_now('2032-04-05 06:07:08');
        try {
            $object->publishRecursive();
        } finally {
            DBDatetime::clear_mock_now();
        }

        $record = $this->lastRecord();
        $this->assertSame('Publish', $record->ChangeType);
        $this->assertSame('2032-04-05 06:07:08', $record->Created);
        $this->assertSame($record->Created, $this->live($page));
    }

    public function testDraftUntouched()
    {
        $page = $this->agedLivePage();
        $draftBefore = $this->draft($page);
        $object = $this->makeObject('Published thing', ['PageID' => $page->ID]);

        DBDatetime::set_mock_now('2032-04-05 06:07:08');
        try {
            $object->publishRecursive();
        } finally {
            DBDatetime::clear_mock_now();
        }

        $this->assertSame($draftBefore, $this->draft($page));
    }

    public function testUnpublishedPageNotBumped()
    {
        $page = $this->makePage('Draft only');
        $object = $this->makeObject('Published thing', ['PageID' => $page->ID]);

        $object->publishRecursive();

        $this->assertNull($this->live($page));
    }

    public function testOtherTypesNoBumpByDefault()
    {
        $page = $this->agedLivePage();
        $object = $this->makeObject('Changed thing', ['PageID' => $page->ID]);
        $object->publishRecursive();
        $this->resetTracking();
        DB::query('UPDATE "SiteTree_Live" SET "LastEdited" = \'' . self::OLD_LIVE . '\' WHERE "ID" = ' . (int)$page->ID);

        DBDatetime::set_mock_now('2033-01-01 00:00:00');
        try {
            $object->Title = 'Changed thing, renamed';
            $object->write();
            $this->resetTracking();
            $object->doUnpublish();
        } finally {
            DBDatetime::clear_mock_now();
        }

        $this->assertContains('Change', $this->types($object));
        $this->assertContains('Unpublish', $this->types($object));
        $this->assertContains('Delete from Live', $this->types($object));
        $this->assertSame(self::OLD_LIVE, $this->live($page));
    }

    public function testPageOwnRecordIsNotBumped()
    {
        $page = $this->agedLivePage();

        DBDatetime::set_mock_now('2034-01-01 00:00:00');
        try {
            $page->Title = 'Page, renamed';
            $page->write();
            $this->resetTracking();
            $page->publishRecursive();
        } finally {
            DBDatetime::clear_mock_now();
        }

        // the bump skips records that are pages, but publishing the page itself writes the live row
        $this->assertSame('2034-01-01 00:00:00', $this->live($page));
        $this->assertSame('Publish', $this->lastRecord()->ChangeType);
    }
}
