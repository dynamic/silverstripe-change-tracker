<?php

namespace Dynamic\ChangeTracker\Extension;

use Dynamic\ChangeTracker\Service\DataChangeTrackService;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;

/**
 * Add to classes you want changes recorded for
 *
 * Silverstripe 6 extensions do not extend DataExtension and have no parent hooks to call, so the hooks below do
 * not call parent:: and read the object through getOwner().
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ChangeRecordable extends Extension
{
    /**
     *
     * @var DataChangeTrackService
     */
    public $dataChangeTrackService;

    private static $ignored_fields = [];

    protected $isNewObject = false;

    protected $changeType = 'Change';

    public function onBeforeWrite()
    {
        $confirmSkipTracking = function ($record) {
            $changedFields = $record->getChangedFields(true, 2);

            foreach ($changedFields as $field => $change) {
                if ($field == 'SecurityID') {
                    continue;
                }
                $before[$field] = $change['before'];
                $after[$field] = $change['after'];
            }

            if (
                isset($before)
                && count($before) == 1
                && count($after) == 1
                && array_key_exists('Version', $before)
                && array_key_exists('Version', $after)
            ) {
                return true;
            }

            return false;
        };

        if ($this->getOwner()->isInDB()) {
            if (!$confirmSkipTracking($this->getOwner())) {
                $this->dataChangeTrackService->track($this->getOwner(), $this->changeType);
            }
        } else {
            $this->isNewObject = true;
            $this->changeType = 'New';
        }
    }

    public function onAfterWrite()
    {
        if ($this->isNewObject) {
            $this->dataChangeTrackService->track($this->getOwner(), $this->changeType);
            $this->isNewObject = false;
        }
    }

    public function onBeforeDelete()
    {
        $this->dataChangeTrackService->track($this->getOwner(), 'Delete');
    }

    public function getIgnoredFields()
    {
        $ignored = Config::inst()->get(ChangeRecordable::class, 'ignored_fields');
        $class = $this->getOwner()->ClassName;
        if (isset($ignored[$class])) {
            return array_combine($ignored[$class], $ignored[$class]);
        }
    }

    public function onBeforeVersionedPublish($from, $to)
    {
        if ($this->getOwner()->isInDB()) {
            $this->dataChangeTrackService->track($this->getOwner(), 'Publish ' . $from . ' to ' . $to);
        }
    }

    /**
     * Get the list of data changes for this item
     *
     * @return \SilverStripe\ORM\DataList
     */
    public function getDataChangesList()
    {
        return DataChangeRecord::get()->filter([
            'ChangeRecordID' => $this->getOwner()->ID,
            'ChangeRecordClass' => $this->getOwner()->ClassName,
        ]);
    }
}
