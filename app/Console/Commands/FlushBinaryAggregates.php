<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Binaries\BinariesConfig;
use App\Services\Binaries\BinaryHandler;
use App\Services\Binaries\CollectionHandler;
use App\Services\Binaries\DeferredAggregateQueue;
use App\Support\SqlError;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('binaries:flush-aggregates
    {--batch=5000 : Binary ids taken from the queue per pass}
    {--seconds=50 : Stop taking new batches after this many seconds}')]
#[Description('Recount part totals for binaries queued by header storage (NN_DEFER_HEADER_AGGREGATES)')]
final class FlushBinaryAggregates extends Command
{
    public function handle(DeferredAggregateQueue $queue): int
    {
        $config = BinariesConfig::fromSettings();
        $binaries = new BinaryHandler($config->sqlChunkSize);
        $collections = new CollectionHandler(sqlChunkSize: $config->sqlChunkSize);
        $batch = max(1, (int) $this->option('batch'));
        $deadline = microtime(true) + max(1, (int) $this->option('seconds'));
        $flushed = 0;

        while (microtime(true) < $deadline) {
            $ids = $queue->pop($batch);
            if ($ids === []) {
                break;
            }
            // Ascending ids: rows are always locked in the same order.
            sort($ids);
            $collectionIds = DB::table('binaries')->whereIn('id', $ids)->distinct()
                ->pluck('collections_id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();

            if (! $binaries->refreshAggregates($ids, $config->sqlChunkSize)
                || ! $collections->refreshAggregates($collectionIds, $config->sqlChunkSize)) {
                $queue->push($ids);
                $exception = $binaries->getLastException() ?? $collections->getLastException();
                // Release processing recounts the same rows; a lock conflict just waits for the next run.
                if ($exception !== null && SqlError::isTransientLock($exception)) {
                    return self::SUCCESS;
                }
                if ($exception !== null) {
                    SqlError::logFailure('Binary aggregate flush failed', $exception);
                }
                $this->error('Aggregate refresh failed; ids returned to the queue.');

                return self::FAILURE;
            }
            $flushed += \count($ids);
        }

        if ($this->output->isVerbose()) {
            $this->line(sprintf('Recounted %s binaries, %s still queued.', number_format($flushed), number_format($queue->size())));
        }

        return self::SUCCESS;
    }
}
