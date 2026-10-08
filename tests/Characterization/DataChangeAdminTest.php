<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Admin\DataChangeAdmin;
use Dynamic\ChangeTracker\Model\DataChangeRecord;

/**
 * The Data Changes screen in the CMS
 */
class DataChangeAdminTest extends CharacterizationTestCase
{
    /**
     * @var string
     */
    private const RECORD_PATH = 'Dynamic-ChangeTracker-Model-DataChangeRecord';

    protected function setUp(): void
    {
        parent::setUp();
        $this->autoFollowRedirection = false;
    }

    private function editLink(DataChangeRecord $record): string
    {
        return 'admin/datachanges/' . self::RECORD_PATH . '/EditForm/field/' . self::RECORD_PATH
            . '/item/' . $record->ID . '/edit';
    }

    private function changeRecord(): DataChangeRecord
    {
        $object = $this->makeObject('Admin screen', ['Body' => 'old']);
        $object->Body = 'new';
        $object->write();
        return $this->lastRecord();
    }

    public function testIndex200()
    {
        $this->changeRecord();

        $response = $this->get('admin/datachanges');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(self::RECORD_PATH, $response->getBody());
    }

    public function testEditView200HasRealContent()
    {
        $record = $this->changeRecord();

        $response = $this->get($this->editLink($record));

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Raw Data', $body);
        $this->assertStringContainsString('Changed Fields', $body);
        $this->assertStringContainsString('ChangedFieldBody', $body);
        // The upstream test asserts on "Get Vars" and "Post Vars" with assertTrue(true, ...), which cannot fail.
        // The labels are actually "Get vars" and "Post vars".
        $this->assertStringContainsString('Get vars', $body);
        $this->assertStringContainsString('Post vars', $body);
        $this->assertStringNotContainsString('Get Vars', $body);
    }

    public function testEditViewOffersSaveButNoDelete()
    {
        $record = $this->changeRecord();

        $body = $this->get($this->editLink($record))->getBody();

        preg_match_all('/name="(action_[^"]*)"/', $body, $matches);
        $this->assertSame(['action_doSave'], $matches[1]);
    }

    public function testUnknownRecord404()
    {
        $response = $this->get('admin/datachanges/' . self::RECORD_PATH . '/EditForm/field/' . self::RECORD_PATH
            . '/item/999999/edit');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSummaryColumnsExactOrderAndLabels()
    {
        $this->changeRecord();

        $body = $this->get('admin/datachanges')->getBody();

        preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $body, $matches);
        $headers = array_values(array_filter(array_map(function ($header) {
            return trim(strip_tags($header));
        }, $matches[1]), function ($header) {
            return $header !== '' && strpos($header, 'View') !== 0;
        }));
        $this->assertSame(
            ['Change Type', 'Record Type', 'Record ID', 'Record Title', 'Page URL', 'User', 'Modification Date'],
            $headers
        );
        $this->assertSame(
            [
                'ChangeTypeNice' => 'Change Type',
                'RecordClassSingularName' => 'Record Type',
                'ChangeRecordID' => 'Record ID',
                'ObjectTitle' => 'Record Title',
                'PageURL' => 'Page URL',
                'ChangedBy.Title' => 'User',
                'Created' => 'Modification Date',
            ],
            DataChangeRecord::singleton()->summaryFields()
        );
    }

    public function testRequiredPermissionCode()
    {
        $admin = DataChangeAdmin::singleton();

        // The code is fixed in configuration instead of derived from the class name
        $this->assertSame('CMS_ACCESS_DataChangeAdmin', $admin->getRequiredPermissions());
        $this->assertArrayHasKey('CMS_ACCESS_DataChangeAdmin', $admin->providePermissions());
        $this->assertArrayNotHasKey(
            'CMS_ACCESS_Dynamic\ChangeTracker\Admin\DataChangeAdmin',
            $admin->providePermissions()
        );
        $this->assertSame('datachanges', DataChangeAdmin::config()->get('url_segment'));
        $this->assertSame('Data Changes', DataChangeAdmin::config()->get('menu_title'));
        $this->assertSame([DataChangeRecord::class], DataChangeAdmin::config()->get('managed_models'));
    }

    public function testCanDeleteFalse()
    {
        $record = $this->changeRecord();

        $this->assertFalse($record->canDelete());
        $this->assertTrue($record->canEdit());
        $this->assertTrue($record->canView());
    }

    public function testNoPermission403()
    {
        $this->changeRecord();

        $this->logOut();
        $response = $this->get('admin/datachanges');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Security/login', $response->getHeader('Location'));

        // another CMS section only: sent back to the default section
        $this->logInWithPermission('CMS_ACCESS_CMSMain');
        $response = $this->get('admin/datachanges');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/admin/pages', $response->getHeader('Location'));

        // a code derived from the class name grants nothing
        $this->logInWithPermission('CMS_ACCESS_Dynamic\ChangeTracker\Admin\DataChangeAdmin');
        $this->assertSame(403, $this->get('admin/datachanges')->getStatusCode());
    }

    public function testPermissionHolders()
    {
        $this->changeRecord();

        $this->logInWithPermission('CMS_ACCESS_DataChangeAdmin');
        $this->assertSame(200, $this->get('admin/datachanges')->getStatusCode());

        // any CMS user with access to every section can open it
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $this->assertSame(200, $this->get('admin/datachanges')->getStatusCode());
    }
}
