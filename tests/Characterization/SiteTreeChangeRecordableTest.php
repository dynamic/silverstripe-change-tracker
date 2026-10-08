<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedObject;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedPage;

/**
 * The published states tab and the publish hooks of SiteTreeChangeRecordable
 */
class SiteTreeChangeRecordableTest extends CharacterizationTestCase
{
    private function lifecyclePage(): TrackedPage
    {
        $page = $this->makePage('Lifecycle');
        $page->Title = 'Lifecycle, edited';
        $page->write();
        $this->resetTracking();
        $page->publishRecursive();
        $this->resetTracking();
        $page->doUnpublish();
        $this->resetTracking();

        return TrackedPage::get()->byID($page->ID);
    }

    private function grid(TrackedPage $page): ?GridField
    {
        return $page->getCMSFields()->fieldByName('Root.PublishedState.PublishStates');
    }

    public function testTabVisibleAdmin()
    {
        $page = $this->lifecyclePage();

        $tab = $page->getCMSFields()->fieldByName('Root.PublishedState');

        $this->assertNotNull($tab);
        $this->assertSame(['PublishStates'], array_map(function ($field) {
            return $field->getName();
        }, $tab->FieldList()->toArray()));
        $grid = $this->grid($page);
        $this->assertInstanceOf(GridField::class, $grid);
        $this->assertSame('Published States', $grid->Title());
    }

    public function testTabVisibleLeftAndMainHolder()
    {
        $page = $this->lifecyclePage();

        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');

        $this->assertNotNull($this->grid($page));
    }

    public function testTabHiddenForHolderOfTheAdminCodeOnly()
    {
        $page = $this->lifecyclePage();

        $this->logInWithPermission('CMS_ACCESS_DataChangeAdmin');

        // the tab requires CMS_ACCESS_LeftAndMain, so access to the Data Changes admin alone does not show it, as
        // before the admin's code was fixed
        $this->assertNull($page->getCMSFields()->fieldByName('Root.PublishedState'));
    }

    public function testTabPermissionIsConfigurable()
    {
        $page = $this->lifecyclePage();
        Config::modify()->set(SiteTreeChangeRecordable::class, 'published_state_permission', 'CMS_ACCESS_CMSMain');

        $this->logInWithPermission('CMS_ACCESS_CMSMain');

        $this->assertNotNull($this->grid($page));
    }

    public function testTabHiddenForSectionOnlyUser()
    {
        $page = $this->lifecyclePage();

        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $this->assertNull($page->getCMSFields()->fieldByName('Root.PublishedState'));

        $this->logOut();
        $this->assertNull($page->getCMSFields()->fieldByName('Root.PublishedState'));
    }

    public function testTabExcludesChange()
    {
        $page = $this->lifecyclePage();

        $types = $this->grid($page)->getList()->sort('ID', 'ASC')->column('ChangeType');

        $this->assertSame(['New', 'Publish', 'Delete from Live', 'Unpublish'], $types);
    }

    public function testTabOnlyListsTheRecordItBelongsTo()
    {
        $page = $this->lifecyclePage();
        $other = $this->makePage('Another page');
        $other->publishRecursive();

        $ids = array_unique($this->grid($page)->getList()->column('ChangeRecordID'));

        $this->assertSame([(string)$page->ID], array_map('strval', $ids));
    }

    public function testTabColumns()
    {
        $grid = $this->grid($this->lifecyclePage());

        $this->assertInstanceOf(GridFieldConfig_RecordViewer::class, $grid->getConfig());
        $columns = $grid->getConfig()->getComponentByType(GridFieldDataColumns::class);
        $this->assertSame(
            [
                'ChangeType' => 'Change Type',
                'ObjectTitle' => 'Page Title',
                'ChangedBy.Title' => 'User',
                'Created' => 'Modification Date',
            ],
            $columns->getDisplayFields($grid)
        );
    }

    public function testTabOnNonPageOwner()
    {
        $object = $this->makeObject('Not a page');

        $fields = $object->getCMSFields();

        $this->assertNotNull($fields->fieldByName('Root.PublishedState.PublishStates'));
    }

    public function testOnAfterPublish()
    {
        $page = $this->makePage('To publish');

        $page->publishRecursive();

        $this->assertSame(['New', 'Publish'], $this->types($page));
        $record = $this->lastRecord();
        $this->assertSame('null', $record->Before);
        $this->assertSame('To publish', json_decode($record->After, true)['Title']);
        $this->assertSame('Stage.Stage', $record->Stage);
    }

    public function testOnAfterUnpublish()
    {
        $page = $this->makePage('To unpublish');
        $page->publishRecursive();
        $this->resetTracking();

        $page->doUnpublish();

        $this->assertSame(['New', 'Publish', 'Delete from Live', 'Unpublish'], $this->types($page));
        $record = $this->lastRecord();
        $this->assertSame('null', $record->After);
        $this->assertSame('To unpublish', json_decode($record->Before, true)['Title']);
    }

    public function testPublishHooksFireForNonPageOwners()
    {
        $object = $this->makeObject('Versioned record');

        $object->publishRecursive();
        $this->resetTracking();
        $object->doUnpublish();

        $this->assertSame(['New', 'Publish', 'Delete from Live', 'Unpublish'], $this->types($object));
        $this->assertSame(TrackedObject::class, $this->lastRecord()->ChangeRecordClass);
    }
}
