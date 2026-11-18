<?php

namespace Dynamic\ChangeTracker\Tests;

use SilverStripe\Dev\FunctionalTest;
use TypeError;

class DataChangeCMSTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestTextJSONFieldObject::class,
        TestTrackedObject::class,
        TestTrackedChild::class,
    ];

    private const EDIT_LINK = 'admin/datachanges/Dynamic-ChangeTracker-Model-DataChangeRecord/EditForm/field/'
        . 'Dynamic-ChangeTracker-Model-DataChangeRecord/item/%d/edit';

    public function testCMSFieldsShowRequestVars()
    {
        $record = new TestTrackedObject();
        $record->Title = 'First title';
        $record->write();
        $record->Title = 'Second title';
        $record->write();

        $ids = $record->getDataChangesList()->column('ID');
        $this->assertCount(2, $ids);

        $this->logInWithPermission('ADMIN');
        $response = $this->get(sprintf(self::EDIT_LINK, $ids[0]));
        $this->assertSame(200, $response->getStatusCode());

        // The upstream test asserted "Get Vars" and "Post Vars" with assertTrue(true, ...), which can never fail.
        // The labels that FormField::name_to_label() produces for GetVars and PostVars are "Get vars" and "Post vars".
        $body = $response->getBody();
        $this->assertStringContainsString('Get vars', $body, 'The edit form shows the GetVars field');
        $this->assertStringContainsString('Post vars', $body, 'The edit form shows the PostVars field');
    }

    /**
     * Characterization of current behaviour, not the intended behaviour.
     *
     * Upstream wrote this test to guard against "nl2br() expects parameter 1 to be string, array given": the
     * DataDifferencer cannot render a Text field whose getter returns an array. Upstream avoided it by decoding the
     * stored Before/After JSON only one level deep (prepareForDataDifferencer()). The fork's getCMSFields() decodes
     * the JSON fully again (since "UPDATE show changed fields and casting for colors"), and
     * prepareForDataDifferencer() is no longer called, so the edit screen of such a record throws a TypeError.
     * The same behaviour is pinned at the getCMSFields() level in
     * Characterization\DataChangeRecordCMSFieldsTest::testNestedJsonCurrentBehaviour().
     *
     * When this is fixed, replace the expected exception with a 200 response and the "Get vars"/"Post vars"
     * assertions of testCMSFieldsShowRequestVars().
     */
    public function testCMSFieldsWithJSONDataCurrentlyThrows()
    {
        $record = new TestTextJSONFieldObject();
        $record->TextFieldWithJSON = json_encode([
            'The Pixies' => [
                'Bossanova' => [
                    'The Happening' => [
                        'My head was feeling scared',
                        'but my heart was feeling free',
                    ]
                ],
            ]
        ]);
        $record->write();
        $record->TextFieldWithJSON = json_encode([
            'Radiohead' => [
                'A Moonshaped Pool' => [
                    'Present Tense' => [
                        'Keep it light and',
                        'Keep it moving',
                        'I am doing',
                        'No harm',
                    ]
                ],
            ]
        ]);
        $record->write();

        // Get the data change tracker records written by the ChangeRecordable extension
        $ids = $record->getDataChangesList()->column('ID');
        $this->assertCount(2, $ids);

        $this->logInWithPermission('ADMIN');
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('nl2br(): Argument #1 ($string) must be of type string, array given');
        $this->get(sprintf(self::EDIT_LINK, $ids[0]));
    }
}
