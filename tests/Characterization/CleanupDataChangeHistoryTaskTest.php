<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Job\CleanupDataChangeHistoryTask;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use Dynamic\ChangeTracker\Tests\Support\TaskInvoker;
use SilverStripe\ORM\DB;

/**
 * What the history cleanup task does
 */
class CleanupDataChangeHistoryTaskTest extends CharacterizationTestCase
{
    private const MISSING_OLDER = "Please specify an 'older' param with a date older than which to prune"
        . " (in strtotime friendly format)<br/>\n";

    /**
     * Four change records, created a year ago, half a year ago, a month ago and now, each with one join row
     *
     * @return int[] record ids, oldest first
     */
    private function history(): array
    {
        $page = $this->makePage('Owner');
        $object = $this->makeObject('History', ['PageID' => $page->ID]);
        foreach (['one', 'two', 'three'] as $title) {
            $object->Title = $title;
            $object->write();
            $this->resetTracking();
        }
        $ids = array_map('intval', DataChangeRecord::get()
            ->filter('ChangeRecordClass', get_class($object))
            ->sort('ID', 'ASC')
            ->column('ID'));
        $this->assertCount(4, $ids);
        foreach (['-12 months', '-6 months', '-1 month', 'now'] as $index => $when) {
            DB::prepared_query(
                'UPDATE "DataChangeRecord" SET "Created" = ? WHERE "ID" = ?',
                [date('Y-m-d H:i:s', strtotime($when)), $ids[$index]]
            );
        }
        return $ids;
    }

    /**
     * @return int[]
     */
    private function remaining(array $ids): array
    {
        return array_values(array_intersect($ids, array_map('intval', DataChangeRecord::get()->column('ID'))));
    }

    private function joinRowsFor(array $ids): int
    {
        return (int)DB::query(
            'SELECT COUNT(*) FROM "DataChangeRecord_AffectedPages" WHERE "DataChangeRecordID" IN ('
            . implode(',', array_map('intval', $ids)) . ')'
        )->value();
    }

    public function testRequiresOlder()
    {
        $this->assertSame(self::MISSING_OLDER, TaskInvoker::run(CleanupDataChangeHistoryTask::create()));
        $this->assertSame(
            self::MISSING_OLDER,
            TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => 'not a date'])
        );
    }

    public function testImportOfTheRecordClassIsBroken()
    {
        $ids = $this->history();

        // the task imports a class that does not exist, so it fails as soon as it looks for records
        try {
            TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => '-3 months', 'run' => 1]);
            $this->fail('The task found its record class');
        } catch (\Error $e) {
            $this->assertSame('Class "Dynamic\ChangeTracker\DataChangeRecord" not found', $e->getMessage());
        }

        $this->assertSame($ids, $this->remaining($ids));
        $this->assertSame(4, $this->joinRowsFor($ids));
    }
}
