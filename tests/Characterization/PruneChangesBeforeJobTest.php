<?php

namespace Dynamic\ChangeTracker\Tests\Characterization;

use Dynamic\ChangeTracker\Job\PruneChangesBeforeJob;
use Dynamic\ChangeTracker\Model\DataChangeRecord;
use SilverStripe\ORM\DB;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * What the nightly pruning job does. It needs the queued jobs module and is skipped without it.
 */
class PruneChangesBeforeJobTest extends CharacterizationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(AbstractQueuedJob::class)) {
            $this->markTestSkipped('symbiote/silverstripe-queuedjobs is not installed');
        }
    }

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
        // only the history records, so "every id below the newest old one" is easy to read
        $in = implode(',', $ids);
        DB::query('DELETE FROM "DataChangeRecord" WHERE "ID" NOT IN (' . $in . ')');
        DB::query('DELETE FROM "DataChangeRecord_AffectedPages" WHERE "DataChangeRecordID" NOT IN (' . $in . ')');
        return $ids;
    }

    private function joinRows(): int
    {
        return (int)DB::query('SELECT COUNT(*) FROM "DataChangeRecord_AffectedPages"')->value();
    }

    private function orphanJoinRows(): int
    {
        return (int)DB::query(
            'SELECT COUNT(*) FROM "DataChangeRecord_AffectedPages" "j" LEFT JOIN "DataChangeRecord" "d"'
            . ' ON "d"."ID" = "j"."DataChangeRecordID" WHERE "d"."ID" IS NULL'
        )->value();
    }

    public function testPruneKeepsTheNewestOldRecordAndRequeues()
    {
        $ids = $this->history();
        $this->assertSame(4, $this->joinRows());

        $job = new PruneChangesBeforeJob('-3 months');
        $job->process();

        // records older than the date are found, then every id below the newest of them is deleted, so that one stays
        $this->assertSame(
            [$ids[1], $ids[2], $ids[3]],
            array_map('intval', DataChangeRecord::get()->sort('ID')->column('ID'))
        );
        $this->assertTrue($job->jobFinished());
        $next = QueuedJobDescriptor::get()->filter('Implementation', PruneChangesBeforeJob::class)->first();
        $this->assertNotNull($next);
        $this->assertSame(date('Y-m-d 03:00:00', strtotime('tomorrow')), $next->StartAfter);
    }

    public function testPruneLeavesTheJoinRowsOfDeletedRecords()
    {
        $this->history();

        (new PruneChangesBeforeJob('-3 months'))->process();

        $this->assertSame(1, $this->orphanJoinRows());
        $this->assertSame(4, $this->joinRows());
    }
}
