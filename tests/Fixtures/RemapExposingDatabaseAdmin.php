<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\Command\DbBuild;
use SilverStripe\Dev\TestOnly;

/**
 * Runs the ClassName remapping step of the build for one field, with the mapping from configuration
 */
class RemapExposingDatabaseAdmin extends DbBuild implements TestOnly
{
    public function remapField(string $dataClass, string $fieldName): void
    {
        $this->updateLegacyClassNameField(
            $dataClass,
            $fieldName,
            DbBuild::config()->get('classname_value_remapping')
        );
    }
}
