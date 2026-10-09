<?php

namespace Dynamic\ChangeTracker\Job;

use Dynamic\ChangeTracker\Model\DataChangeRecord;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deletes the change records older than the date given as the older option, with their affected page rows.
 *
 * The task is a dry run unless run is given. A date within the last three months also needs force.
 *
 * @author <marcus@symbiote.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CleanupDataChangeHistoryTask extends BuildTask
{
    protected static string $commandName = 'Dynamic-ChangeTracker-Job-CleanupDataChangeHistoryTask';

    protected string $title = 'Cleanup data change history';

    protected static string $description = 'Deletes the data change records older than the date given as the older option,'
        . ' and their affected page rows. A dry run unless the run option is given.';

    public function getOptions(): array
    {
        return [
            new InputOption(
                'older',
                null,
                InputOption::VALUE_REQUIRED,
                'Records older than this date (any strtotime format) are deleted'
            ),
            new InputOption('run', null, InputOption::VALUE_NONE, 'Delete the records. Without it nothing is deleted'),
            new InputOption('force', null, InputOption::VALUE_NONE, 'Allow records from the last three months'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $confirm = (bool)$input->getOption('run');
        $force = (bool)$input->getOption('force');
        $since = $input->getOption('older');

        if (!$since) {
            $this->writeLine($output, "Please specify an 'older' param with a date older than which to prune (in strtotime friendly format)");
            return Command::SUCCESS;
        }

        $since = strtotime((string) $since);
        if (!$since) {
            $this->writeLine($output, "Please specify an 'older' param with a date older than which to prune (in strtotime friendly format)");
            return Command::SUCCESS;
        }

        if ($since > strtotime('-3 months') && !$force) {
            $this->writeLine($output, "To cleanup data more recent than 3 months, please supply the 'force' parameter as well as the run parameter, swapping to dry run ");
            $confirm = false;
        }

        $since = date('Y-m-d H:i:s', $since);

        $items = DataChangeRecord::get()->filter('Created:LessThan', $since);
        $max = $items->max('ID');
        $this->writeLine($output, "Pruning records older than $since (ID $max)");

        if ($confirm && $max) {
            $query = new SQLDelete('DataChangeRecord', '"ID" < \'' . $max . '\'');
            $query->execute();
            $orphans = DataChangeRecord::deleteOrphanAffectedPageRows();
            $this->writeLine($output, "Removed $orphans affected page rows of pruned records");
        } else {
            $this->writeLine($output, 'Dry run performed, please supply the run=1 parameter to actually execute the deletion!');
        }

        return Command::SUCCESS;
    }

    /**
     * The line as the task wrote it in Silverstripe 5: HTML lines end with a <br/> and a newline. Plain text output
     * gets the same words on their own line.
     */
    private function writeLine(PolyOutput $output, string $line): void
    {
        $output->writeForHtml($line . "<br/>\n", false, OutputInterface::OUTPUT_RAW);
        $output->writeForAnsi($line, true, OutputInterface::OUTPUT_RAW);
    }
}
