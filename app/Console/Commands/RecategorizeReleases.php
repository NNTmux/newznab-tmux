<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use Blacklight\Categorize;
use Illuminate\Console\Command;

class RecategorizeReleases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:recategorize-releases
    {--misc : Re-categorize all releases in misc categories}
    {--all : Re-categorize all releases}
    {--test : Test only, no updates}
    {--group= : Re-categorize all releases in a group}
    {--groups= : Re-categorize all releases in a list of groups}
    {--category= : Re-categorize all releases in a category}
    {--categories= : Re-categorize all releases in a list of categories}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-categorize releases based on their name and group.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $countQuery = Release::query();
        if ($this->option('misc')) {
            $countQuery->whereIn('categories_id', Category::OTHERS_GROUP);
        } elseif ($this->option('all')) {
            if ($this->confirm('This will reset categorization on all releases and re-categorize them all from scratch. Are you sure? (y/n)', 'n')) {
                Release::query()->where('iscategorized', 1)->update([
                    'iscategorized' => 0,
                ]);
                $countQuery->where('iscategorized', 0);
            } else {
                $this->info('Reset script stopped.');
                exit();
            }

        } elseif ($this->option('group')) {
            $countQuery->where('groups_id', $this->option('group'));
        } elseif ($this->option('groups')) {
            $countQuery->whereIn('groups_id', explode(',', $this->option('groups')));
        } elseif ($this->option('category')) {
            $countQuery->where('categories_id', $this->option('category'));
        } elseif ($this->option('categories')) {
            $countQuery->whereIn('categories_id', explode(',', $this->option('categories')));
        } elseif ($this->option('test')) {
            $countQuery->where('iscategorized', 0);
        } else {
            $this->error('You must specify at least one option. See: --help');
            exit();
        }

        $count = $countQuery->count();

        $cat = new Categorize;
        $results = $countQuery->select(['id', 'searchname', 'fromname', 'groups_id', 'categories_id'])->get();
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        foreach ($results as $result) {
            $bar->advance();
            $catId = $cat->determineCategory($result->groups_id, $result->searchname, $result->fromname);
            if ((int) $result->categories_id !== (int) $catId['categories_id']) {
                if ($this->option('test')) {
                    $this->info('Would have changed '.$result->searchname.' from '.$result->categories_id.' to '.$catId['categories_id']);
                } else {
                    Release::query()->where('id', $result->id)->update([
                        'iscategorized' => 1,
                        'videos_id' => 0,
                        'tv_episodes_id' => 0,
                        'imdbid' => null,
                        'musicinfo_id' => null,
                        'consoleinfo_id' => null,
                        'gamesinfo_id' => 0,
                        'bookinfo_id' => 0,
                        'anidbid' => null,
                        'xxxinfo_id' => 0,
                        'categories_id' => $catId['categories_id'],
                    ]);

                    $newCatName = Category::query()->where('id', $catId['categories_id'])->first();

                    $this->line('');
                    $this->output->writeln('<fg=yellow>ID       :</> '.$result->id);
                    $this->output->writeln('<fg=green>Release  :</> '.$result->searchname);
                    $this->output->writeln('<fg=cyan>Group    :</> '.$result->group->name);
                    $oldCategoryTitle = $result->category?->parent ? ($result->category->parent->title . ' -> ' . $result->category->title) : ($result->category?->title ?? 'N/A');
                    $newCategoryTitle = $newCatName?->parent ? ($newCatName->parent->title . ' -> ' . $newCatName->title) : ($newCatName?->title ?? 'N/A');
                    $this->output->writeln('<fg=white>Category :</> ' . $oldCategoryTitle . ' <fg=yellow>→</> <fg=magenta>' . $newCategoryTitle . '</>');
                    $this->line('');
                }
            }
        }
        $bar->finish();
    }
}
