<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Category;
use App\Models\Release;
use App\Services\Categorization\CategorizationService;
use App\Services\Releases\ReleaseBrowseService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecategorizeOnlyFans extends Command
{
    protected $signature = 'releases:recategorize-onlyfans
                            {--apply : Apply the category corrections (otherwise preview only)}';

    protected $description = 'Preview or repair OF. releases in XXX categories that belong in OnlyFans';

    public function handle(CategorizationService $categorizationService): int
    {
        $categories = Category::query()
            ->where('root_categories_id', Category::XXX_ROOT)
            ->pluck('title', 'id');

        if (! $categories->has(Category::XXX_ONLYFANS)) {
            $this->error('XXX > OnlyFans category (6047) is missing. No releases were changed.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $inspected = $eligible = $updated = $refreshRequested = $skipped = $failed = 0;

        if (! $apply) {
            $this->info('Preview only. Run with --apply to save these category corrections.');
        }

        Release::query()
            ->select(['id', 'searchname', 'categories_id', 'groups_id', 'fromname'])
            ->whereIn('categories_id', $categories->keys()->all())
            ->where('categories_id', '!=', Category::XXX_ONLYFANS)
            ->whereRaw('LOWER(searchname) LIKE ?', ['of.%'])
            ->chunkById(250, function (Collection $releases) use (
                $categorizationService, $categories, $apply,
                &$inspected, &$eligible, &$updated, &$refreshRequested, &$skipped, &$failed
            ): void {
                foreach ($releases as $release) {
                    $inspected++;

                    try {
                        $result = $categorizationService->determineCategory(
                            $release->groups_id,
                            $release->searchname,
                            $release->fromname ?? '',
                        );

                        if ((int) $result['categories_id'] !== Category::XXX_ONLYFANS) {
                            $skipped++;

                            continue;
                        }

                        $eligible++;

                        if (! $apply) {
                            $this->line(sprintf(
                                '#%d %s: XXX > %s (%d) -> XXX > %s (%d)',
                                $release->id, $release->searchname,
                                $categories->get($release->categories_id), $release->categories_id,
                                $categories->get(Category::XXX_ONLYFANS), Category::XXX_ONLYFANS,
                            ));

                            continue;
                        }

                        if (! $this->updateCategory($release)) {
                            $skipped++;
                            $this->warn(sprintf('#%d changed during repair; skipped.', $release->id));

                            continue;
                        }

                        $updated++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error(sprintf('#%d category correction failed: %s', $release->id, $exception->getMessage()));

                        continue;
                    }

                    try {
                        Search::updateRelease((int) $release->id);
                        $refreshRequested++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error(sprintf(
                            '#%d category saved, but search refresh failed: %s. Reindex this release before considering its repair complete.',
                            $release->id, $exception->getMessage(),
                        ));
                    }
                }
            });

        if ($updated > 0) {
            ReleaseBrowseService::bumpCacheVersion();
        }

        $this->info(sprintf('Inspected: %d; Eligible: %d; Skipped: %d; Failed: %d.', $inspected, $eligible, $skipped, $failed));
        if ($apply) {
            $this->info(sprintf('Database categories updated: %d; Search refreshes requested: %d.', $updated, $refreshRequested));
        } else {
            $this->info(sprintf('Would update: %d. No releases were changed.', $eligible));
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function updateCategory(Release $release): bool
    {
        return DB::transaction(function () use ($release): bool {
            $current = Release::query()->lockForUpdate()->find($release->id, ['searchname', 'categories_id']);

            if ($current === null || $current->searchname !== $release->searchname ||
                $current->categories_id !== $release->categories_id) {
                return false;
            }

            return Release::query()->whereKey($release->id)->update(['categories_id' => Category::XXX_ONLYFANS]) === 1;
        });
    }
}
