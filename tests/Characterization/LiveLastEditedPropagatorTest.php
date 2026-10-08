<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Service\LiveLastEditedPropagator;
use Dynamic\ChangeTracker\Tests\Fixtures\LegacySiteDataChangeRecordExtension;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedPage;

/**
 * The live LastEdited update writes the same rows and values as the raw query the sites ran
 */
class LiveLastEditedPropagatorTest extends CharacterizationTestCase
{
    private const OLD_LIVE = '2001-02-03 04:05:06';

    /**
     * @return string[] page id => live LastEdited, for every live page
     */
    private function liveMap(): array
    {
        return DB::query('SELECT "ID", "LastEdited" FROM "SiteTree_Live" ORDER BY "ID"')->map();
    }

    /**
     * @return string[] page id => draft LastEdited, for every page
     */
    private function draftMap(): array
    {
        return DB::query('SELECT "ID", "LastEdited" FROM "SiteTree" ORDER BY "ID"')->map();
    }

    private function ageAllLive(): void
    {
        DB::query('UPDATE "SiteTree_Live" SET "LastEdited" = \'' . self::OLD_LIVE . '\'');
    }

    private function publishedPage(string $title): TrackedPage
    {
        $page = $this->makePage($title);
        $page->publishRecursive();
        $this->resetTracking();
        return $page;
    }

    public function testUpdateLivePageMatchesTheSitesQuery()
    {
        $site = $this->publishedPage('Updated by the site query');
        $module = $this->publishedPage('Updated by the module');
        $this->publishedPage('Left alone');
        $this->ageAllLive();
        $draft = $this->draftMap();

        DB::query(sprintf(
            "UPDATE \"SiteTree_Live\" SET \"LastEdited\" = '%s' WHERE \"ID\" = %d",
            '2030-06-01 12:00:00',
            $site->ID
        ));
        LiveLastEditedPropagator::singleton()->updateLivePage($module->ID, '2030-06-01 12:00:00');

        $live = $this->liveMap();
        $this->assertSame('2030-06-01 12:00:00', $live[$site->ID]);
        $this->assertSame($live[$site->ID], $live[$module->ID]);
        $this->assertSame(
            [self::OLD_LIVE],
            array_values(array_unique(array_diff_key($live, [$site->ID => 1, $module->ID => 1])))
        );
        $this->assertSame($draft, $this->draftMap());
    }

    public function testValueIsPassedAsAParameter()
    {
        $page = $this->publishedPage('Quoted');
        $this->ageAllLive();

        LiveLastEditedPropagator::singleton()->updateLivePage(
            (string)$page->ID,
            "2030-06-01 12:00:00', \"Title\" = 'changed"
        );

        $title = DB::query('SELECT "Title" FROM "SiteTree_Live" WHERE "ID" = ' . (int)$page->ID)->value();
        $this->assertSame('Quoted', $title);
    }

    public function testPublishUpdatesTheSameRowsAsTheSiteExtension()
    {
        $linked = $this->publishedPage('Linked');
        $featuring = $this->publishedPage('Features it');
        $draftOnly = $this->makePage('Draft only');
        $object = $this->makeObject('Published thing', ['PageID' => $linked->ID]);
        $featuring->FeatureID = $object->ID;
        $featuring->write();
        $draftOnly->FeatureID = $object->ID;
        $draftOnly->write();
        $this->resetTracking();

        DBDatetime::set_mock_now('2032-04-05 06:07:08');
        try {
            $object->publishRecursive();
        } finally {
            DBDatetime::clear_mock_now();
        }
        $record = DataChangeRecord::get()
            ->filter(['ChangeType' => 'Publish', 'ChangeRecordClass' => get_class($object)])
            ->sort('ID', 'DESC')
            ->first();
        $this->assertSame('2032-04-05 06:07:08', $record->Created);

        $this->ageAllLive();
        $legacy = new LegacySiteDataChangeRecordExtension();
        $legacy->setOwner($record);
        $legacy->onAfterWrite();
        $legacy->clearOwner();
        $expected = $this->liveMap();

        $this->ageAllLive();
        LiveLastEditedPropagator::singleton()->onChangeRecordWritten($record);

        $this->assertSame($expected, $this->liveMap());
        $this->assertSame('2032-04-05 06:07:08', $expected[$linked->ID]);
        $this->assertSame('2032-04-05 06:07:08', $expected[$featuring->ID]);
        $this->assertArrayNotHasKey($draftOnly->ID, $expected);
    }

    public function testOtherChangeTypesUpdateNothing()
    {
        $page = $this->publishedPage('Linked');
        $object = $this->makeObject('Changed thing', ['PageID' => $page->ID]);
        $object->Title = 'Changed thing, renamed';
        $object->write();
        $record = DataChangeRecord::get()->byID($this->lastRecord()->ID);
        $this->assertSame('Change', $record->ChangeType);
        $this->ageAllLive();
        $before = $this->liveMap();

        LiveLastEditedPropagator::singleton()->onChangeRecordWritten($record);

        $this->assertSame($before, $this->liveMap());
    }
}
