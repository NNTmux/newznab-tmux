<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Console\Command;

class NntmuxResetPostProcessing extends Command
{
    /**
     * @var array
     */
    private static $allowedCategories = [
        'music',
        'console',
        'movie',
        'game',
        'tv',
        'adult',
        'book',
        'misc',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:resetpp {--c|category=* : Reset all, multiple or single category}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all, multiple or single release category postprocessing';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (empty($this->option('category'))) {
            $qry = Release::query()->select(['id'])->get();
            $affected = 0;
            $total = \count($qry);
            if ($total > 0) {
                $bar = $this->output->createProgressBar($total);
                $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
                $bar->start();
                $this->info('Resetting all postprocessing');
                foreach ($qry as $releases) {
                    Release::query()->where('id', $releases->id)->update(
                        [
                            'consoleinfo_id' => null,
                            'gamesinfo_id' => null,
                            'imdbid' => null,
                            'movieinfo_id' => null,
                            'musicinfo_id' => null,
                            'bookinfo_id' => null,
                            'videos_id' => 0,
                            'tv_episodes_id' => 0,
                            'xxxinfo_id' => 0,
                            'passwordstatus' => -1,
                            'haspreview' => -1,
                            'jpgstatus' => 0,
                            'videostatus' => 0,
                            'audiostatus' => 0,
                            'nfostatus' => -1,
                        ]
                    );
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
            } else {
                $this->info('No releases to reset');
            }
        } else {
            foreach ($this->option('category') as $option) {
                $adjusted = str_replace('=', '', $option);
                if (\in_array($adjusted, self::$allowedCategories, false)) {
                    $this->info('Resetting postprocessing for '.$adjusted.' category');
                    switch ($adjusted) {
                        case 'console':
                            $this->resetConsole();
                            break;
                        case 'movie':
                            $this->resetMovies();
                            break;
                        case 'game':
                            $this->resetGames();
                            break;
                        case 'book':
                            $this->resetBooks();
                            break;
                        case 'music':
                            $this->resetMusic();
                            break;
                        case 'adult':
                            $this->resetAdult();
                            break;
                        case 'tv':
                            $this->resetTv();
                            break;
                        case 'misc':
                            $this->resetMisc();
                            break;
                    }
                }
            }
        }
    }

    private function resetConsole(): void
    {
        $qry = Release::query()->whereNotNull('consoleinfo_id')->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])->get();
        $total = $qry->count();
        $bar = $this->output->createProgressBar($total);
        $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
        $bar->start();
        if ($total > 0) {
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'consoleinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' consoleinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetMovies(): void
    {
        $qry = Release::query()->whereNotNull('movieinfo_id')->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'movieinfo_id' => null,
                        'imdbid' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' movieinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetGames(): void
    {
        $qry = Release::query()->whereNotNull('gamesinfo_id')->where('categories_id', '=', Category::PC_GAMES)->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'gamesinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' gamesinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetBooks(): void
    {
        $qry = Release::query()->whereNotNull('bookinfo_id')->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'bookinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' bookinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetMusic(): void
    {
        $qry = Release::query()->whereNotNull('musicinfo_id')->whereBetween('categories_id', [Category::MUSIC_ROOT, Category::MUSIC_OTHER])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'musicinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' musicinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetAdult(): void
    {
        $qry = Release::query()->whereNotNull('xxxinfo_id')->whereBetween('categories_id', [Category::XXX_ROOT, Category::XXX_OTHER])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'xxxinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' xxxinfo_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetTv(): void
    {
        $qry = Release::query()->where('videos_id', '!=', 0)->where('tv_episodes_id', '!=', 0)->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'videos_id' => 0,
                        'tv_episodes_id' => 0,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' video_id\'s reset.');
        } else {
            $this->info('No releases to reset');
        }
    }

    private function resetMisc(): void
    {
        $qry = Release::query()->whereBetween('categories_id', [Category::OTHER_ROOT, Category::OTHER_HASHED])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            $conCount = 0;
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'passwordstatus' => -1,
                        'haspreview' => -1,
                        'jpgstatus' => 0,
                        'videostatus' => 0,
                        'audiostatus' => 0,
                        'nfostatus' => -1,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($conCount).' misc releases reset.');
        } else {
            $this->info('No releases to reset');
        }
    }
}
