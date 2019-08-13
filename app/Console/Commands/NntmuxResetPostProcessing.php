<?php

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\Category;
use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
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
        'misc',
        'anime',
        'nfo',
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
     * @var ColorCLI
     */
    private $colorCli;

    /**
     * @var ConsoleTools
     */
    private $consoleTools;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->colorCli = new ColorCLI();
        $this->consoleTools = new ConsoleTools();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (empty($this->option('category'))) {
            $qry = Release::query()->select(['id'])->get();
            $affected = 0;
            $total = \count($qry);
            if ($total > 0) {
                $this->colorCli->header('Resetting all postprocessing');
                foreach ($qry as $releases) {
                    Release::query()->where('id', $releases->id)->update(
                        [
                            'consoleinfo_id' => null,
                            'gamesinfo_id' => null,
                            'imdbid' => null,
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
                    $this->consoleTools->overWritePrimary('Resetting Releases:  '.$this->consoleTools->percentString(++$affected, $total));
                }
            } else {
                $this->colorCli->header('No releases to reset');
            }
        } else {
            foreach ($this->option('category') as $option) {
                $adjusted = str_replace('=', '', $option);
                if (\in_array($adjusted, self::$allowedCategories, false)) {
                    $this->info('Resetting postprocessing for '.$adjusted.' category');
                    switch ($adjusted) {
                        case 'console':
                            $this->colorCli->header('Resetting all Console postprocessing');
                            $qry = Release::query()->whereNotNull('consoleinfo_id')->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])->get();
                            $total = $qry->count();
                            $conCount = 0;
                            foreach ($qry as $releases) {
                                Release::query()->where('id', $releases->id)->update(
                                    [
                                        'consoleinfo_id' => null,
                                    ]);
                                $this->consoleTools->overWritePrimary('Resetting Console Releases:  '.$this->consoleTools->percentString(++$conCount, $total));
                            }
                            $this->colorCli->header(number_format($conCount).' consoleinfo_id\'s reset.');
                            break;
                        case 'movie':
                            $this->colorCli->header('Resetting all Movie postprocessing');
                            $qry = Release::query()->whereNotNull('movieinfo_id')->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])->get();
                            $total = $qry->count();
                            $conCount = 0;
                            foreach ($qry as $releases) {
                                Release::query()->where('id', $releases->id)->update(
                                    [
                                        'movieinfo_id' => null,
                                    ]);
                                $this->consoleTools->overWritePrimary('Resetting Movie Releases:  '.$this->consoleTools->percentString(++$conCount, $total));
                            }
                            $this->colorCli->header(number_format($conCount).' movieinfo_id\'s reset.');
                            break;
                        case 'game':
                            $this->colorCli->header('Resetting all PC Games postprocessing');
                            $qry = Release::query()->whereIn('gamesinfo_id', [-2, 0])->where('categories_id', '=', Category::PC_GAMES)->get();
                            $total = $qry->count();
                            $conCount = 0;
                            foreach ($qry as $releases) {
                                Release::query()->where('id', $releases->id)->update(
                                    [
                                        'gamesinfo_id' => null,
                                    ]);
                                $this->consoleTools->overWritePrimary('Resetting PC GAME Releases:  '.$this->consoleTools->percentString(++$conCount, $total));
                            }
                            $this->colorCli->header(number_format($conCount).' gamesinfo_id\'s reset.');
                            break;
                    }
                }
            }
        }
    }
}
