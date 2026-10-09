<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class LegacySiteDataChangeRecordExtension extends Extension
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
    public function onAfterWrite(): void
    {
        if ($this->getOwner()->ChangeType == 'Publish' && !$this->getOwner()->ChangeRecord() instanceof SiteTree) {
            $affectedPages = $this->getAffectedPageRecords();
            $changeRecordCreated = $this->getOwner()->Created;

            foreach ($affectedPages as $page) {
                if ($page && $page->isPublished()) {
                    // Update the LastEdited value for the SiteTree_Live record directly via SQL
                    DB::query(sprintf(
                        "UPDATE \"SiteTree_Live\" SET \"LastEdited\" = '%s' WHERE \"ID\" = %d",
                        $changeRecordCreated,
                        $page->ID
                    ));
                }
            }
        }
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
            $referrer = $record->Referrer;

            // Use CurrentURL if available, otherwise use Referrer
            $pageURL = $currentURL ?: $referrer;
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
     * @param $relations
     * @param $affectedPages
     * @return void
     */
    private function addRelatedPages($relations, &$affectedPages): void
    {
        foreach ($relations as $relation => $class) {
            if (is_subclass_of($class, SiteTree::class)) {
                $relatedPages = $this->asList($this->getOwner()->ChangeRecord()->$relation());
                foreach ($relatedPages as $page) {
                    if ($page instanceof SiteTree) {
                        if (!array_key_exists($page->ID, $affectedPages) && $page->ID > 0) {
                            $affectedPages[$page->ID] = $page;
                        }
                    }
                }
            } elseif (class_exists(BaseElement::class) && is_subclass_of($class, BaseElement::class)) {
                $relatedElements = $this->asList($this->getOwner()->ChangeRecord()->$relation());
                foreach ($relatedElements as $element) {
                    if ($element->hasMethod('getPage')) {
                        $elementPage = $element->getPage();
                        if ($elementPage && $elementPage instanceof SiteTree) {
                            if (!array_key_exists($elementPage->ID, $affectedPages) && $elementPage->ID > 0) {
                                $affectedPages[$elementPage->ID] = $elementPage;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Silverstripe 5 iterated a has_one object as a list of itself. Silverstripe 6 models are not iterable, so a
     * has_one object is wrapped as a list here, as the module does. Without this the site copy finds no has_one pages.
     *
     * @param mixed $relation a list, a single object, or nothing
     * @return iterable
     */
    private function asList($relation): iterable
    {
        if ($relation instanceof SS_List) {
            return $relation;
        }
        if ($relation instanceof DataObject) {
            return [$relation];
        }
        return [];
    }

    /**
     * @param $affectedPages
     * @return void
     */
    private function findRelatedPagesByHasOne(&$affectedPages): void
    {
        // Get all SiteTree objects
        $siteTreeObjects = SiteTree::get();

        foreach ($siteTreeObjects as $siteTree) {
            // Get has_one relationships of the SiteTree object
            $hasOneRelations = $siteTree->hasOne();

            foreach ($hasOneRelations as $relation => $class) {
                // Check if the current record is related to the SiteTree object
                if ($class === get_class($this->getOwner()->ChangeRecord()) && $siteTree->$relation()->ID === $this->getOwner()->ChangeRecord()->ID) {
                    if (!array_key_exists($siteTree->ID, $affectedPages) && $siteTree->ID > 0) {
                        $affectedPages[] = $siteTree;
                    }
                }
            }
        }
    }

    /**
     * @param $affectedPages
     * @return void
     */
    private function findRelatedPagesByManyMany(&$affectedPages): void
    {
        // Get all SiteTree objects
        $siteTreeObjects = SiteTree::get();

        foreach ($siteTreeObjects as $siteTree) {
            // Get many_many relationships of the SiteTree object
            $manyManyRelations = $siteTree->manyMany();

            foreach ($manyManyRelations as $relation => $class) {
                // Check if the current record is related to the SiteTree object
                if ($class === get_class($this->getOwner()->ChangeRecord())) {
                    $relatedObjects = $siteTree->$relation();
                    foreach ($relatedObjects as $relatedObject) {
                        if ($relatedObject->ID === $this->getOwner()->ChangeRecord()->ID) {
                            if (!array_key_exists($siteTree->ID, $affectedPages) && $siteTree->ID > 0) {
                                $affectedPages[] = $siteTree;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array|null
     */
    public function getAffectedPageRecords(): ?array
    {
        $record = $this->getOwner()->ChangeRecord();
        if (!$record) {
            return null;
        }

        // Check if the record is a descendant of SiteTree
        if ($record instanceof SiteTree && $record->ID > 0) {
            return [$record];
        }

        // Extrapolate affected pages
        $affectedPages = [];

        // Check for has_one relationships
        $this->addRelatedPages($record->hasOne(), $affectedPages);

        // Check for has_many relationships
        $this->addRelatedPages($record->hasMany(), $affectedPages);

        // Check for many_many relationships
        $this->addRelatedPages($record->manyMany(), $affectedPages);

        // Check if the record belongs to an Element and get the Element's page
        if ($record->hasMethod('getPage')) {
            $elementPage = $record->getPage();
            if ($elementPage && $elementPage instanceof SiteTree) {
                if (!array_key_exists($elementPage->ID, $affectedPages) && $elementPage->ID > 0) {
                    $affectedPages[] = $elementPage;
                }
            }
        }

        // Find related pages by checking has_one relationships on other objects
        $this->findRelatedPagesByHasOne($affectedPages);

        // Find related pages by checking many_many relationships on other objects
        $this->findRelatedPagesByManyMany($affectedPages);

        return $affectedPages;
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
}
