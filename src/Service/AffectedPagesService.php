<?php

namespace Dynamic\ChangeTracker\Service;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Dynamic\ChangeTracker\Model\DataChangeRecord;

/**
 * Works out which pages a change record affects.
 *
 * This is the lookup the sites carried in their own DataChangeRecord extension, kept exactly as it was, including its
 * known quirks:
 * - has_one, has_many and many_many relations on the changed record are followed, but only when they point at a
 *   subclass of SiteTree (a relation declared to SiteTree itself is ignored) or of the configured element class;
 * - every page in the site is then scanned for a has_one or many_many relation declared to exactly the class of the
 *   changed record (subclasses are not matched);
 * - pages from the relations are keyed by id while pages from the scans are appended, so a scanned page can be
 *   skipped when its id equals a key already taken by an appended page.
 */
class AffectedPagesService
{
    use Configurable;
    use Injectable;

    /**
     * Base class of content blocks whose getPage() names the page they sit on. Set to the elemental BaseElement
     * class by _config/elemental.yml when that module is installed.
     *
     * @config
     * @var string|null
     */
    private static $element_class = null;

    /**
     * @param DataChangeRecord $changeRecord
     * @return array|null pages, or null when the changed record no longer exists
     */
    public function getAffectedPageRecords(DataChangeRecord $changeRecord): ?array
    {
        $record = $changeRecord->ChangeRecord();
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
        $this->addRelatedPages($changeRecord, $record->hasOne(), $affectedPages);

        // Check for has_many relationships
        $this->addRelatedPages($changeRecord, $record->hasMany(), $affectedPages);

        // Check for many_many relationships
        $this->addRelatedPages($changeRecord, $record->manyMany(), $affectedPages);

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
        $this->findRelatedPagesByHasOne($changeRecord, $affectedPages);

        // Find related pages by checking many_many relationships on other objects
        $this->findRelatedPagesByManyMany($changeRecord, $affectedPages);

        return $affectedPages;
    }

    /**
     * @param DataChangeRecord $changeRecord
     * @param array $relations relation name => class
     * @param array $affectedPages
     */
    protected function addRelatedPages(DataChangeRecord $changeRecord, $relations, &$affectedPages): void
    {
        $elementClass = static::config()->get('element_class');

        foreach ($relations as $relation => $class) {
            if (is_subclass_of($class, SiteTree::class)) {
                $relatedPages = $changeRecord->ChangeRecord()->$relation();
                foreach ($relatedPages as $page) {
                    if ($page instanceof SiteTree) {
                        if (!array_key_exists($page->ID, $affectedPages) && $page->ID > 0) {
                            $affectedPages[$page->ID] = $page;
                        }
                    }
                }
            } elseif ($elementClass && is_subclass_of($class, $elementClass)) {
                $relatedElements = $changeRecord->ChangeRecord()->$relation();
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
     * @param DataChangeRecord $changeRecord
     * @param array $affectedPages
     */
    protected function findRelatedPagesByHasOne(DataChangeRecord $changeRecord, &$affectedPages): void
    {
        // Get all SiteTree objects
        $siteTreeObjects = SiteTree::get();

        foreach ($siteTreeObjects as $siteTree) {
            // Get has_one relationships of the SiteTree object
            $hasOneRelations = $siteTree->hasOne();

            foreach ($hasOneRelations as $relation => $class) {
                // Check if the current record is related to the SiteTree object
                if (
                    $class === get_class($changeRecord->ChangeRecord())
                    && $siteTree->$relation()->ID === $changeRecord->ChangeRecord()->ID
                ) {
                    if (!array_key_exists($siteTree->ID, $affectedPages) && $siteTree->ID > 0) {
                        $affectedPages[] = $siteTree;
                    }
                }
            }
        }
    }

    /**
     * @param DataChangeRecord $changeRecord
     * @param array $affectedPages
     */
    protected function findRelatedPagesByManyMany(DataChangeRecord $changeRecord, &$affectedPages): void
    {
        // Get all SiteTree objects
        $siteTreeObjects = SiteTree::get();

        foreach ($siteTreeObjects as $siteTree) {
            // Get many_many relationships of the SiteTree object
            $manyManyRelations = $siteTree->manyMany();

            foreach ($manyManyRelations as $relation => $class) {
                // Check if the current record is related to the SiteTree object
                if ($class === get_class($changeRecord->ChangeRecord())) {
                    $relatedObjects = $siteTree->$relation();
                    foreach ($relatedObjects as $relatedObject) {
                        if ($relatedObject->ID === $changeRecord->ChangeRecord()->ID) {
                            if (!array_key_exists($siteTree->ID, $affectedPages) && $siteTree->ID > 0) {
                                $affectedPages[] = $siteTree;
                            }
                        }
                    }
                }
            }
        }
    }
}
