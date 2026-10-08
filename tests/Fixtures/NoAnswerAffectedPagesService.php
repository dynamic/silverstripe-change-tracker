<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Service\AffectedPagesService;
use SilverStripe\Dev\TestOnly;

/**
 * The affected pages lookup as it answers for a record that cannot be loaded
 */
class NoAnswerAffectedPagesService extends AffectedPagesService implements TestOnly
{
    public function getAffectedPageRecords(DataChangeRecord $changeRecord): ?array
    {
        return null;
    }
}
