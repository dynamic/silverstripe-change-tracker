<?php

namespace Symbiote\DataChange\Tests\Characterization;

use ReflectionMethod;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Symbiote\DataChange\Control\AuditListingExtension;
use Symbiote\DataChange\Tests\Fixtures\AuditPage;
use Symbiote\DataChange\Tests\Fixtures\AuditPageController;
use Symbiote\DataChange\Tests\Fixtures\LegacyAuditPage;
use Symbiote\DataChange\Tests\Fixtures\LegacyAuditPageController;

/**
 * The review listings answer the same requests with the same responses as the page controller code the sites carried.
 * Each request is made below a page using the extension and below a page using the sites' code.
 */
class AuditListingExtensionTest extends CharacterizationTestCase
{
    public static function getExtraDataObjects()
    {
        return array_merge(parent::getExtraDataObjects(), [
            AuditPage::class,
            LegacyAuditPage::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TestAssetStore::activate('AuditListingExtensionTest');
        $this->autoFollowRedirection = false;
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();
        parent::tearDown();
    }

    private function publish(SiteTree $page, string $lastEdited): SiteTree
    {
        $page->write();
        $page->publishRecursive();
        $this->resetTracking();
        foreach (['SiteTree', 'SiteTree_Live'] as $table) {
            DB::prepared_query(
                'UPDATE "' . $table . '" SET "LastEdited" = ? WHERE "ID" = ?',
                [$lastEdited, $page->ID]
            );
        }
        return $page;
    }

    /**
     * Both listing pages plus two other pages, with fixed and distinct LastEdited values
     */
    private function pages(): void
    {
        $this->publish(AuditPage::create(['Title' => 'Audit', 'URLSegment' => 'audit']), '2030-01-01 00:00:01');
        $this->publish(
            LegacyAuditPage::create(['Title' => 'Legacy audit', 'URLSegment' => 'legacy-audit']),
            '2030-01-01 00:00:02'
        );
        $this->publish(
            AuditPage::create(['Title' => 'Quotes "&" <tags>', 'URLSegment' => 'quotes']),
            '2030-01-01 00:00:04'
        );
        $this->publish(AuditPage::create(['Title' => 'Older', 'URLSegment' => 'older']), '2029-12-31 23:59:59');
    }

    private function file(string $class, string $filename, string $content, string $lastEdited): File
    {
        /** @var File $file */
        $file = $class::create();
        $file->setFromString($content, $filename);
        $file->write();
        $file->publishSingle();
        $table = DataObject::getSchema()->baseDataTable(File::class);
        foreach ([$table, $table . '_Live'] as $name) {
            DB::prepared_query('UPDATE "' . $name . '" SET "LastEdited" = ? WHERE "ID" = ?', [$lastEdited, $file->ID]);
        }
        return $file;
    }

    private function files(): void
    {
        $this->file(File::class, 'docs/report.pdf', 'pdf content', '2030-02-01 00:00:03');
        $this->file(File::class, 'docs/sheet.xlsx', 'sheet content, longer', '2030-02-01 00:00:05');
        $this->file(File::class, 'notes.txt', 'n', '2030-02-01 00:00:01');
        $this->file(File::class, 'archive.zip', 'not a document', '2030-02-01 00:00:09');
        $this->file(Image::class, 'picture.png', 'not an image either', '2030-02-01 00:00:08');
        $this->file(File::class, 'docs/Quoted & "named".doc', 'doc', '2030-02-01 00:00:02');
    }

    /**
     * @return array status, content type and body, with the controller class names made equal
     */
    private function describe(HTTPResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'type' => $response->getHeader('Content-Type'),
            'body' => str_replace(
                [LegacyAuditPageController::class, AuditPageController::class],
                'CONTROLLER',
                (string)$response->getBody()
            ),
        ];
    }

    private function assertSameAsLegacy(string $action): array
    {
        $module = $this->describe($this->get('audit/' . $action));
        $legacy = $this->describe($this->get('legacy-audit/' . $action));
        $this->assertSame($legacy, $module, $action);
        return $module;
    }

    public function testAnonymous403()
    {
        $this->pages();
        $this->logOut();

        foreach (['listpages', 'listdocs'] as $action) {
            $response = $this->assertSameAsLegacy($action);
            $this->assertSame(403, $response['status'], $action);
            $this->assertStringContainsString("Action '$action' isn't allowed on class CONTROLLER", $response['body']);
        }
    }

    public function testCmsUserWithoutAdmin403()
    {
        $this->pages();
        $this->logInWithPermission('CMS_ACCESS_CMSMain');

        foreach (['listpages', 'listdocs'] as $action) {
            $this->assertSame(403, $this->assertSameAsLegacy($action)['status'], $action);
        }
    }

    public function testAdminListPages200()
    {
        $this->pages();

        $response = $this->assertSameAsLegacy('listpages');

        $this->assertSame(200, $response['status']);
        $this->assertSame('text/html; charset=utf-8', $response['type']);
        $row = function (string $segment, string $title, string $lastEdited) {
            $id = SiteTree::get_by_link($segment)->ID;
            $url = 'http://localhost/' . $segment;
            return "<tr><td>$id</td><td>$title</td><td><a target=\"_blank\" href=\"$url\">$url</a></td>"
                . "<td>$lastEdited</td></tr>";
        };
        $this->assertSame(
            '<table border="1" cellpadding="4" width="100%">'
            . '<tr><th>ID</th><th>Title</th><th>URL</th><th>Modified (LastEdited)</th></tr>'
            . $row('quotes', 'Quotes &quot;&amp;&quot; &lt;tags&gt;', '2030-01-01 00:00:04')
            . $row('legacy-audit', 'Legacy audit', '2030-01-01 00:00:02')
            . $row('audit', 'Audit', '2030-01-01 00:00:01')
            . $row('older', 'Older', '2029-12-31 23:59:59')
            . '</table>',
            $response['body']
        );
    }

    public function testAdminListDocs200()
    {
        $this->pages();
        $this->files();

        $response = $this->assertSameAsLegacy('listdocs');

        $this->assertSame(200, $response['status']);
        $this->assertSame('text/html; charset=utf-8', $response['type']);
        preg_match_all('/<tr><td>(\d+)<\/td><td>([^<]*)<\/td><td>([^<]*)<\/td>/', $response['body'], $rows);
        // documents only, newest first; folders, the zip and the image are left out
        $this->assertSame(['sheet.xlsx', 'report.pdf', 'Quoted-named.doc', 'notes.txt'], $rows[3]);
        $this->assertStringStartsWith(
            '<table border="1" cellpadding="4" width="100%">'
            . '<tr><th>ID</th><th>Title</th><th>Filename</th><th>URL</th><th>Size</th><th>Modified</th></tr>',
            $response['body']
        );
        $this->assertStringContainsString('<td>21 bytes</td><td>2030-02-01 00:00:05</td></tr>', $response['body']);
        $this->assertSame(0, Folder::get()->filter('Name', 'docs')->count() - 1);
    }

    public function testDocumentExtensionsAreConfigurable()
    {
        $this->pages();
        $this->files();
        Config::modify()->set(AuditListingExtension::class, 'document_extensions', ['zip', 'txt']);

        $body = $this->get('audit/listdocs')->getBody();

        preg_match_all('/<tr><td>(\d+)<\/td><td>([^<]*)<\/td><td>([^<]*)<\/td>/', $body, $rows);
        $this->assertSame(['archive.zip', 'notes.txt'], $rows[3]);
    }

    public function testPermissionIsConfiguredOnTheExtension()
    {
        $this->pages();
        $this->logInWithPermission('CMS_ACCESS_CMSMain');

        // the controller's own allowed_actions is not consulted for actions that come from an extension
        Config::modify()->merge(AuditPageController::class, 'allowed_actions', ['listpages' => 'CMS_ACCESS_CMSMain']);
        $this->assertSame(403, $this->get('audit/listpages')->getStatusCode());

        // the rules are read from the class that declares the action, uninherited, so a site sets them in YAML on
        // the extension class (a Config::modify() change is not visible to that uninherited read)
        $controller = AuditPageController::create();
        $method = new ReflectionMethod($controller, 'definingClassForAction');
        $method->setAccessible(true);
        foreach (['listpages', 'listdocs'] as $action) {
            $this->assertSame(AuditListingExtension::class, $method->invoke($controller, $action));
        }
        $this->assertSame(
            ['listpages' => 'ADMIN', 'listdocs' => 'ADMIN'],
            $controller->allowedActions(AuditListingExtension::class)
        );
    }

    public function testNotAppliedIs404()
    {
        $this->pages();
        $this->publish($this->makePage('Plain', ['URLSegment' => 'plain']), '2030-01-01 00:00:03');

        $this->assertSame(404, $this->get('plain/listpages')->getStatusCode());
        $this->assertSame(404, $this->get('plain/listdocs')->getStatusCode());
    }
}
