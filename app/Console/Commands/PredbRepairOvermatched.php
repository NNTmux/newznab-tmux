<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use App\Services\NameFixing\PredbMatchGuard;
use App\Services\NameFixing\ReleaseNameResetPayload;
use App\Support\PredbSearchDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PredbRepairOvermatched extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'predb:repair-overmatched
                            {--source=* : PreDB sources to check (default: NN_PREDB_FILENAME_UNTRUSTED_SOURCES)}
                            {--min-releases= : Repair PreDB rows linked to more than this many releases (default: NN_PREDB_MAX_RELEASES_PER_PRE)}
                            {--limit=100 : Maximum number of PreDB rows to repair in one run}
                            {--execute : Reset the releases; without it the command only reports}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset releases that a bad PreDB row (e.g. a generic xrel filename) renamed in bulk, so they are name-fixed again.';

    private const SAMPLE_NAMES = 3;

    public function handle(PredbMatchGuard $guard): int
    {
        $sources = array_values(array_filter(array_map(
            static fn (mixed $source): string => strtolower(trim((string) $source)),
            (array) $this->option('source')
        )));
        if ($sources === []) {
            $sources = $guard->untrustedFilenameSources();
        }

        if ($sources === []) {
            $this->error('No PreDB sources to check. Pass --source or set NN_PREDB_FILENAME_UNTRUSTED_SOURCES.');

            return self::FAILURE;
        }

        $minReleases = $this->option('min-releases') !== null ? (int) $this->option('min-releases') : $guard->maxReleasesPerPre();
        if ($minReleases < 1) {
            $this->error('--min-releases must be at least 1.');

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $predbRows = $this->overmatchedPredbRows($sources, $minReleases, max(1, (int) $this->option('limit')));

        if ($predbRows->isEmpty()) {
            $this->info('No PreDB rows from ['.implode(', ', $sources).'] are linked to more than '.$minReleases.' releases.');

            return self::SUCCESS;
        }

        $report = [];
        $resetTotal = $searchSyncFailures = 0;

        foreach ($predbRows as $pre) {
            $title = (string) $pre->title;
            $toReset = 0;
            $samples = [];

            Release::query()
                ->select(['id', 'name', 'searchname'])
                ->where('predb_id', (int) $pre->id)
                ->chunkById(500, function (Collection $releases) use ($title, $execute, &$toReset, &$samples, &$searchSyncFailures): void {
                    /** @var Release $release */
                    foreach ($releases as $release) {
                        // A release whose usenet name already is the PreDB title was matched correctly.
                        if (PredbSearchDocument::exactValue((string) $release->name) === PredbSearchDocument::exactValue($title)) {
                            continue;
                        }

                        $toReset++;
                        if (count($samples) < self::SAMPLE_NAMES) {
                            $samples[] = (string) $release->name;
                        }

                        if (! $execute) {
                            continue;
                        }

                        Release::query()->whereKey($release->id)->update(ReleaseNameResetPayload::for($release));

                        try {
                            Search::updateRelease($release->id);
                        } catch (\Throwable $e) {
                            $searchSyncFailures++;
                            Log::warning('Failed to update search index while repairing an over-matched PreDB release', [
                                'release_id' => $release->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

            $resetTotal += $toReset;
            $report[] = [(int) $pre->id, $title, (string) $pre->source, (int) $pre->linked, $toReset, implode(' | ', $samples)];
        }

        $this->table(['PreDB ID', 'Title', 'Source', 'Linked', $execute ? 'Reset' : 'To reset', 'Sample release names'], $report);

        if (! $execute) {
            $this->warn('Dry run: '.number_format($resetTotal).' release(s) would be reset. Run again with --execute to apply.');

            return self::SUCCESS;
        }

        $this->info('Reset '.number_format($resetTotal).' release(s).');
        if ($searchSyncFailures > 0) {
            $this->warn($searchSyncFailures.' release(s) were reset but could not be synced to the search index. Check logs for details.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $sources
     * @return Collection<int, object{id: int, title: string, source: string, linked: int}>
     */
    private function overmatchedPredbRows(array $sources, int $minReleases, int $limit): Collection
    {
        /** @var Collection<int, object{id: int, title: string, source: string, linked: int}> */
        return DB::table('predb as p')
            ->join('releases as r', 'r.predb_id', '=', 'p.id')
            ->whereIn('p.source', $sources)
            ->groupBy('p.id', 'p.title', 'p.source')
            ->havingRaw('COUNT(*) > ?', [$minReleases])
            ->orderByDesc('linked')
            ->limit($limit)
            ->get(['p.id', 'p.title', 'p.source', DB::raw('COUNT(*) AS linked')]);
    }
}
