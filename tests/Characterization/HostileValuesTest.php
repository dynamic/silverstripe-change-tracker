<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedObject;

/**
 * Values that Silverstripe 6 is stricter about than Silverstripe 5: a change record with no member, an integer title,
 * and data that cannot be encoded as JSON. These exist on line 2 only, because the framework that makes them fail
 * is the one line 2 targets; line 1 has no such test.
 */
class HostileValuesTest extends CharacterizationTestCase
{
    public function testNullChangedByIdBuildsTheCmsFields()
    {
        $object = $this->makeObject('Without a member');
        $object->Title = 'Without a member, renamed';
        $object->write();

        $record = $this->lastRecord();
        $record->ChangedByID = null;
        $record->write(skipValidation: true);

        $this->assertSame(0, (int)DataChangeRecord::get()->byID($record->ID)->ChangedByID);
        $this->assertNull($record->getMemberDetails());
        $this->assertNotNull($record->getCMSFields());
        $this->assertSame([], $this->takeWarnings());
    }

    public function testIntegerTitleIsStoredAsText()
    {
        $object = $this->makeObject('Numbered');
        $object->Title = 12345;
        $object->write(skipValidation: true);

        $record = $this->lastRecord();
        $this->assertSame('12345', (string)$record->ObjectTitle);
        $this->assertSame('12345', DataChangeRecord::get()->byID($record->ID)->ObjectTitle);
        $this->assertSame([], $this->takeWarnings());
    }

    public function testUnencodableValueDoesNotStopTheWrite()
    {
        $object = $this->makeObject('Unencodable');
        // a byte sequence that is not valid UTF-8, so json_encode() returns false
        $object->Body = "bad \xB1 byte";
        $object->write();

        $record = $this->lastRecord();
        $this->assertSame('Change', $record->ChangeType);
        $this->assertNotNull($record->getCMSFields());
        $this->assertSame([], $this->takeWarnings());
    }
}
