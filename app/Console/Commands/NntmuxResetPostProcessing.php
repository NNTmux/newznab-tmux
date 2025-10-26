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
    protected $signature = 'nntmux:resetpp {--c|category=* : Reset all, multiple or single category (music, console, movie, game, tv, adult, book, misc). Supports comma-separated and repeated options}';

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

        // Allow resetting categories only if environment is local and category is 'misc'
        if (app()->environment() !== 'local' && ((isset($this->option('category')['0']) && $this->option('category')[0] !== 'misc') || ! isset($this->option('category')['0']))) {
            $this->error('This command can only be run in local environment');

            return;
        }

        $raw = (array) $this->option('category');
        if (empty($raw)) {
            $qry = Release::query()->select(['id'])->get();
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
            $normalized = $this->normalizeCategories($raw);

            // Validate
            $invalid = $this->invalidCategories($normalized);
            if (! empty($invalid)) {
                $this->error('Unknown category option(s): '.implode(', ', $invalid));
                $this->line('Allowed: '.implode(', ', self::$allowedCategories).' (or omit --category to reset all).');

                return;
            }

            // If user explicitly passed 'all', treat as full reset
            if (in_array('all', $normalized, true) || empty($normalized)) {
                $this->call('nntmux:resetpp'); // fall back to full reset

                return;
            }

            foreach ($normalized as $adjusted) {
                // skip 'all' since handled above
                if ($adjusted === 'all') {
                    continue;
                }
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

    /**
     * Normalize raw category options into a unique, lowercased list.
     * Handles comma-separated values, repeated options, casing, simple plurals,
     * and values provided as key=value (e.g. category=tv or the single-dash typo -category=tv).
     */
    private function normalizeCategories(array $raw): array
    {
        $normalized = collect($raw)
            ->flatMap(function ($opt) {
                $opt = is_array($opt) ? implode(',', $opt) : (string) $opt;

                return preg_split('/[\s,]+/', $opt, -1, PREG_SPLIT_NO_EMPTY);
            })
            ->map(function ($opt) {
                $opt = trim((string) $opt);
                // If the token contains '=', take the substring after the last '='
                if (str_contains($opt, '=')) {
                    $parts = explode('=', $opt);
                    $opt = end($parts);
                }
                $opt = strtolower(trim($opt));
                // normalize common plurals
                $singular = rtrim($opt, 's');
                if (in_array($singular, self::$allowedCategories, true)) {
                    return $singular;
                }

                return $opt;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * Return invalid categories from a normalized list.
     * Keeps 'all' as a special allowed token.
     */
    private function invalidCategories(array $normalized): array
    {
        return collect($normalized)
            ->reject(function ($opt) {
                return in_array($opt, self::$allowedCategories, true) || $opt === 'all';
            })
            ->values()
            ->all();
    }

    private function resetConsole(): void
    {
        $qry = Release::query()->whereNotNull('consoleinfo_id')->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])->get();
        $total = $qry->count();
        if ($total > 0) {
            $bar = $this->output->createProgressBar($total);
            $bar->setOverwrite(true); // Terminal needs to support ANSI Encoding for this?
            $bar->start();
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'consoleinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($total).' consoleinfo_id\'s reset.');
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
            $this->info(number_format($total).' movieinfo_id\'s reset.');
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
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'gamesinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($total).' gamesinfo_id\'s reset.');
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
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'bookinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($total).' bookinfo_id\'s reset.');
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
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'musicinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($total).' musicinfo_id\'s reset.');
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
            foreach ($qry as $releases) {
                Release::query()->where('id', $releases->id)->update(
                    [
                        'xxxinfo_id' => null,
                    ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->info(number_format($total).' xxxinfo_id\'s reset.');
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
            $this->info(number_format($total).' video_id\'s reset.');
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
            $this->info(number_format($total).' misc releases reset.');
        } else {
            $this->info('No releases to reset');
        }
    }
}
