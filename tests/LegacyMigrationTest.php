<?php

namespace Dynamic\ChangeTracker\Tests;

use Dynamic\ChangeTracker\Job\PruneChangesBeforeJob;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Tests\Fixtures\RemapExposingDatabaseAdmin;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DatabaseAdmin;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionRole;
use SilverStripe\Security\PermissionRoleCode;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Data written by symbiote/silverstripe-datachange-tracker or the Dynamic fork of it is carried over by the build.
 *
 * The temporary test database is built without the build's ClassName remapping and without default records, so the
 * tests run both steps themselves. The ClassName column is an enum of the current classes, so tests that store the
 * legacy class name widen it first; that is a schema change, which is why this test does not use transactions.
 */
class LegacyMigrationTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected $usesTransactions = false;

    protected $autoFollowRedirection = false;

    private const NEW_CLASS = 'Dynamic\\ChangeTracker\\Model\\DataChangeRecord';

    private const OLD_CLASS = 'Symbiote\\DataChange\\Model\\DataChangeRecord';

    private const OLD_CODE = 'CMS_ACCESS_Symbiote\\DataChange\\Admin\\DataChangeAdmin';

    /**
     * Run the module's default records step, as the build does, and return what it printed
     */
    private function build(): string
    {
        DB::get_schema()->quiet(false);
        ob_start();
        try {
            DataChangeRecord::singleton()->requireDefaultRecords();
        } finally {
            $output = ob_get_clean();
            DB::get_schema()->quiet(true);
        }
        return strip_tags((string)$output);
    }

    private function widenClassNameColumn(): void
    {
        DB::query('ALTER TABLE "DataChangeRecord" MODIFY "ClassName" VARCHAR(255) NULL');
    }

    private function insertRecord(string $className, string $title): int
    {
        DB::prepared_query(
            'INSERT INTO "DataChangeRecord" ("ClassName", "Created", "LastEdited", "ChangeType", "ChangeRecordClass",'
            . ' "ChangeRecordID", "ObjectTitle") VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$className, '2024-01-02 03:04:05', '2024-01-02 03:04:05', 'Change', 'Page', 7, $title]
        );
        return (int)DB::get_generated_id('DataChangeRecord');
    }

    private function joinPage(int $recordID, int $pageID): void
    {
        DB::prepared_query(
            'INSERT INTO "DataChangeRecord_AffectedPages" ("DataChangeRecordID", "SiteTreeID") VALUES (?, ?)',
            [$recordID, $pageID]
        );
    }

    /**
     * @return string[] ID => ClassName
     */
    private function classNames(): array
    {
        return DB::query('SELECT "ID", "ClassName" FROM "DataChangeRecord" ORDER BY "ID"')->map();
    }

    /**
     * @return string[]
     */
    private function joinRows(): array
    {
        return DB::query(
            'SELECT CONCAT("DataChangeRecordID", \':\', "SiteTreeID") FROM "DataChangeRecord_AffectedPages"'
            . ' ORDER BY "ID"'
        )->column();
    }

    public function testRemapConfigOnBothBuildKeys()
    {
        $expected = [self::OLD_CLASS => self::NEW_CLASS];
        foreach ([DatabaseAdmin::class, 'SilverStripe\\Dev\\Command\\DbBuild'] as $class) {
            $mapping = Config::inst()->get($class, 'classname_value_remapping');
            $this->assertSame(self::NEW_CLASS, $mapping[self::OLD_CLASS] ?? null, $class);
        }
        $this->assertSame(self::NEW_CLASS, DataChangeRecord::class);
        $this->assertSame(self::OLD_CLASS, DataChangeRecord::LEGACY_CLASS);
        $this->assertSame($expected, array_intersect_key(
            Config::inst()->get(DatabaseAdmin::class, 'classname_value_remapping'),
            $expected
        ));
    }

    public function testTablesKeepTheirNames()
    {
        $this->assertSame('DataChangeRecord', DataChangeRecord::singleton()->baseTable());
        $this->assertSame(
            'DataChangeRecord_AffectedPages',
            DataObject::getSchema()->manyManyComponent(DataChangeRecord::class, 'AffectedPages')['join']
        );
        $this->assertTrue(DB::get_schema()->hasTable('DataChangeRecord_AffectedPages'));
        $this->assertSame(
            ['ID', 'DataChangeRecordID', 'SiteTreeID'],
            array_keys(DB::field_list('DataChangeRecord_AffectedPages'))
        );
    }

    public function testBuildRemapRewritesLegacyRows()
    {
        $this->widenClassNameColumn();
        $legacy = $this->insertRecord(self::OLD_CLASS, 'legacy');
        $current = $this->insertRecord(self::NEW_CLASS, 'current');

        ob_start();
        RemapExposingDatabaseAdmin::create()->remapField(DataChangeRecord::class, 'ClassName');
        ob_end_clean();

        $this->assertSame([$legacy => self::NEW_CLASS, $current => self::NEW_CLASS], $this->classNames());
    }

    public function testLegacyAndEmptyClassNamesMigratedAndJoinRowsKept()
    {
        $this->widenClassNameColumn();
        $legacy = $this->insertRecord(self::OLD_CLASS, 'legacy');
        $empty = $this->insertRecord('', 'written during the swap');
        $current = $this->insertRecord(self::NEW_CLASS, 'current');
        $this->joinPage($legacy, 11);
        $this->joinPage($legacy, 12);
        $this->joinPage($empty, 13);
        $this->joinPage($current, 11);
        $joins = $this->joinRows();
        $count = DataChangeRecord::get()->count();

        $output = $this->build();

        $this->assertStringContainsString('Updated 2 legacy change records in DataChangeRecord.ClassName', $output);
        $this->assertSame(
            [$legacy => self::NEW_CLASS, $empty => self::NEW_CLASS, $current => self::NEW_CLASS],
            $this->classNames()
        );
        $this->assertSame($joins, $this->joinRows());
        $this->assertSame($count, DataChangeRecord::get()->count());
        $record = DataChangeRecord::get()->byID($legacy);
        $this->assertInstanceOf(DataChangeRecord::class, $record);
        $this->assertSame('legacy', $record->ObjectTitle);

        // a second build changes nothing
        $this->assertSame('', trim($this->build()));
        $this->assertSame($joins, $this->joinRows());
    }

    public function testLegacyPermissionCodesMigrated()
    {
        $group = Group::create(['Title' => 'Auditors']);
        $group->write();
        Permission::grant($group->ID, self::OLD_CODE);
        Permission::grant($group->ID, 'CMS_ACCESS_CMSMain');
        $role = PermissionRole::create(['Title' => 'Audit role']);
        $role->write();
        PermissionRoleCode::create(['Code' => self::OLD_CODE, 'RoleID' => $role->ID])->write();
        PermissionRoleCode::create(['Code' => 'CMS_ACCESS_AssetAdmin', 'RoleID' => $role->ID])->write();
        $member = Member::create(['Email' => 'auditor@example.com', 'FirstName' => 'Audit']);
        $member->write();
        $member->Groups()->add($group);

        $this->logInAs($member);
        // the legacy code grants nothing: the member is sent to the section their other code opens
        $this->assertSame(302, $this->get('admin/datachanges')->getStatusCode());

        $output = $this->build();

        $this->assertStringContainsString('Updated 1 legacy permission codes in Permission.Code', $output);
        $this->assertStringContainsString('Updated 1 legacy permission codes in PermissionRoleCode.Code', $output);
        $this->assertEqualsCanonicalizing(
            ['CMS_ACCESS_DataChangeAdmin', 'CMS_ACCESS_CMSMain'],
            Permission::get()->filter('GroupID', $group->ID)->column('Code')
        );
        $this->assertEqualsCanonicalizing(
            ['CMS_ACCESS_DataChangeAdmin', 'CMS_ACCESS_AssetAdmin'],
            PermissionRoleCode::get()->filter('RoleID', $role->ID)->column('Code')
        );
        $this->assertSame(0, Permission::get()->filter('Code', self::OLD_CODE)->count());
        $this->assertSame(200, $this->get('admin/datachanges')->getStatusCode(), 'The migrated code opens the admin');

        // a second build changes nothing
        $this->assertSame('', trim($this->build()));
        $this->assertSame(1, Permission::get()->filter('Code', 'CMS_ACCESS_DataChangeAdmin')->count());
    }

    public function testQueuedPruningJobsMigrated()
    {
        if (!class_exists(QueuedJobDescriptor::class)) {
            $this->markTestSkipped('symbiote/silverstripe-queuedjobs is not installed');
        }
        $legacy = QueuedJobDescriptor::create([
            'JobTitle' => 'Prune data change track entries before 2024-01-01 00:00:00',
            'Implementation' => DataChangeRecord::LEGACY_PRUNE_JOB_CLASS,
        ]);
        $legacy->write();
        $other = QueuedJobDescriptor::create(['JobTitle' => 'Other', 'Implementation' => 'Some\\Other\\Job']);
        $other->write();

        $output = $this->build();

        $this->assertStringContainsString('Updated 1 legacy queued pruning jobs', $output);
        $this->assertSame(
            PruneChangesBeforeJob::class,
            QueuedJobDescriptor::get()->byID($legacy->ID)->Implementation
        );
        $this->assertSame('Some\\Other\\Job', QueuedJobDescriptor::get()->byID($other->ID)->Implementation);
        $this->assertSame('', trim($this->build()));
    }
}
