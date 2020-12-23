<?php

declare(strict_types=1);

/**
 * This example demonstrates a very simple system for running executable classes (jobs)
 * that have been serialized in a message queue.
 *
 * The executables take parameters in their constructors and implement __invoke method to become callable.
 * The __invoke method receives any services that are needed for the job to execute
 * (e.g. database connection, mailing service, logger, etc.).
 */

use Dakujem\Sleeve;
use Dakujem\Wire\Genie;

class AnswerQuestionJob
{
    private string $theQuestion;

    public function __construct(string $theQuestion)
    {
        $this->theQuestion = $theQuestion;
    }

    /**
     * Note the required service in parameters:
     */
    public function __invoke(Oracle $oracle): ?string
    {
        return $oracle->answer($this->theQuestion);
    }
}

class Queue
{
    public function getNextJob()
    {
        // This would be fetched from the message queue or a database or wherever you store async jobs in.
        $message = '
        {
            "class": "AnswerQuestionJob",
            "args": ["Will there be a storm tomorrow?"]
        }
        ';
        return json_decode($message);
    }
}

class Oracle
{
    public function answer(string $question): ?string
    {
        $seed = rand(-10, 10);
        return $seed > 0 ? 'yes' : ($seed < 0 ? 'no' : null);
    }
}

// The service container has its services registered by their class name:
$container = new Sleeve([
    Oracle::class => fn() => new Oracle(),
]);

if (!$container->get(Oracle::class) instanceof Oracle) {
    // this won't happen
    throw new Exception('WTF.');
}

// Fetch the next job from the job queue:
[$className, $args] = (new  Queue())->getNextJob();

// Instantiate the job:
$job = new $className(...$args);

if (!is_callable($job)) {
    throw new Exception('The job is not callable.');
}

// Since we have no idea what services the job needs to be executed
// and there may be multiple jobs with different signatures,
// we utilize dynamic dependency resolution:
$g = new Genie($container);
$g->invoke($job);

// That's it!
// Our job is called and the Oracle class is fetched from the container and delivered as an argument.

