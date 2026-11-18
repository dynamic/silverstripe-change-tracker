<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;

/**
 * Proves that DataChangeRecord::getCMSFields() calls the updateCMSFields extension hook
 */
class CmsFieldsProbeExtension extends Extension
{
    public function updateCMSFields(FieldList $fields)
    {
        $fields->push(LiteralField::create('HookMarker', 'hook ran'));
    }
}
