<?php

namespace Dynamic\ChangeTracker\Extension;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridField;

/**
 * Add to Pages you want changes recorded for
 *
 * @author  stephen@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class SiteTreeChangeRecordable extends ChangeRecordable
{
    /**
     * Permission code needed to see the Published States tab. The fork checked the literal code
     * CMS_ACCESS_DataChangeAdmin, which no group was normally given while the admin required a code derived from its
     * class name, so the tab was shown to administrators and to holders of CMS_ACCESS_LeftAndMain. This default keeps that
     * audience now that the admin requires CMS_ACCESS_DataChangeAdmin itself.
     *
     * @config
     * @var string
     */
    private static $published_state_permission = 'CMS_ACCESS_LeftAndMain';

    public function onAfterPublish(&$original)
    {
        $this->dataChangeTrackService->track($this->getOwner(), 'Publish');
    }

    public function onAfterUnpublish()
    {
        $this->dataChangeTrackService->track($this->getOwner(), 'Unpublish');
    }

    public function updateCMSFields(FieldList $fields)
    {
        if (Permission::check(Config::inst()->get(SiteTreeChangeRecordable::class, 'published_state_permission'))) {
            //Get all data changes relating to this page filter them by publish/unpublish
            $dataChanges = DataChangeRecord::get()->filter([
                    'ChangeRecordID' => $this->getOwner()->ID,
                    'ChangeRecordClass' => $this->getOwner()->ClassName
                ])->exclude('ChangeType', 'Change');

            //create a gridfield out of them
            $gridFieldConfig = GridFieldConfig_RecordViewer::create();
            $publishedGrid   = new GridField('PublishStates', 'Published States', $dataChanges, $gridFieldConfig);
            $dataColumns     = $publishedGrid->getConfig()->getComponentByType(\SilverStripe\Forms\GridField\GridFieldDataColumns::class);
            $dataColumns->setDisplayFields([
                'ChangeType' => 'Change Type',
                'ObjectTitle' => 'Page Title',
                'ChangedBy.Title' => 'User',
                'Created' => 'Modification Date'
            ]);

            //linking through to the datachanges modeladmin

            $fields->addFieldsToTab('Root.PublishedState', [$publishedGrid]);
            return $fields;
        }
    }
}
