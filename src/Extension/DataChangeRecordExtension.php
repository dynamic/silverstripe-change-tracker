<?php

namespace Dynamic\ChangeTracker\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use Dynamic\ChangeTracker\Service\AffectedPagesService;
use Dynamic\ChangeTracker\Service\LiveLastEditedPropagator;
use WeakMap;

/**
 * Adds the pages a change affects to DataChangeRecord: the Page URL and friendlier columns in the Data Changes admin,
 * the AffectedPages join rows written by DataChangeRecord::track(), and the update of the live LastEdited value of
 * those pages when a record that is not a page is published.
 *
 * Applied to DataChangeRecord by the module's configuration.
 */
class DataChangeRecordExtension extends Extension
{
    /**
     * @var string[]
     */
    private static array $summary_fields = [
        'ChangeType' => 'Change Type',
        'ChangeRecordClass' => 'Record Class',
        'ChangeRecordID' => 'Record ID',
        'ObjectTitle' => 'Record Title',
        'PageURL' => 'Page URL',
        'ChangedBy.Title' => 'User',
        'Created' => 'Modification Date',
    ];

    /**
     * Affected pages per change record object. Extension instances are shared by every owner, so the result is keyed
     * by the owner and dropped together with it. It is also dropped whenever the owner is written.
     *
     * @var WeakMap|null
     */
    private ?WeakMap $affectedPagesMemo = null;

    /**
     * @param FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName([
            'ChangedFieldID',
            'ChangeFieldClassName',
            'ChangeFieldRecordClassName',
            'ChangeFieldShowInMenus',
            'ChangeFieldShowInSearch',
            'ChangeFieldCanViewType',
            'ChangeFieldCanEditType',
            'ChangeFieldCreated',
            'ChangedFieldSearchContent',
        ]);
    }

    /**
     * @param $fields
     * @return void
     */
    public function updateSummaryFields(&$fields): void
    {
        if (array_key_exists('ChangeType', $fields)) {
            $value = $fields['ChangeType'];
            $keys = array_keys($fields);
            $index = array_search('ChangeType', $keys);

            unset($fields['ChangeType']);

            $fields = array_slice($fields, 0, $index, true) +
                ['ChangeTypeNice' => $value] +
                array_slice($fields, $index, null, true);
        }

        if (array_key_exists('ChangeRecordClass', $fields)) {
            $value = $fields['ChangeRecordClass'];
            $keys = array_keys($fields);
            $index = array_search('ChangeRecordClass', $keys);

            unset($fields['ChangeRecordClass']);

            $fields = array_slice($fields, 0, $index, true) +
                ['RecordClassSingularName' => 'Record Type'] +
                array_slice($fields, $index, null, true);
        }
    }

    /**
     * @return void
     */
    public function onBeforeWrite(): void
    {
        $this->forgetAffectedPages();
    }

    /**
     * @return void
     */
    public function onAfterWrite(): void
    {
        LiveLastEditedPropagator::singleton()->onChangeRecordWritten($this->getOwner());
    }

    /**
     * @return string
     */
    public function getChangeTypeNice(): string
    {
        if ($this->getOwner()->ChangeType === 'Change') {
            return 'Saved';
        }

        return $this->getOwner()->ChangeType;
    }

    /**
     * @return null|string
     */
    public function getRecordClassSingularName(): ?string
    {
        $record = $this->getOwner()->ChangeRecord();
        if ($record) {
            return $record->singular_name();
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getPageURL(): ?string
    {
        $record = $this->getOwner();

        if ($record->AffectedPages()->count()) {
            $urls = [];

            foreach ($record->AffectedPages() as $page) {
                $urls[] = $page->AbsoluteLink();
            }

            return implode(', ', $urls);
        }

        if ($record) {
            $currentURL = $record->CurrentURL;
            $referer = $record->Referer;

            // Use CurrentURL if available, otherwise use Referer
            $pageURL = $currentURL ?: $referer;
            $pageURLs = ''; // This should be a string, not an array

            if ($pageURL) {
                // Check if the URL is a CMS link with a page ID
                if (preg_match('/\/admin\/pages\/edit\/EditForm\/(\d+)/', $pageURL, $matches)) {
                    $pageID = $matches[1];

                    // Query the page using the extracted page ID
                    $page = SiteTree::get()->byID($pageID);
                    if ($page) {
                        $pageURLs = $page->AbsoluteLink(); // Assign the URL directly to $pageURLs
                    }
                }

                $pages = $this->getAffectedPageURLs() ?: [];

                if ($pages && is_array($pages) && $pageURLs !== '') {
                    // Ensure $pageURLs is not duplicated
                    if (!in_array($pageURLs, $pages)) {
                        array_unshift($pages, $pageURLs);
                    }
                }

                if (is_array($pages)) {
                    return implode(', ', $pages);
                }

                return $pages;
            }
        }

        return 'No pages affected';
    }

    /**
     * The pages this change affects, worked out by AffectedPagesService. The result is kept for the owner object
     * until it is written again, so the write and the join rows that follow it share one lookup.
     *
     * @return array|null
     */
    public function getAffectedPageRecords(): ?array
    {
        $owner = $this->getOwner();
        if ($this->affectedPagesMemo === null) {
            $this->affectedPagesMemo = new WeakMap();
        }
        if (!$this->affectedPagesMemo->offsetExists($owner)) {
            // wrapped, because a WeakMap entry holding null reads as missing
            $this->affectedPagesMemo[$owner] = [AffectedPagesService::singleton()->getAffectedPageRecords($owner)];
        }
        return $this->affectedPagesMemo[$owner][0];
    }

    /**
     * @return array|string
     */
    public function getAffectedPageURLs(): array|string
    {
        $affectedPages = $this->getAffectedPageRecords();
        if (!$affectedPages) {
            return 'No pages affected';
        }

        $pageURLs = [];
        foreach ($affectedPages as $page) {
            if ($page instanceof SiteTree) {
                if (!in_array($page->AbsoluteLink(), $pageURLs)) {
                    $pageURLs[] = $page->AbsoluteLink();
                }
            }
        }

        return $pageURLs;
    }

    private function forgetAffectedPages(): void
    {
        if ($this->affectedPagesMemo !== null) {
            unset($this->affectedPagesMemo[$this->getOwner()]);
        }
    }
}
