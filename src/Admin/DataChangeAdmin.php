<?php

namespace Dynamic\ChangeTracker\Admin;

use SilverStripe\Admin\ModelAdmin;
use Dynamic\ChangeTracker\Model\DataChangeRecord;

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataChangeAdmin extends ModelAdmin
{
    private static $managed_models = [
        DataChangeRecord::class,
    ];

    private static $url_segment = 'datachanges';

    /**
     * Fixed instead of derived from the class name, so the code does not change when the class moves. Codes granted
     * under the legacy class name are migrated by DataChangeRecord::requireDefaultRecords().
     *
     * @config
     * @var string
     */
    private static $required_permission_codes = 'CMS_ACCESS_DataChangeAdmin';
    private static $menu_title = 'Data Changes';
}
