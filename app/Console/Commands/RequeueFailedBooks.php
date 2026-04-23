<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\NameFixing\Extractors\ObfuscatedSubjectExtractor;
use Illuminate\Console\Command;

class RequeueFailedBooks extends Command
{
    protected $signature = 'books:requeue-failed
                            {--dry-run : Preview affected releases without writing changes}
                            {--limit=0 : Max releases to inspect (0 = no limit)}
                            {--only-obfuscated : Only requeue rows with obfuscated subject that can be normalized}
                            {--normalize-obfuscated-all : Normalize obfuscated names across all book/audiobook releases, not just bookinfo_id=-2}';

    protected $description = 'Requeue failed book metadata rows (bookinfo_id = -2) and normalize obfuscated search names.';

    public function handle(ObfuscatedSubjectExtractor $extractor): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $onlyObfuscated = (bool) $this->option('only-obfuscated');
        $normalizeObfuscatedAll = (bool) $this->option('normalize-obfuscated-all');

        $query = Release::query()
            ->select(['id', 'name', 'searchname', 'categories_id', 'bookinfo_id'])
            ->where(function ($builder): void {
                $builder->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])
                    ->orWhere('categories_id', Category::MUSIC_AUDIOBOOK);
            })
            ->orderBy('id');

        if ($normalizeObfuscatedAll) {
            $query->where(function ($builder): void {
                $builder
                    ->where('searchname', 'like', 'N:/NZB%')
                    ->orWhere('searchname', 'like', 'N_NZB_%')
                    ->orWhere('name', 'like', 'N:/NZB%')
                    ->orWhere('name', 'like', 'N_NZB_%');
            });
        } else {
            $query->where('bookinfo_id', -2);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();
        $total = $releases->count();

        if ($total === 0) {
            $this->info('No failed book releases found (bookinfo_id = -2).');

            return self::SUCCESS;
        }

        $queued = 0;
        $renamed = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($releases as $release) {
            $bar->advance();

            $normalized = $extractor->extract((string) $release->searchname)
                ?? $extractor->extract((string) $release->name);

            if ($onlyObfuscated && $normalized === null) {
                $skipped++;

                continue;
            }

            $updates = [];
            $shouldRequeue = (int) $release->bookinfo_id === -2;
            if ($shouldRequeue) {
                $updates['bookinfo_id'] = null;
            }
            if ($normalized !== null && $normalized !== $release->searchname) {
                $updates['searchname'] = $normalized;
                $updates['isrenamed'] = 1;
            }

            if ($updates === []) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                if (isset($updates['searchname'])) {
                    $this->newLine();
                    $this->line(sprintf(
                        '[dry-run] #%d searchname: "%s" -> "%s"',
                        (int) $release->id,
                        (string) $release->searchname,
                        (string) $updates['searchname']
                    ));
                }

                $queued++;
                if (isset($updates['searchname'])) {
                    $renamed++;
                }

                continue;
            }

            Release::query()->where('id', $release->id)->update($updates);
            Search::updateRelease((int) $release->id);

            $queued++;
            if (isset($updates['searchname'])) {
                $renamed++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run complete. Inspected: %d, would apply updates: %d, would normalize names: %d, skipped: %d.',
                $total,
                $queued,
                $renamed,
                $skipped
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Requeued %d failed book release(s). Normalized %d searchname value(s). Skipped: %d.',
            $queued,
            $renamed,
            $skipped
        ));
        $this->line('Next step: run your books postprocess (e.g. `php artisan update:postprocess book`).');

        return self::SUCCESS;
    }
}
