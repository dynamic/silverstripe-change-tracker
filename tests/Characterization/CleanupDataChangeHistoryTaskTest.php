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

    public function testPrunesBelowTheNewestOldRecord()
    {
        $ids = $this->history();

        $output = TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => '-3 months', 'run' => 1]);

        $this->assertMatchesRegularExpression(
            '/^Pruning records older than \d{4}-\d\d-\d\d \d\d:\d\d:\d\d \(ID ' . $ids[1] . '\)<br\/>\n'
            . 'Removed \d+ affected page rows of pruned records<br\/>\n$/',
            $output
        );
        // records older than the date are found, then every id below the newest of them is deleted
        $this->assertSame([$ids[1], $ids[2], $ids[3]], $this->remaining($ids));
        // and the join rows of the deleted records go with them
        $this->assertSame(0, $this->joinRowsFor([$ids[0]]));
        $this->assertSame(3, $this->joinRowsFor($ids));
        $this->assertSame(0, (int)DB::query(
            'SELECT COUNT(*) FROM "DataChangeRecord_AffectedPages" WHERE "DataChangeRecordID" NOT IN'
            . ' (SELECT "ID" FROM "DataChangeRecord")'
        )->value());
    }

    public function testDryRunWithoutRun()
    {
        $ids = $this->history();

        $output = TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => '-3 months']);

        $this->assertStringEndsWith(
            "Dry run performed, please supply the run=1 parameter to actually execute the deletion!<br/>\n",
            $output
        );
        $this->assertSame($ids, $this->remaining($ids));
        $this->assertSame(4, $this->joinRowsFor($ids));
    }

    public function testRecentDateNeedsForce()
    {
        $ids = $this->history();

        $output = TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => '-2 weeks', 'run' => 1]);

        $this->assertStringStartsWith(
            "To cleanup data more recent than 3 months, please supply the 'force' parameter as well as the run"
            . " parameter, swapping to dry run <br/>\n",
            $output
        );
        $this->assertStringContainsString('(ID ' . $ids[2] . ')', $output);
        $this->assertStringEndsWith("Dry run performed, please supply the run=1 parameter to actually execute the deletion!<br/>\n", $output);
        $this->assertSame($ids, $this->remaining($ids));

        TaskInvoker::run(CleanupDataChangeHistoryTask::create(), ['older' => '-2 weeks', 'run' => 1, 'force' => 1]);
        $this->assertSame([$ids[2], $ids[3]], $this->remaining($ids));
    }
}
