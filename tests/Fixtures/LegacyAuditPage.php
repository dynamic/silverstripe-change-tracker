<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A page whose controller carries the sites' own listing actions
 */
class LegacyAuditPage extends SiteTree implements TestOnly
{
    private static $table_name = 'LegacyAuditPage';

    private static $controller_name = LegacyAuditPageController::class;
}
