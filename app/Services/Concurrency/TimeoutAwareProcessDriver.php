<?php

declare(strict_types=1);

namespace App\Services\Concurrency;

use Closure;
use Exception;
use Illuminate\Concurrency\ProcessDriver;
use Illuminate\Console\Application;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Process\Pool;
use Illuminate\Support\Arr;
use Laravel\SerializableClosure\SerializableClosure;

class TimeoutAwareProcessDriver extends ProcessDriver
{
    public function __construct(
        ProcessFactory $processFactory,
        protected int $timeout
    ) {
        parent::__construct($processFactory);
    }

    /**
     * Run the given tasks concurrently and return an array containing the results.
     *
     * @throws \Throwable
     */
    public function run(Closure|array $tasks): array
    {
        $command = Application::formatCommandString('invoke-serialized-closure');

        $results = $this->processFactory->pool(function (Pool $pool) use ($tasks, $command) {
            foreach (Arr::wrap($tasks) as $key => $task) {
                $pool->as((string) $key)
                    ->timeout($this->timeout)
                    ->path(base_path())
                    ->env([
                        'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(
                            serialize(new SerializableClosure($task))
                        ),
                    ])
                    ->command($command);
            }
        })->start()->wait();

        return $results->collect()->mapWithKeys(function ($result, $key) {
            if ($result->failed()) {
                throw new Exception('Concurrent process failed with exit code ['.$result->exitCode().']. Message: '.$result->errorOutput());
            }

            $output = $result->output();

            if (($pos = strpos($output, "\x1f\x8b")) !== false) {
                $output = substr($output, 0, $pos);
            }

            $decodedResult = json_decode($output, true);

            if (! $decodedResult['successful']) {
                throw new $decodedResult['exception'](
                    ...(! empty(array_filter($decodedResult['parameters']))
                        ? $decodedResult['parameters']
                        : [$decodedResult['message']])
                );
            }

            return [$key => unserialize($decodedResult['result'])];
        })->all();
    }
}
