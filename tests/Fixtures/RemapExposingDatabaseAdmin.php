<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DatabaseAdmin;

/**
 * Runs the ClassName remapping step of the build for one field, with the mapping from configuration
 */
class RemapExposingDatabaseAdmin extends DatabaseAdmin implements TestOnly
{
    public function remapField(string $dataClass, string $fieldName): void
    {
        $this->updateLegacyClassNameField(
            $dataClass,
            $fieldName,
            DatabaseAdmin::config()->get('classname_value_remapping')
        );
    }
}
