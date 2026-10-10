<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RetryArchiveInspection extends Command
{
    private const int CHUNK_SIZE = 1000;

    protected $signature = 'releases:retry-archive-inspection
                            {--limit=0 : Max releases to queue (0 = no limit)}
                            {--dry-run : Count the affected releases by archive type without queueing them}';

    protected $description = 'Queue releases whose archives could not be read (passwordstatus -1, haspreview 0) for one more post-processing pass.';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));

        if ($this->option('dry-run')) {
            $counts = ['7z' => 0, 'rar' => 0, 'zip' => 0, 'unknown' => 0];
            $this->eligible()->select(['id', 'name'])->lazyById(self::CHUNK_SIZE)->each(
                function (Release $release) use (&$counts, $limit): bool {
                    $counts[$this->archiveType((string) $release->name)]++;

                    return $limit === 0 || array_sum($counts) < $limit;
                },
            );

            $this->table(['Archive type', 'Releases'], array_map(
                static fn (string $type, int $count): array => [$type, $count],
                array_keys($counts),
                $counts,
            ));
            $this->info(sprintf('Dry run: %d release(s) would be queued.', array_sum($counts)));

            return self::SUCCESS;
        }

        $queued = 0;
        do {
            $chunk = $limit > 0 ? min(self::CHUNK_SIZE, $limit - $queued) : self::CHUNK_SIZE;
            $ids = $this->eligible()->orderBy('id')->limit($chunk)->pluck('id')->all();
            if ($ids === []) {
                break;
            }

            $queued += $this->eligible()->whereIn('id', $ids)->update([
                'haspreview' => -1,
                AdditionalCandidateQuery::ARCHIVE_RETRY_COLUMN => now(),
            ]);
        } while ($limit === 0 || $queued < $limit);

        $this->info(sprintf('Queued %d release(s) for one more archive inspection.', $queued));

        return self::SUCCESS;
    }

    /**
     * @return Builder<Release>
     */
    private function eligible(): Builder
    {
        return Release::query()
            ->where('passwordstatus', -1)
            ->where('haspreview', 0)
            ->where('nzbstatus', 1)
            ->whereNull(AdditionalCandidateQuery::ARCHIVE_RETRY_COLUMN);
    }

    private function archiveType(string $name): string
    {
        return match (true) {
            preg_match('/\.7z(\.\d{3})?\b/i', $name) === 1 => '7z',
            preg_match('/\.(rar|r\d{2,3}|part\d+)\b/i', $name) === 1 => 'rar',
            preg_match('/\.zipx?\b/i', $name) === 1 => 'zip',
            default => 'unknown',
        };
    }
}
