<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Service\AffectedPagesService;

/**
 * The affected pages lookup with a call counter
 */
class CountingAffectedPagesService extends AffectedPagesService implements TestOnly
{
    public static int $lookups = 0;

    public function getAffectedPageRecords(DataChangeRecord $changeRecord): ?array
    {
        self::$lookups++;
        return parent::getAffectedPageRecords($changeRecord);
    }
}
