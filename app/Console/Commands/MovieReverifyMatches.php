<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Services\MovieService;
use App\Services\Releases\ReleaseBrowseService;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MovieReverifyMatches extends Command
{
    protected $signature = 'movie:reverify-matches
                            {--dry-run : Only report mismatches without changing anything}
                            {--limit= : Maximum number of releases to check}
                            {--repair-links : Requeue releases whose IMDb lookup failed but kept an old movieinfo link, and link releases to existing movieinfo rows}';

    protected $description = 'Re-verify movie release IMDb matches against the title/year parsed from their searchname';

    public function handle(MovieService $movieService): int
    {
        $movieService->echooutput = false;

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $limit = is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;

        if ($this->option('repair-links')) {
            return $this->repairLinks($dryRun, $limit);
        }

        $query = Release::query()
            ->select(['id', 'searchname', 'imdbid'])
            ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
            ->whereNotNull('imdbid')
            ->where('imdbid', '<>', '')
            ->whereNotIn('imdbid', imdb_id_pending_values());

        $checked = 0;
        $skipped = 0;
        $mismatched = [];

        $process = function ($releases) use (&$checked, &$skipped, &$mismatched, $movieService, $limit): bool {
            foreach ($releases as $release) {
                if ($limit !== null && $checked >= $limit) {
                    return false;
                }

                $checked++;
                $result = $movieService->verifyReleaseMovieMatch($release->searchname, (string) $release->imdbid);

                if ($result === null) {
                    $skipped++;

                    continue;
                }

                if ($result === false) {
                    $mismatched[] = (int) $release->id;
                    $this->warn("Mismatch: release {$release->id} ({$release->searchname}) matched to tt{$release->imdbid}");
                }
            }

            return true;
        };

        if ($limit !== null) {
            $process($query->limit($limit)->get());
        } else {
            $query->chunkById(500, $process);
        }

        $this->info("Checked {$checked} movie releases, skipped {$skipped} unverifiable, found ".count($mismatched).' mismatches.');

        if ($dryRun) {
            if ($mismatched !== []) {
                $this->info('Dry run: no changes made.');
            }

            return self::SUCCESS;
        }

        foreach (array_chunk($mismatched, 100) as $chunk) {
            Release::query()->whereIn('id', $chunk)->update(['imdbid' => null, 'movieinfo_id' => null]);
            ReleaseSearchIndexSync::forIds($chunk);
        }

        if ($mismatched !== []) {
            $this->info('Reset imdbid/movieinfo_id for '.count($mismatched).' mismatched releases.');
        }

        return self::SUCCESS;
    }

    /**
     * A rename used to reset imdbid but keep movieinfo_id, and a failed lookup then stored
     * imdbid = '' next to the old (usually wrong) movie. Those releases get a fresh lookup.
     * Releases whose imdbid already has a movieinfo row but no link get linked to it.
     */
    private function repairLinks(bool $dryRun, ?int $limit): int
    {
        $stale = $this->movieReleases()
            ->where('imdbid', '')
            ->where('movieinfo_id', '>', 0);

        $unlinked = $this->movieReleases()
            ->whereNotNull('imdbid')
            ->where('imdbid', '<>', '')
            ->whereNotIn('imdbid', imdb_id_pending_values())
            ->where(static function (Builder $query): void {
                $query->whereNull('movieinfo_id')->orWhere('movieinfo_id', 0);
            })
            ->whereExists(static function ($query): void {
                $query->selectRaw('1')->from('movieinfo')->whereColumn('movieinfo.imdbid', 'releases.imdbid');
            });

        $requeued = $this->eachChunk($stale, ['id'], $dryRun, $limit, static function (Collection $releases): void {
            Release::query()->whereIn('id', $releases->pluck('id'))->update(['imdbid' => null, 'movieinfo_id' => null]);
        });

        $linked = $this->eachChunk($unlinked, ['id', 'imdbid'], $dryRun, $limit, static function (Collection $releases): void {
            $movieInfoIds = MovieInfo::query()
                ->whereIn('imdbid', $releases->pluck('imdbid')->unique()->values())
                ->pluck('id', 'imdbid');

            foreach ($releases->groupBy('imdbid') as $imdbId => $group) {
                $movieInfoId = $movieInfoIds[$imdbId] ?? null;
                if ($movieInfoId === null) {
                    continue;
                }

                Release::query()
                    ->whereIn('id', $group->pluck('id'))
                    ->where(static function (Builder $query): void {
                        $query->whereNull('movieinfo_id')->orWhere('movieinfo_id', 0);
                    })
                    ->update(['movieinfo_id' => $movieInfoId]);
            }
        });

        if (! $dryRun && $requeued + $linked > 0) {
            ReleaseBrowseService::bumpCacheVersion();
        }

        $this->table(['Repair', $dryRun ? 'Would change' : 'Changed'], [
            ['Stale movie links requeued for lookup', number_format($requeued)],
            ['Releases linked to existing movieinfo', number_format($linked)],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return Builder<Release>
     */
    private function movieReleases(): Builder
    {
        return Release::query()->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER]);
    }

    /**
     * Walk the matching releases in id order and repair each chunk, then sync it to the search index.
     *
     * @param  Builder<Release>  $query
     * @param  list<string>  $columns
     * @param  callable(Collection<int, Release>): void  $repair
     */
    private function eachChunk(Builder $query, array $columns, bool $dryRun, ?int $limit, callable $repair): int
    {
        if ($dryRun) {
            $count = (clone $query)->count();

            return $limit === null ? $count : min($count, $limit);
        }

        $changed = 0;
        $query->select($columns)->chunkById(500, function (Collection $releases) use ($repair, $limit, &$changed): bool {
            if ($limit !== null) {
                $releases = $releases->take($limit - $changed);
            }

            $repair($releases);
            ReleaseSearchIndexSync::forIds($releases->pluck('id')->all());
            $changed += $releases->count();

            return $limit === null || $changed < $limit;
        });

        return $changed;
    }
}
