<?php

namespace Symbiote\DataChange\Tests\Fixtures;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Dev\TestOnly;
use Symbiote\DataChange\Control\AuditListingExtension;

class AuditPageController extends ContentController implements TestOnly
{
    private static $extensions = [
        AuditListingExtension::class,
    ];
}
