<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LogViewer\Index\LogIndex;
use App\Services\LogViewer\Index\LogIndexer;
use App\Services\LogViewer\LogFile;
use Illuminate\Console\Command;
use Throwable;

final class IndexLogs extends Command
{
    protected $signature = 'nntmux:index-logs
                            {--file=* : Only index these log files (paths relative to storage/logs)}
                            {--reset : Drop the log index and index every file again from the start}';

    protected $description = 'Tail storage/logs into the Manticore log index used by the admin log viewer search';

    public function handle(LogIndex $index, LogIndexer $indexer): int
    {
        if (! $index->isEnabled()) {
            $this->info('The log index is disabled (LOG_VIEWER_INDEX_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $index->isAvailable()) {
            $this->error('Manticore is not reachable; log viewer searches fall back to grep.');

            return self::FAILURE;
        }

        try {
            if ((bool) $this->option('reset')) {
                $indexer->reset();
                $this->info('Log index dropped; re-indexing from the start.');
            }

            $stats = $indexer->run(array_values(array_map('strval', (array) $this->option('file'))));
        } catch (Throwable $exception) {
            $this->error('Log indexing failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Indexed %s entries (%s) from %d file(s); %d file(s) purged.',
            number_format($stats['entries']),
            LogFile::formatBytes($stats['bytes']),
            $stats['files'],
            $stats['purged'],
        ));

        return self::SUCCESS;
    }
}
