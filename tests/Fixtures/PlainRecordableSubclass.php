<?php

namespace Dynamic\ChangeTracker\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;

/**
 * Pins that the reverse relation scan only matches the exact class of the tracked record
 */
class PlainRecordableSubclass extends PlainRecordable implements TestOnly
{
    private static $table_name = 'PlainRecordableSubclass';

    private static $db = [
        'Flavour' => 'Varchar(100)',
    ];
}
