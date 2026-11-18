<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedObject;

/**
 * The columns and helper methods the site extension adds to the Data Changes grid
 */
class PageUrlColumnTest extends CharacterizationTestCase
{
    private function fresh(DataChangeRecord $record): DataChangeRecord
    {
        return DataChangeRecord::get()->byID($record->ID);
    }

    /**
     * @param \SilverStripe\ORM\SS_List|array $pages
     * @return string
     */
    private function links($pages): string
    {
        $links = [];
        foreach ($pages as $page) {
            $links[] = $page->AbsoluteLink();
        }
        return implode(', ', $links);
    }

    public function testFromAffectedPagesCommaJoined()
    {
        $one = $this->makePage('Linked directly');
        $two = $this->makePage('Features the object');
        $object = $this->makeObject('Shown on two pages', ['PageID' => $one->ID]);
        $two->FeatureID = $object->ID;
        $two->write();
        $this->resetTracking();

        $object->Title = 'Shown on two pages, renamed';
        $object->write();

        $record = $this->fresh($this->lastRecord());
        $this->assertCount(2, $record->AffectedPages());
        $this->assertSame($this->links($record->AffectedPages()), $record->PageURL);
        $this->assertStringContainsString(', ', $record->PageURL);
        $this->assertStringContainsString($one->AbsoluteLink(), $record->PageURL);
        $this->assertStringContainsString($two->AbsoluteLink(), $record->PageURL);
    }

    public function testFromCmsEditUrl()
    {
        $edited = $this->makePage('Edited in the CMS');
        $related = $this->makePage('Related later');
        $object = $this->makeObject('Saved from a page form');

        // no page is known when the record is written, but the request came from a page's edit form
        $_SERVER['REQUEST_URI'] = '/admin/pages/edit/EditForm/' . $edited->ID . '/field/Things/item/1';
        $object->Title = 'Saved from a page form, renamed';
        $object->write();
        $record = $this->lastRecord();
        $this->assertCount(0, $record->AffectedPages());

        // once a page can be found for the object, the page from the edit form comes first
        $this->resetTracking();
        $object->PageID = $related->ID;
        $object->write();
        $record = $this->fresh($record);
        $this->assertSame($edited->AbsoluteLink() . ', ' . $related->AbsoluteLink(), $record->PageURL);

        // and is not repeated when it is also one of the pages
        $object->PageID = $edited->ID;
        $object->write();
        $this->assertSame($edited->AbsoluteLink(), $this->fresh($record)->PageURL);
    }

    public function testNoPagesAffectedString()
    {
        $plain = $this->makePlain('Belongs to no page');
        $plain->Notes = 'changed';
        $plain->write();

        $record = $this->fresh($this->lastRecord());

        $this->assertSame('No pages affected', $record->PageURL);
        $this->assertSame('No pages affected', $record->getAffectedPageURLs());
    }

    public function testCmsEditUrlIsIgnoredWhenNoPagesCanBeFound()
    {
        $page = $this->makePage('Edited in the CMS');
        $plain = $this->makePlain('Belongs to no page');
        $_SERVER['REQUEST_URI'] = '/admin/pages/edit/EditForm/' . $page->ID . '/field/Things/item/1';

        $plain->Notes = 'changed';
        $plain->write();

        // the edit form's page is only used when other pages exist, so this is the same as no URL at all
        $this->assertSame('No pages affected', $this->fresh($this->lastRecord())->PageURL);
    }

    public function testChangeTypeNiceSaved()
    {
        $page = $this->makePage('Typed');
        $page->Title = 'Typed, renamed';
        $page->write();
        $this->resetTracking();
        $page->publishRecursive();

        $niceByType = [];
        foreach ($this->recordsFor($page) as $record) {
            $niceByType[$record->ChangeType] = $record->ChangeTypeNice;
        }

        $this->assertSame(['New' => 'New', 'Change' => 'Saved', 'Publish' => 'Publish'], $niceByType);
    }

    public function testRecordClassSingularName()
    {
        $object = $this->makeObject('Named');
        $object->Title = 'Named, renamed';
        $object->write();

        $record = $this->lastRecord();
        $this->assertSame(TrackedObject::singleton()->singular_name(), $record->RecordClassSingularName);
        $this->assertSame('Tracked Object', $record->RecordClassSingularName);

        // a record that has been deleted since is still described by its class
        $object->delete();
        $this->assertSame('Tracked Object', $this->fresh($record)->RecordClassSingularName);
    }

    public function testRefererFallback()
    {
        $page = $this->makePage('Has an object');
        $object = $this->makeObject('Saved with only a referer', ['PageID' => $page->ID]);
        $_SERVER['REQUEST_URI'] = '';
        unset($_SERVER['SERVER_NAME']);
        $object->Title = 'Saved with only a referer, renamed';
        $object->write();
        $record = $this->lastRecord();
        // the join rows exist, so a record like this is rendered from them
        $this->assertSame($page->AbsoluteLink(), $this->fresh($record)->PageURL);

        // Without join rows and without a current URL the referer is used, and a CMS edit form URL there names the
        // page
        $record->AffectedPages()->removeAll();
        $record = $this->fresh($record);
        $record->CurrentURL = '';
        $record->Referer = 'http://tracker.test/admin/pages/edit/EditForm/' . $page->ID . '/field/x';
        $record->write();
        $this->assertSame($page->AbsoluteLink(), $this->fresh($record)->PageURL);

        // without either URL there is nothing to go on
        $record->Referer = '';
        $record->write();
        $this->assertSame('No pages affected', $this->fresh($record)->PageURL);

        $record->CurrentURL = 'http://tracker.test/anything';
        $record->write();
        $this->assertSame($page->AbsoluteLink(), $this->fresh($record)->PageURL);
    }

    public function testGetAffectedPageUrlsDeduplicates()
    {
        $page = $this->makePage('Linked twice');
        $object = $this->makeObject('Linked twice object', ['PageID' => $page->ID]);
        $page->FeatureID = $object->ID;
        $page->write();
        $this->resetTracking();

        $object->Title = 'Linked twice object, renamed';
        $object->write();

        $record = $this->fresh($this->lastRecord());
        $this->assertSame([$page->AbsoluteLink()], $record->getAffectedPageURLs());
    }
}
