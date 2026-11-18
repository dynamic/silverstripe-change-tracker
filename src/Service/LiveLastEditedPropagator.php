<?php

namespace Dynamic\ChangeTracker\Service;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Versioned\Versioned;
use Dynamic\ChangeTracker\Model\DataChangeRecord;

/**
 * Copies the time of a change onto the live row of the pages it affects, so that a page reports a fresh LastEdited
 * when something it shows is published.
 *
 * Only the live table is written; the draft row of the page is left alone.
 */
class LiveLastEditedPropagator
{
    use Injectable;

    /**
     * After a change record is written: a Publish of a record that is not itself a page updates every published
     * page the record affects.
     *
     * @param DataChangeRecord $changeRecord
     */
    public function onChangeRecordWritten(DataChangeRecord $changeRecord): void
    {
        if ($changeRecord->ChangeType == 'Publish' && !$changeRecord->ChangeRecord() instanceof SiteTree) {
            $affectedPages = $changeRecord->getAffectedPageRecords();
            $changeRecordCreated = $changeRecord->Created;

            foreach ($affectedPages as $page) {
                if ($page && $page->isPublished()) {
                    $this->updateLivePage($page->ID, $changeRecordCreated);
                }
            }
        }
    }

    /**
     * After an item is added to or removed from a tracked many_many relation: every published page in the record's
     * AffectedPages is updated, provided the item is versioned and published.
     *
     * @param DataChangeRecord $changeRecord
     * @param DataObject $item
     */
    public function onManyManyChange(DataChangeRecord $changeRecord, DataObject $item): void
    {
        foreach ($changeRecord->AffectedPages() as $page) {
            if ($page && $page->isPublished() && $item->hasExtension(Versioned::class) && $item->isPublished()) {
                $this->updateLivePage($page->ID, $changeRecord->Created);
            }
        }
    }

    /**
     * Set the LastEdited value of one page's live row. The value is written as a string, as the sites' raw query did.
     *
     * @param int|string $pageID
     * @param string|null $lastEdited
     */
    public function updateLivePage($pageID, $lastEdited): void
    {
        SQLUpdate::create(
            '"SiteTree_Live"',
            ['"LastEdited"' => (string)$lastEdited],
            ['"ID"' => (int)$pageID]
        )->execute();
    }
}
