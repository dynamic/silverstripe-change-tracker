<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Forms\ReadonlyField;

/**
 * A read-only field that holds an object which cannot be rendered, until a value is set explicitly
 */
class ObjectValueReadonlyField extends ReadonlyField
{
    public function Value()
    {
        if ($this->getName() === 'Referer' && $this->value === null) {
            return new \stdClass();
        }
        return parent::Value();
    }
}
