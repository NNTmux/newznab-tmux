<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use Blacklight\Categorize;
use Blacklight\NameFixer;
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
    public function handle()
    {
        $countQuery = Release::query();
        if ($this->option('misc')) {
            $countQuery->whereIn('categories_id', Category::OTHERS_GROUP);
        } elseif ($this->option('all')) {
            $countQuery->where('iscategorized', 0);
        } elseif ($this->option('group')) {
            $countQuery->where('groups_id', $this->option('group'));
        } elseif ($this->option('groups')) {
            $countQuery->whereIn('groups_id', explode(',', $this->option('groups')));
        } elseif ($this->option('category')) {
            $countQuery->where('categories_id', $this->option('category'));
        } elseif ($this->option('categories')) {
            $countQuery->whereIn('categories_id', explode(',', $this->option('categories')));
        } elseif($this->option('test')) {
            $countQuery;
        } else {
            $this->error('You must specify at least one option. See: --help');
            exit();
        }

        $count = $countQuery->count();

        $cat = new Categorize();
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

                    NameFixer::echoChangedReleaseName([
                        'new_name' => $result->searchname,
                        'old_name' => $result->searchname,
                        'new_category' => $catId['categories_id'],
                        'old_category' => $result->categories_id,
                        'group' => $result->group->name,
                        'releases_id' => $result->id,
                        'method' => 'Recategorize',
                    ]);
                }
            }
        }
        $bar->finish();
    }
}
