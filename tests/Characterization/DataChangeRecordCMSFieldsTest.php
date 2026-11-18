<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use TypeError;
use Dynamic\ChangeTracker\Extension\SiteTreeChangeRecordable;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Tests\Fixtures\CmsFieldsProbeExtension;
use Dynamic\ChangeTracker\Tests\Fixtures\ObjectValueReadonlyField;
use Dynamic\ChangeTracker\Tests\Fixtures\TrackedObject;
use Dynamic\ChangeTracker\Tests\TestTextJSONFieldObject;

/**
 * The read-only screen built by DataChangeRecord::getCMSFields()
 */
class DataChangeRecordCMSFieldsTest extends CharacterizationTestCase
{
    protected static $required_extensions = [
        SiteTree::class => [
            SiteTreeChangeRecordable::class,
        ],
        DataChangeRecord::class => [
            CmsFieldsProbeExtension::class,
        ],
    ];

    /**
     * @param FieldList|CompositeField $fields
     * @return string[]
     */
    private function names($fields): array
    {
        $list = $fields instanceof CompositeField ? $fields->FieldList() : $fields;
        return array_map(function ($field) {
            return $field->getName();
        }, $list->toArray());
    }

    private function changeRecord(): DataChangeRecord
    {
        $object = $this->makeObject('Original', ['Body' => 'old body']);
        $object->Body = 'new body';
        $object->write();
        return $this->lastRecord();
    }

    public function testDetailsAndRawDataSections()
    {
        $fields = $this->changeRecord()->getCMSFields();

        $this->assertSame(['Details', 'FieldChanges', 'RawData', 'HookMarker'], $this->names($fields));

        $details = $fields->fieldByName('Details');
        $this->assertSame(
            [
                'ChangeType' => 'Type of change',
                'ChangeRecordClass' => 'Record Class',
                'ChangeRecordID' => 'Record ID',
                'ObjectTitle' => 'Record Title',
                'Created' => 'Modification Date',
                'Stage' => 'Stage',
                'User' => 'User',
                'CurrentURL' => 'URL',
                'Referer' => 'Referer',
                'RemoteIP' => 'Remote IP',
                'Agent' => 'Agent',
            ],
            array_combine($this->names($details), array_map(function ($field) {
                return $field->Title();
            }, $details->FieldList()->toArray()))
        );
        $this->assertFalse($details->getStartClosed());
        $this->assertStringContainsString('datachange-field', $details->extraClass());

        $raw = $fields->fieldByName('RawData');
        $this->assertSame(['Before', 'After', 'GetVars', 'PostVars'], $this->names($raw));
        $this->assertSame('Raw Data', $raw->Title());
    }

    public function testUserFieldShowsNameAndEmail()
    {
        $record = $this->changeRecord();

        $field = $record->getCMSFields()->dataFieldByName('User');
        $this->assertSame($record->getMemberDetails(), $field->getValue());
        $this->assertStringContainsString('<' . $record->CurrentEmail . '>', $field->getValue());
    }

    public function testChangedFieldsOnlyForKeysInJson()
    {
        $fields = $this->changeRecord()->getCMSFields();

        // Title is in the diffed record but not in the stored JSON, so only Body is shown
        $this->assertSame(['ChangedFieldBody'], $this->names($fields->fieldByName('FieldChanges')));
    }

    public function testChangedValuesAreHtmlTextWithInsDel()
    {
        $field = $this->changeRecord()->getCMSFields()->dataFieldByName('ChangedFieldBody');

        $this->assertInstanceOf(DBHTMLText::class, $field->getValue());
        $html = preg_replace('/\s+/', ' ', $field->getValue()->getValue());
        $this->assertStringContainsString('<del>old</del>', $html);
        $this->assertStringContainsString('<ins>new</ins>', $html);
        $this->assertSame('Body', $field->Title());
    }

    public function testUpdateCMSFieldsHookInvoked()
    {
        $fields = $this->changeRecord()->getCMSFields();

        $marker = $fields->fieldByName('HookMarker');
        $this->assertNotNull($marker);
        $this->assertStringContainsString('hook ran', $marker->getContent());
    }

    public function testAllFieldsReadonly()
    {
        $fields = $this->changeRecord()->getCMSFields();

        $this->assertNotEmpty($fields->dataFields());
        foreach ($fields->dataFields() as $name => $field) {
            $this->assertTrue($field->isReadonly(), "$name is read-only");
            $this->assertInstanceOf(ReadonlyField::class, $field, $name);
        }
    }

    public function testNestedJsonCurrentBehaviour()
    {
        $object = TestTextJSONFieldObject::create();
        $object->TextFieldWithJSON = json_encode(['The Pixies' => ['Bossanova' => ['The Happening' => ['a']]]]);
        $object->write();
        $object->TextFieldWithJSON = json_encode(['Radiohead' => ['A Moonshaped Pool' => ['Present Tense' => ['b']]]]);
        $object->write();

        // The fork decodes the stored JSON fully instead of one level deep, so a field whose getter returns an
        // array cannot be diffed and the whole screen fails
        $record = $this->lastRecord();
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('DBField::XML(): Return value must be of type string, array returned');
        $record->getCMSFields();
    }

    public function testMissingForTemplateReplaced()
    {
        Injector::inst()->load([
            ReadonlyField::class => ['class' => ObjectValueReadonlyField::class],
        ]);

        $fields = $this->changeRecord()->getCMSFields();

        $this->assertSame('[Missing stdClass::forTemplate]', $fields->dataFieldByName('Referer')->getValue());
    }

    public function testExtensionRemovesNoiseRows()
    {
        $new = TrackedObject::create(['Title' => 'Fresh', 'Body' => 'text']);
        $new->write();
        $record = $this->recordsFor($new)[0];
        $this->assertSame('New', $record->ChangeType);

        $changed = $this->names($record->getCMSFields()->fieldByName('FieldChanges'));

        sort($changed);
        // ChangedFieldID is removed, but the extension asks for "ChangeFieldClassName" so ClassName stays
        $this->assertSame(['ChangedFieldBody', 'ChangedFieldClassName', 'ChangedFieldTitle'], $changed);
    }

    /**
     * @return array[]
     */
    public static function changeTypeProvider(): array
    {
        return [
            'new' => ['new'],
            'change' => ['change'],
            'publish' => ['publish'],
            'unpublish' => ['unpublish'],
            'delete' => ['delete'],
            'add' => ['add'],
        ];
    }

    /**
     * Every kind of record can be opened in the admin
     *
     * @dataProvider changeTypeProvider
     */
    #[DataProvider('changeTypeProvider')]
    public function testEveryChangeTypeBuildsFields(string $kind)
    {
        $page = $this->makePage('Rendered');
        $child = $this->makeChild('Linked');

        switch ($kind) {
            case 'new':
                $record = $this->recordsFor($page)[0];
                break;
            case 'change':
                $page->Title = 'Rendered, edited';
                $page->write();
                $record = $this->lastRecord();
                break;
            case 'publish':
                $page->publishRecursive();
                $record = $this->lastRecord();
                break;
            case 'unpublish':
                $page->publishRecursive();
                $this->resetTracking();
                $page->doUnpublish();
                $record = $this->lastRecord();
                break;
            case 'delete':
                $page->delete();
                $record = $this->lastRecord();
                break;
            default:
                $this->trackRelationships(['TrackedPage_Kids']);
                $page->Kids()->add($child);
                $record = $this->lastRecord();
        }

        $fields = $record->getCMSFields();

        $this->assertInstanceOf(FieldList::class, $fields);
        $this->assertSame([], $this->takeWarnings());
    }

    /**
     * A stored row can have no After value. The screen still renders its sections, and decoding the missing value
     * raises no deprecation.
     */
    public function testNullAfterRowRendersWithoutDeprecation()
    {
        $record = $this->changeRecord();
        DB::prepared_query('UPDATE "DataChangeRecord" SET "After" = NULL WHERE "ID" = ?', [$record->ID]);
        $record = DataChangeRecord::get()->byID($record->ID);
        $this->assertNull($record->After);

        $deprecations = [];
        set_error_handler(function ($errno, $errstr) use (&$deprecations) {
            if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                $deprecations[] = $errstr;
                return true;
            }
            return false;
        });
        try {
            $fields = $record->getCMSFields();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['Details', 'FieldChanges', 'RawData', 'HookMarker'], $this->names($fields));
        $this->assertSame([], $deprecations);
        $this->assertSame([], $this->takeWarnings());
    }
}
