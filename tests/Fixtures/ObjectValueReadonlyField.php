<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Forms\ReadonlyField;

/**
 * A read-only field that holds an object which cannot be rendered, until a value is set explicitly.
 *
 * Silverstripe 6 has no Value() on form fields, and the change tracker reads the value with getValue(), so the object
 * is returned from getValue().
 */
class ObjectValueReadonlyField extends ReadonlyField
{
    public function getValue(): mixed
    {
        if ($this->getName() === 'Referer' && $this->value === null) {
            return new \stdClass();
        }
        return parent::getValue();
    }
}
