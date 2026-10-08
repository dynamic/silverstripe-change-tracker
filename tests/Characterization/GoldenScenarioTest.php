<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use SilverStripe\Versioned\Versioned;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedObject;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedPage;

/**
 * Scripted scenarios whose stored records are compared with golden files recorded from the module before it was
 * changed. Rows are normalised first: fixture classes are shortened, ids become labels, and payloads are cut down to
 * an allow-list of fixture fields, so the files do not depend on core columns, timestamps or auto increment values.
 */
class GoldenScenarioTest extends CharacterizationTestCase
{
    public function testPageLifecycle()
    {
        $page = TrackedPage::create(['Title' => 'Lifecycle page', 'Subtitle' => 'first']);
        $page->write();
        $this->label($page, 'page');
        $this->resetTracking();

        $page->Title = 'Lifecycle page, renamed';
        $page->write();
        $this->resetTracking();

        $page->publishRecursive();
        $this->resetTracking();

        $page->Subtitle = 'second';
        $page->write();
        $this->resetTracking();

        $page->doRevertToLive();
        $this->resetTracking();

        $page->rollbackSingle(1);
        $this->resetTracking();

        $page->doUnpublish();

        GoldenFile::assertMatches('page-lifecycle', $this->snapshotRecords());
    }

    public function testVersionedObjectLifecycle()
    {
        $page = $this->makePage('Owner page');
        $this->label($page, 'page');
        $object = TrackedObject::create([
            'Title' => 'Object',
            'Body' => 'one',
            'Password' => 'secret',
            'PageID' => $page->ID,
        ]);
        $object->write();
        $this->label($object, 'object');
        $this->resetTracking();

        $object->Body = 'two';
        $object->Secret = 'shown';
        $object->Password = 'changed secret';
        $object->write();
        $this->resetTracking();

        $object->publishRecursive();
        $this->resetTracking();

        $object->Title = 'Object, renamed';
        $object->write();
        $this->resetTracking();

        $object->doUnpublish();
        $this->resetTracking();

        $object->delete();

        GoldenFile::assertMatches('object-lifecycle', $this->snapshotRecords());
    }

    public function testManyManyScenario()
    {
        $page = $this->makePage('Owner page');
        $this->label($page, 'page');
        $one = $this->makeChild('one');
        $two = $this->makeChild('two');
        $three = $this->makeChild('three');
        $this->label($one, 'one');
        $this->label($two, 'two');
        $this->label($three, 'three');
        $this->trackRelationships(['TrackedPage_Kids', 'TrackedPage_SortedKids']);

        $page->Kids()->add($one);
        $this->resetTracking();
        $page->Kids()->add($two->ID);
        $this->resetTracking();
        $page->SortedKids()->add($three, ['Sort' => 1]);
        $this->resetTracking();
        $page->SortedKids()->add($three, ['Sort' => 2]);
        $this->resetTracking();
        $page->Kids()->remove($one);
        $this->resetTracking();
        $page->Kids()->setByIDList([$two->ID, $three->ID]);

        GoldenFile::assertMatches('many-many', $this->snapshotRecords());
    }

    public function testChangeTypeStringsByteIdentical()
    {
        $page = $this->makePage('Typed page');
        $child = $this->makeChild('typed child');
        $this->trackRelationships(['TrackedPage_Kids']);

        $page->Title = 'Typed page, renamed';
        $page->write();
        $this->resetTracking();
        $page->publishRecursive();
        $this->resetTracking();
        $page->doUnpublish();
        $this->resetTracking();
        $page->rollbackSingle(1);
        $this->resetTracking();
        $page->Kids()->add($child);
        $this->resetTracking();
        $page->Kids()->remove($child);
        $this->resetTracking();
        Versioned::set_reading_mode('Stage.Live');
        $page->delete();
        Versioned::set_reading_mode('Stage.Stage');

        $types = [];
        foreach ($this->records() as $record) {
            $types[$record->ChangeType] = true;
        }

        GoldenFile::assertMatches('change-types', array_keys($types));
    }
}
