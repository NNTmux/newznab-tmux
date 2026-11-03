<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RenameApplyResult;
use App\Models\Category;
use App\Models\Release;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\Data\NzbSplitSubject;
use App\Services\NameFixing\Data\ReleaseIdentity;
use App\Services\NameFixing\NzbSplitUnwrapper;
use App\Services\NameFixing\ReleaseUpdateService;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Services\Releases\ReleaseManagementService;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FixNzbSplitNames extends Command
{
    private const int CHUNK_SIZE = 250;

    /**
     * A set this small that declares many files is the tail of a set whose head never became a release.
     */
    private const int ORPHAN_TAIL_MAX_FILES = 2;

    private const int ORPHAN_TAIL_MIN_DECLARED = 10;

    /**
     * @var list<string>
     */
    private const array COLUMNS = [
        'id', 'guid', 'name', 'searchname', 'fromname', 'groups_id', 'categories_id', 'predb_id', 'isrenamed',
        'totalpart', 'size', 'grabs', 'imdbid', 'movieinfo_id', 'musicinfo_id', 'bookinfo_id', 'consoleinfo_id', 'gamesinfo_id',
    ];

    protected $signature = 'releases:fix-nzbsplit
                            {--dry-run : Show what would change without writing or deleting anything}
                            {--repair : Re-unwrap, recategorize and clear wrong metadata links on every NZBSPLIT release}
                            {--include-renamed : Same as --repair (kept for older scripts)}
                            {--purge : Delete incomplete, sample-only and orphan-tail NZBSPLIT releases}
                            {--reset-diverged : With --repair, also replace non-PreDB names that differ from the wrapper title}
                            {--sync-nzb-meta : With --repair, rewrite the name meta of existing NZB files}
                            {--id=* : Only these release ids}
                            {--limit= : Stop after this many releases per phase}';

    protected $description = 'Repair, recategorize or purge NZBSPLIT releases. Without --repair or --purge, repairs releases not yet marked as renamed.';

    private bool $dryRun = false;

    public function handle(
        CategorizationService $categorizationService,
        NzbSplitUnwrapper $unwrapper,
        ReleaseUpdateService $releaseUpdateService,
        ReleaseManagementService $releaseManagement,
        NzbService $nzbService,
    ): int {
        $this->dryRun = (bool) $this->option('dry-run');
        $purge = (bool) $this->option('purge');
        $repairAll = (bool) $this->option('repair') || (bool) $this->option('include-renamed');

        if ($purge) {
            $this->purge($unwrapper, $releaseManagement, $nzbService);
        }

        if ($repairAll || ! $purge) {
            $this->repair($categorizationService, $unwrapper, $releaseUpdateService, $nzbService, onlyUnrenamed: ! $repairAll);
        }

        return self::SUCCESS;
    }

    private function purge(NzbSplitUnwrapper $unwrapper, ReleaseManagementService $releaseManagement, NzbService $nzbService): void
    {
        $this->info($this->dryRun ? 'Purge (dry run)' : 'Purge');

        $counts = ['sample' => 0, 'incomplete' => 0, 'orphan_tail' => 0, 'no_declared_count' => 0, 'kept' => 0];
        $grabbed = 0;
        $releaseImage = new ReleaseImageService;

        $this->eachRelease($this->baseQuery(), function (Collection $releases) use ($unwrapper, $releaseManagement, $nzbService, $releaseImage, &$counts, &$grabbed): void {
            $doomed = [];

            foreach ($releases as $release) {
                $split = $this->parse($unwrapper, $release);
                $reason = $split === null ? null : $this->purgeReason($split, (int) $release->totalpart);

                if ($split !== null && $split->declaredFiles === null && ! $split->isSample) {
                    $counts['no_declared_count']++;
                }

                if ($reason === null) {
                    $counts['kept']++;

                    continue;
                }

                $counts[$reason]++;
                $grabbed += (int) $release->grabs > 0 ? 1 : 0;
                $doomed[] = $release;

                if ($this->dryRun || $this->output->isVerbose()) {
                    $this->line(sprintf(
                        '%s#%d %s: %d/%s files, %s, %d grabs, %s',
                        $this->dryRun ? '[dry-run] ' : '',
                        $release->id,
                        $reason,
                        (int) $release->totalpart,
                        $split->declaredFiles ?? '?',
                        $this->formatBytes((int) $release->size),
                        (int) $release->grabs,
                        $release->searchname
                    ));
                }
            }

            if (! $this->dryRun && $doomed !== []) {
                $releaseManagement->deleteBatch($doomed, $nzbService, $releaseImage);
            }
        });

        $this->table(['Result', 'Releases'], [
            ['sample-only', $counts['sample']],
            ['incomplete (files < declared)', $counts['incomplete']],
            ['orphan tail', $counts['orphan_tail']],
            ['kept (no declared file count)', $counts['no_declared_count']],
            ['kept', $counts['kept']],
            [$this->dryRun ? 'would delete, grabbed by users' : 'deleted, grabbed by users', $grabbed],
        ]);
    }

    private function purgeReason(NzbSplitSubject $split, int $files): ?string
    {
        if ($split->isSample) {
            return 'sample';
        }

        if ($split->declaredFiles === null || $files >= $split->declaredFiles) {
            return null;
        }

        return $files <= self::ORPHAN_TAIL_MAX_FILES && $split->declaredFiles >= self::ORPHAN_TAIL_MIN_DECLARED
            ? 'orphan_tail'
            : 'incomplete';
    }

    private function repair(
        CategorizationService $categorizationService,
        NzbSplitUnwrapper $unwrapper,
        ReleaseUpdateService $releaseUpdateService,
        NzbService $nzbService,
        bool $onlyUnrenamed,
    ): void {
        $this->info(($onlyUnrenamed ? 'Repair releases not marked as renamed' : 'Repair').($this->dryRun ? ' (dry run)' : ''));

        $query = $this->baseQuery();
        if ($onlyUnrenamed) {
            $query->where('isrenamed', 0);
        }

        $resetDiverged = (bool) $this->option('reset-diverged');
        $syncNzbMeta = (bool) $this->option('sync-nzb-meta');
        $counts = ['renamed' => 0, 'recategorized' => 0, 'links' => 0, 'diverged' => 0, 'unchanged' => 0, 'skipped' => 0, 'nzb_meta' => 0];

        $this->eachRelease($query, function (Collection $releases) use ($categorizationService, $unwrapper, $releaseUpdateService, $nzbService, $resetDiverged, $syncNzbMeta, &$counts): void {
            foreach ($releases as $release) {
                $split = $this->parse($unwrapper, $release);
                if ($split === null) {
                    $counts['skipped']++;

                    continue;
                }

                $currentName = (string) $release->searchname;
                [$title, $diverged] = $this->repairedTitle($release, $split, $unwrapper, $resetDiverged);
                if ($diverged) {
                    $counts['diverged']++;
                }

                $category = $categorizationService->determineCategory(
                    $release->groups_id,
                    $title,
                    (string) ($release->fromname ?? ''),
                    sourceName: (string) $release->name,
                    contentFileName: $this->contentFileName((int) $release->id),
                );
                $categoryId = (int) ($category['categories_id'] ?? $release->categories_id);
                $renamed = $title !== $currentName;
                $recategorized = $categoryId !== (int) $release->categories_id;
                $columns = $this->repairColumns($release, $categoryId, $renamed);

                if (! $renamed && $columns === []) {
                    $counts['unchanged']++;
                    if ($syncNzbMeta && ! $this->dryRun && $nzbService->syncNameMetadata($release)) {
                        $counts['nzb_meta']++;
                    }

                    continue;
                }

                if ($this->dryRun) {
                    $this->line(sprintf(
                        '[dry-run] #%d %s -> %s (%d -> %d)%s%s',
                        $release->id,
                        $currentName,
                        $title,
                        (int) $release->categories_id,
                        $categoryId,
                        $diverged ? ' [diverged name kept]' : '',
                        $this->clearedLinks($columns)
                    ));
                    $this->countRepair($counts, $renamed, $recategorized);

                    continue;
                }

                $result = $releaseUpdateService->applyRename(
                    ReleaseIdentity::fromRelease($release),
                    $title,
                    ['isrenamed' => 1, 'iscategorized' => 1, ...$columns],
                    recategorize: false,
                    release: $release,
                );

                if ($result === RenameApplyResult::Applied) {
                    $this->countRepair($counts, $renamed, $recategorized);
                } elseif ($result === RenameApplyResult::Unchanged) {
                    $counts['unchanged']++;
                } else {
                    $counts['skipped']++;
                    $this->warn(sprintf('#%d skipped: %s', $release->id, $result->value));
                }

                if ($syncNzbMeta && $result !== RenameApplyResult::StaleIdentity && $nzbService->syncNameMetadata($release)) {
                    $counts['nzb_meta']++;
                }
            }
        });

        $this->table(['Result', 'Releases'], [
            [$this->dryRun ? 'would rename' : 'renamed', $counts['renamed']],
            [$this->dryRun ? 'would recategorize only' : 'recategorized only', $counts['recategorized']],
            [$this->dryRun ? 'would clear metadata links only' : 'metadata links cleared only', $counts['links']],
            ['diverged name kept (see --reset-diverged)', $counts['diverged']],
            ['unchanged', $counts['unchanged']],
            ['skipped', $counts['skipped']],
            ['NZB name meta rewritten', $counts['nzb_meta']],
        ]);
    }

    /**
     * The wrapper title, unless the current name is a PreDB title or a different name that a
     * name fixer chose (reported as diverged and kept unless --reset-diverged).
     *
     * @return array{0: string, 1: bool} The title and whether a diverged name was kept
     */
    private function repairedTitle(Release $release, NzbSplitSubject $split, NzbSplitUnwrapper $unwrapper, bool $resetDiverged): array
    {
        $current = (string) $release->searchname;

        if ($current === '' || $unwrapper->parse($current) !== null) {
            return [$split->title, false];
        }

        if ((int) $release->predb_id > 0) {
            return [$current, false];
        }

        $currentKey = $this->comparisonKey($current);
        $titleKey = $this->comparisonKey($split->title);
        $sameName = $currentKey !== '' && $titleKey !== ''
            && (str_starts_with($currentKey, $titleKey) || str_starts_with($titleKey, $currentKey));

        if ($sameName || $resetDiverged) {
            return [$split->title, false];
        }

        return [$current, true];
    }

    /**
     * Category plus the metadata links that do not belong to the new category root.
     *
     * @return array<string, mixed>
     */
    private function repairColumns(Release $release, int $categoryId, bool $titleChanged): array
    {
        $columns = [];
        $root = intdiv($categoryId, 1000) * 1000;
        $categoryChanged = $categoryId !== (int) $release->categories_id;

        if ($categoryChanged) {
            $columns['categories_id'] = $categoryId;
        }

        if ($root !== Category::MOVIE_ROOT) {
            if ($release->imdbid !== null && $release->imdbid !== '') {
                $columns['imdbid'] = null;
            }
            if ($release->movieinfo_id !== null) {
                $columns['movieinfo_id'] = null;
            }
        }
        if ($root !== Category::MUSIC_ROOT && $release->musicinfo_id !== null) {
            $columns['musicinfo_id'] = null;
        }
        if ($root !== Category::BOOKS_ROOT && $release->bookinfo_id !== null) {
            $columns['bookinfo_id'] = null;
        }
        if ($root === Category::BOOKS_ROOT && $categoryChanged) {
            $columns['book_lookup_attempts'] = 0;
            $columns['book_lookup_retry_at'] = null;
        }
        if (! in_array($root, [Category::GAME_ROOT, Category::PC_ROOT], true)) {
            if ($release->consoleinfo_id !== null) {
                $columns['consoleinfo_id'] = null;
            }
            if ((int) ($release->gamesinfo_id ?? 0) !== 0) {
                $columns['gamesinfo_id'] = 0;
            }
        }

        if ($titleChanged || $categoryChanged) {
            $columns = [...$columns, ...TvProcessingCandidateQuery::resetColumns()];
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function countRepair(array &$counts, bool $renamed, bool $recategorized): void
    {
        if ($renamed) {
            $counts['renamed']++;
        } elseif ($recategorized) {
            $counts['recategorized']++;
        } else {
            $counts['links']++;
        }
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function clearedLinks(array $columns): string
    {
        $cleared = array_keys(array_intersect_key($columns, array_flip(['imdbid', 'movieinfo_id', 'musicinfo_id', 'bookinfo_id', 'consoleinfo_id', 'gamesinfo_id'])));

        return $cleared === [] ? '' : ' [clears '.implode(', ', $cleared).']';
    }

    private function parse(NzbSplitUnwrapper $unwrapper, Release $release): ?NzbSplitSubject
    {
        return $unwrapper->parse((string) $release->name) ?? $unwrapper->parse((string) $release->searchname);
    }

    private function contentFileName(int $releaseId): ?string
    {
        $name = DB::table('release_files')->where('releases_id', $releaseId)->orderBy('name')->value('name');

        return is_string($name) ? $name : null;
    }

    private function comparisonKey(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower($value));
    }

    /**
     * @return Builder<Release>
     */
    private function baseQuery(): Builder
    {
        $query = Release::query()
            ->select(self::COLUMNS)
            ->where(function (Builder $builder): void {
                $builder->where('name', 'like', '%NZBSPLIT%')
                    ->orWhere('searchname', 'like', '%NZBSPLIT%');
            });

        $ids = array_filter(array_map('intval', (array) $this->option('id')));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    /**
     * @param  Builder<Release>  $query
     * @param  callable(Collection<int, Release>): void  $callback
     */
    private function eachRelease(Builder $query, callable $callback): void
    {
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $seen = 0;

        $query->chunkById(self::CHUNK_SIZE, function (Collection $releases) use ($callback, $limit, &$seen): bool {
            if ($limit !== null) {
                $releases = $releases->take($limit - $seen);
            }

            $callback($releases);
            $seen += $releases->count();

            return $limit === null || $seen < $limit;
        });
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1_073_741_824
            ? sprintf('%.2f GB', $bytes / 1_073_741_824)
            : sprintf('%.1f MB', $bytes / 1_048_576);
    }
}
