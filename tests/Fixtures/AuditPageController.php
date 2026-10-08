<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Dev\TestOnly;
use Dynamic\ChangeTracker\Control\AuditListingExtension;

class AuditPageController extends ContentController implements TestOnly
{
    private static $extensions = [
        AuditListingExtension::class,
    ];
}
