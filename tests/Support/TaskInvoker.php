<?php

namespace Dynamic\ChangeTracker\Tests\Support;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/**
 * Runs a build task with the given parameters and returns what it printed. Running a task differs between
 * Silverstripe 5 and 6, so this is the one test file that differs between the module's release lines.
 */
class TaskInvoker
{
    /**
     * @param BuildTask $task
     * @param array $parameters as they would be given in the query string
     * @return string the task's output
     */
    public static function run(BuildTask $task, array $parameters = []): string
    {
        $request = new HTTPRequest('GET', 'dev/tasks', $parameters);
        ob_start();
        try {
            $task->run($request);
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }
}
