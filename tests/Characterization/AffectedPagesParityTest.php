<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Service\AffectedPagesService;
use Dynamic\ChangeTracker\Tests\Fixtures\ElementLike;
use Dynamic\ChangeTracker\Tests\Fixtures\LegacySiteDataChangeRecordExtension;
use Dynamic\ChangeTracker\Tests\Fixtures\PlainRecordableSubclass;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedPageSubclass;

/**
 * The module's affected pages lookup gives the same answer as the copy the sites carried, key for key, for every
 * record of a graph that exercises each branch of the lookup and its quirks.
 */
class AffectedPagesParityTest extends CharacterizationTestCase
{
    /**
     * @param array|null $pages
     * @return array|null key => page id
     */
    private static function ids(?array $pages): ?array
    {
        if ($pages === null) {
            return null;
        }
        return array_map(function ($page) {
            return [get_class($page), (int)$page->ID];
        }, $pages);
    }

    private function legacy(DataChangeRecord $record): ?array
    {
        $extension = new LegacySiteDataChangeRecordExtension();
        $extension->setOwner($record);
        try {
            return $extension->getAffectedPageRecords();
        } finally {
            $extension->clearOwner();
        }
    }

    public function testSameLookupAsTheSiteExtension()
    {
        $plain = $this->makePlain('Collides');
        $first = $this->makePage('First', ['RelatedID' => $plain->ID]);
        $second = $this->makePage('Second');
        $third = $this->makePage('Third', ['RelatedID' => $plain->ID]);
        $plain->PageID = $second->ID;
        $plain->write();
        $this->resetTracking();
        $plain->Notes = 'changed';
        $plain->write();
        $this->resetTracking();

        $object = $this->makeObject('Everywhere', ['PageID' => $first->ID, 'BasePageID' => $second->ID]);
        $third->FeatureID = $object->ID;
        $third->write();
        $this->resetTracking();
        $object->RelatedPages()->add($second);
        $object->Kids()->add($this->makeChild('kid'));
        $this->resetTracking();
        $object->Title = 'Everywhere, renamed';
        $object->write();
        $this->resetTracking();
        $object->publishRecursive();
        $this->resetTracking();

        $element = ElementLike::create(['Title' => 'Element', 'PageRef' => $third->ID]);
        $element->write();
        $this->resetTracking();

        $subclass = PlainRecordableSubclass::create(['Title' => 'Subclass']);
        $subclass->write();
        $this->resetTracking();

        $child = $this->makeChild('Reverse');
        $first->Kids()->add($child);
        $this->trackService()->track($child, 'Publish');
        $this->resetTracking();

        $sub = TrackedPageSubclass::create(['Title' => 'Subclass page']);
        $sub->write();
        $this->resetTracking();

        $object->delete();

        $service = AffectedPagesService::singleton();
        $compared = 0;
        foreach (DataChangeRecord::get()->sort('ID') as $record) {
            $this->assertSame(
                self::ids($this->legacy($record)),
                self::ids($service->getAffectedPageRecords($record)),
                $record->ChangeType . ' of ' . $record->ChangeRecordClass
            );
            $compared++;
        }
        $this->assertGreaterThan(10, $compared);
    }
}
