<?php

namespace Dynamic\ChangeTracker\Tests\Support;

use ReflectionMethod;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\HttpRequestInput;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs a build task with the given parameters and returns what it printed. Running a task differs between
 * Silverstripe 5 and 6, so this is the one test file that differs between the module's release lines.
 *
 * Silverstripe 6 has BuildTask::run() wrap the task's own output in a heading and a timing line, so this calls
 * execute() directly and captures the HTML output the task writes, as the HTTP route would give it.
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
        $input = HttpRequestInput::create($request, $task->getOptions());
        $buffer = new BufferedOutput();
        $output = new PolyOutput(PolyOutput::FORMAT_HTML, OutputInterface::VERBOSITY_NORMAL, false, $buffer);
        (new ReflectionMethod($task, 'execute'))->invoke($task, $input, $output);
        return $buffer->fetch();
    }
}
