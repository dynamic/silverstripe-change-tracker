<?php

namespace Symbiote\DataChange\Tests\Fixtures;

/**
 * The site extension with a call counter on the affected pages lookup
 */
class CountingLegacySiteDataChangeRecordExtension extends LegacySiteDataChangeRecordExtension
{
    public static int $lookups = 0;

    public function getAffectedPageRecords(): ?array
    {
        self::$lookups++;
        return parent::getAffectedPageRecords();
    }
}
