<?php

namespace App\Console\Commands;

use Blacklight\NNTP;
use Blacklight\processing\PostProcess;
use Illuminate\Console\Command;

class UpdatePostProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:postprocess
                            {type : Type (all, nfo, movies, tv, music, console, games, book, anime, xxx, additional, amazon)}
                            {echo? : Echo output (true/false, default: true)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post-process releases by type';

    /**
     * Valid types and whether they require NNTP.
     */
    private array $validTypes = [
        'additional' => false,
        'all' => true,
        'amazon' => false, // Alias for book, music, console, games, xxx
        'anime' => false,
        'book' => false,
        'console' => false,
        'games' => false,
        'movies' => false,
        'music' => false,
        'nfo' => true,
        'tv' => false,
        'xxx' => false,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $echoArg = $this->argument('echo');
        $echo = ($echoArg === null || $echoArg === 'true');

        if (! array_key_exists($type, $this->validTypes)) {
            $this->error("Invalid type: {$type}");
            $this->showHelp();

            return self::FAILURE;
        }

        try {
            $nntp = $this->validTypes[$type] ? $this->getNntp() : null;
            $postProcess = new PostProcess;

            match ($type) {
                'all' => $postProcess->processAll($nntp),
                'amazon' => $this->processAmazon($postProcess),
                'nfo' => $postProcess->processNfos($nntp),
                'movies' => $postProcess->processMovies(),
                'music' => $postProcess->processMusic(),
                'console' => $postProcess->processConsoles(),
                'games' => $postProcess->processGames(),
                'book' => $postProcess->processBooks(),
                'anime' => $postProcess->processAnime(),
                'tv' => $postProcess->processTv(),
                'xxx' => $postProcess->processXXX(),
                'additional' => $postProcess->processAdditional(),
                default => throw new \Exception("Unhandled type: {$type}"),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Show help text.
     */
    private function showHelp(): void
    {
        $this->line('');
        $this->line('Available types:');
        $this->line('  all         - Does all the types of post processing');
        $this->line('  nfo         - Processes NFO files');
        $this->line('  movies      - Processes movies');
        $this->line('  music       - Processes music');
        $this->line('  console     - Processes console games');
        $this->line('  games       - Processes games');
        $this->line('  book        - Processes books');
        $this->line('  anime       - Processes anime');
        $this->line('  tv          - Processes tv');
        $this->line('  xxx         - Processes xxx');
        $this->line('  additional  - Processes previews/mediainfo/etc...');
        $this->line('  amazon      - Processes books, music, console, games, and xxx');
    }

    /**
     * Process amazon types (books, music, console, games, xxx).
     */
    private function processAmazon(PostProcess $postProcess): void
    {
        $postProcess->processBooks();
        $postProcess->processMusic();
        $postProcess->processConsoles();
        $postProcess->processGames();
        $postProcess->processXXX();
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP;

        if ((config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect()) !== true) {
            throw new \Exception('Unable to connect to usenet.');
        }

        return $nntp;
    }
}
