<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use App\Models\Release;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\NzbSplitUnwrapper;
use Illuminate\Console\Command;

class FixNzbSplitNames extends Command
{
    protected $signature = 'releases:fix-nzbsplit
                            {--dry-run : Preview changes without updating the database}';

    protected $description = 'Fix NZBSPLIT-wrapped release names and recategorize the affected releases.';

    public function handle(CategorizationService $categorizationService, NzbSplitUnwrapper $nzbSplitUnwrapper): int
    {
        $query = Release::query()
            ->select(['id', 'name', 'searchname', 'fromname', 'groups_id', 'categories_id'])
            ->where(function ($builder): void {
                $builder
                    ->where('name', 'like', '%__NZBSPLIT__%')
                    ->orWhere('searchname', 'like', '%__NZBSPLIT__%');
            })
            ->orderBy('id');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No NZBSPLIT-wrapped releases found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(250, function ($releases) use ($categorizationService, $nzbSplitUnwrapper, $dryRun, &$updated, $bar): void {
            foreach ($releases as $release) {
                $bar->advance();

                $newTitle = $nzbSplitUnwrapper->unwrap((string) $release->searchname)
                    ?? $nzbSplitUnwrapper->unwrap((string) $release->name);

                if ($newTitle === null) {
                    continue;
                }

                $category = $categorizationService->determineCategory(
                    $release->groups_id,
                    $newTitle,
                    (string) ($release->fromname ?? '')
                );

                $newCategoryId = (int) ($category['categories_id'] ?? $release->categories_id);

                if ($dryRun) {
                    $this->newLine();
                    $this->line(sprintf(
                        '[dry-run] #%d %s -> %s (%d -> %d)',
                        $release->id,
                        (string) $release->searchname,
                        $newTitle,
                        (int) $release->categories_id,
                        $newCategoryId
                    ));

                    continue;
                }

                Release::query()
                    ->where('id', $release->id)
                    ->update([
                        'name' => $newTitle,
                        'searchname' => $newTitle,
                        'categories_id' => $newCategoryId,
                        'isrenamed' => 1,
                        'iscategorized' => 1,
                        'videos_id' => 0,
                        'tv_episodes_id' => 0,
                        'imdbid' => null,
                        'musicinfo_id' => null,
                        'consoleinfo_id' => null,
                        'gamesinfo_id' => 0,
                        'bookinfo_id' => 0,
                        'anidbid' => null,
                    ]);

                Search::updateRelease((int) $release->id);
                $updated++;
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. %d NZBSPLIT-wrapped releases inspected.', $count));

            return self::SUCCESS;
        }

        $this->info(sprintf('Updated %d NZBSPLIT-wrapped releases.', $updated));

        return self::SUCCESS;
    }
}
