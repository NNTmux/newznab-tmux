<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CollectReleaseFingerprintMetadata;
use App\Models\Release;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class BackfillReleaseFingerprintMetadata extends Command
{
    protected $signature = 'releases:fingerprint-backfill
                            {--limit=100 : Maximum number of releases to queue}
                            {--after-id=0 : Resume after this release ID}
                            {--delay-ms=0 : Delay between queue submissions in milliseconds}
                            {--dry-run : Show matching releases without queueing jobs}';

    protected $description = 'Queue resumable collection of public release format and NNTP metadata';

    public function handle(): int
    {
        $limit = max(1, min(10_000, (int) $this->option('limit')));
        $afterId = max(0, (int) $this->option('after-id'));
        $delayMs = max(0, min(60_000, (int) $this->option('delay-ms')));
        $releaseIds = Release::query()
            ->where('nzbstatus', 1)
            ->where('id', '>', $afterId)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('fingerprintMetadata')
                    ->orWhereHas('fingerprintMetadata', static function (Builder $metadata): void {
                        $metadata->where('status', '!=', 'complete');
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->table(['release_id'], $releaseIds->map(static fn (int|string $id): array => [(string) $id])->all());
            $this->info('Dry run: '.$releaseIds->count().' matching releases; nothing was queued.');

            return self::SUCCESS;
        }

        foreach ($releaseIds as $releaseId) {
            CollectReleaseFingerprintMetadata::dispatch((int) $releaseId);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $this->info('Queued '.$releaseIds->count().' releases. Resume with --after-id='.($releaseIds->last() ?? $afterId).'.');

        return self::SUCCESS;
    }
}
