<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Models\User;
use App\Services\Categorization\CategorizationService;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecategorizeXxxReleases extends Command
{
    /**
     * The XXX categories releases are sorted into.
     */
    private const array TARGET_CATEGORIES = [
        Category::XXX_SD,
        Category::XXX_HD,
        Category::XXX_UHD,
        Category::XXX_VR,
        Category::XXX_ONLYFANS,
        Category::XXX_JAV,
        Category::XXX_PACK,
        Category::XXX_IMAGESET,
        Category::XXX_DVD,
        Category::XXX_OTHER,
    ];

    protected $signature = 'releases:recategorize-xxx
                            {--apply : Save the new categories and remove the retired XXX categories (otherwise preview only)}
                            {--limit= : Stop after inspecting this many releases}
                            {--details : List every release that would change}';

    protected $description = 'Re-sort all XXX releases into the current XXX categories and remove the retired ones';

    public function handle(CategorizationService $categorizationService): int
    {
        $categories = Category::query()
            ->where('root_categories_id', Category::XXX_ROOT)
            ->pluck('title', 'id');

        $missing = array_diff(self::TARGET_CATEGORIES, $categories->keys()->all());
        if ($missing !== []) {
            $this->error(sprintf('XXX categories missing: %s. Run php artisan migrate first. No releases were changed.', implode(', ', $missing)));

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $details = (bool) $this->option('details');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $inspected = $changes = $updated = $refreshRequested = $skipped = $failed = 0;
        /** @var array<string, int> $moves */
        $moves = [];

        if (! $apply) {
            $this->info('Preview only. Run with --apply to save these category changes.');
        }

        Release::query()
            ->select(['id', 'searchname', 'categories_id', 'groups_id', 'fromname'])
            ->whereBetween('categories_id', [Category::XXX_ROOT, Category::XXX_OTHER])
            ->chunkById(500, function (Collection $releases) use (
                $categorizationService, $categories, $apply, $details, $limit,
                &$inspected, &$changes, &$updated, &$refreshRequested, &$skipped, &$failed, &$moves
            ): bool {
                foreach ($releases as $release) {
                    if ($limit !== null && $inspected >= $limit) {
                        return false;
                    }

                    $inspected++;
                    $current = (int) $release->categories_id;

                    try {
                        $result = $categorizationService->determineCategory(
                            $release->groups_id,
                            $release->searchname,
                            $release->fromname ?? '',
                        );
                        $target = $this->targetCategory($current, (int) $result['categories_id']);

                        if ($target === $current) {
                            continue;
                        }

                        $changes++;
                        $move = $this->categoryLabel($categories->get($current), $current).' -> '.$this->categoryLabel($categories->get($target), $target);
                        $moves[$move] = ($moves[$move] ?? 0) + 1;

                        if ($details) {
                            $this->line(sprintf('#%d %s: %s', $release->id, $release->searchname, $move));
                        }

                        if (! $apply) {
                            continue;
                        }

                        if (! $this->updateCategory($release, $target)) {
                            $skipped++;
                            $this->warn(sprintf('#%d changed during re-sort; skipped.', $release->id));

                            continue;
                        }

                        $updated++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error(sprintf('#%d category change failed: %s', $release->id, $exception->getMessage()));

                        continue;
                    }

                    try {
                        Search::updateRelease((int) $release->id);
                        $refreshRequested++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error(sprintf(
                            '#%d category saved, but search refresh failed: %s. Reindex this release before considering the re-sort complete.',
                            $release->id, $exception->getMessage(),
                        ));
                    }
                }

                return true;
            });

        if ($updated > 0) {
            ReleaseBrowseService::bumpCacheVersion();
        }

        if ($moves !== []) {
            arsort($moves);
            $this->table(['Change', 'Releases'], array_map(
                static fn (string $move, int $count): array => [$move, $count],
                array_keys($moves),
                array_values($moves),
            ));
        }

        $this->info(sprintf('Inspected: %d; Changes: %d; Skipped: %d; Failed: %d.', $inspected, $changes, $skipped, $failed));

        if (! $apply) {
            $this->info(sprintf('Would update: %d. No releases were changed.', $changes));

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info(sprintf('Database categories updated: %d; Search refreshes requested: %d.', $updated, $refreshRequested));
        $this->removeRetiredCategories($categories->all());

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pick the category a release should move to. Releases never leave XXX, so a non-XXX result keeps the
     * current category, or the replacement category when the current one is retired.
     */
    private function targetCategory(int $current, int $result): int
    {
        if (in_array($result, self::TARGET_CATEGORIES, true)) {
            return $result;
        }

        return Category::XXX_RETIRED_FALLBACKS[$current] ?? $current;
    }

    private function categoryLabel(?string $title, int $id): string
    {
        return sprintf('XXX > %s (%d)', $title ?? 'Unknown', $id);
    }

    private function updateCategory(Release $release, int $target): bool
    {
        return DB::transaction(function () use ($release, $target): bool {
            $current = Release::query()->lockForUpdate()->find($release->id, ['searchname', 'categories_id']);

            if ($current === null || $current->searchname !== $release->searchname ||
                $current->categories_id !== $release->categories_id) {
                return false;
            }

            return Release::query()->whereKey($release->id)->update(['categories_id' => $target]) === 1;
        });
    }

    /**
     * Delete the retired XXX categories once no release uses them. User exclusions on a retired category are
     * dropped rather than moved, so hiding an old category never hides its broader replacement. Category
     * regexes move to the replacement so admin rules keep working.
     *
     * @param  array<int, string>  $categories
     */
    private function removeRetiredCategories(array $categories): void
    {
        $retiredIds = array_values(array_intersect(array_keys(Category::XXX_RETIRED_FALLBACKS), array_keys($categories)));

        if ($retiredIds === []) {
            return;
        }

        $remaining = Release::query()->whereIn('categories_id', $retiredIds)->count();
        if ($remaining > 0) {
            $this->warn(sprintf('Retired XXX categories still hold %d releases, so they were kept. Run again with --apply and without --limit to finish.', $remaining));

            return;
        }

        $droppedExclusions = $movedRegexes = 0;
        $affectedUsers = [];

        DB::transaction(function () use ($retiredIds, &$droppedExclusions, &$movedRegexes, &$affectedUsers): void {
            $affectedUsers = DB::table('user_excluded_categories')->whereIn('categories_id', $retiredIds)->pluck('users_id')->all();
            $droppedExclusions = DB::table('user_excluded_categories')->whereIn('categories_id', $retiredIds)->delete();

            foreach ($retiredIds as $retiredId) {
                $movedRegexes += DB::table('category_regexes')
                    ->where('categories_id', $retiredId)
                    ->update(['categories_id' => Category::XXX_RETIRED_FALLBACKS[$retiredId]]);
            }

            Category::query()->whereIn('id', $retiredIds)->delete();
        });

        foreach (array_unique($affectedUsers) as $userId) {
            Cache::forget(User::categoryExclusionCacheKey((int) $userId));
        }

        $this->info(sprintf(
            'Removed retired XXX categories: %s. User exclusions dropped: %d; category regexes moved: %d.',
            implode(', ', array_map(fn (int $id): string => $this->categoryLabel($categories[$id], $id), $retiredIds)),
            $droppedExclusions,
            $movedRegexes,
        ));
    }
}
