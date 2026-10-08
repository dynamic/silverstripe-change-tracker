<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A page whose controller carries AuditListingExtension
 */
class AuditPage extends SiteTree implements TestOnly
{
    private static $table_name = 'AuditPage';

    private static $controller_name = AuditPageController::class;
}
