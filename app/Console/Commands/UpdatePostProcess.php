<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PostProcessService;
use Blacklight\NNTP;
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
     *
     * @var array<string, bool>
     */
    private const array VALID_TYPES = [
        'additional' => false,
        'all' => true,
        'amazon' => false,
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

    public function __construct(
        private readonly PostProcessService $postProcessService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');

        if (!array_key_exists($type, self::VALID_TYPES)) {
            $this->error("Invalid type: {$type}");
            $this->showHelp();

            return self::FAILURE;
        }

        try {
            $nntp = self::VALID_TYPES[$type] ? $this->getNntp() : null;

            match ($type) {
                'all' => $this->postProcessService->processAll($nntp),
                'amazon' => $this->processAmazon(),
                'nfo' => $this->postProcessService->processNfos($nntp),
                'movies' => $this->postProcessService->processMovies(),
                'music' => $this->postProcessService->processMusic(),
                'console' => $this->postProcessService->processConsoles(),
                'games' => $this->postProcessService->processGames(),
                'book' => $this->postProcessService->processBooks(),
                'anime' => $this->postProcessService->processAnime(),
                'tv' => $this->postProcessService->processTv(),
                'xxx' => $this->postProcessService->processXXX(),
                'additional' => $this->postProcessService->processAdditional(),
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
    private function processAmazon(): void
    {
        $this->postProcessService->processBooks();
        $this->postProcessService->processMusic();
        $this->postProcessService->processConsoles();
        $this->postProcessService->processGames();
        $this->postProcessService->processXXX();
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTP
    {
        $nntp = new NNTP();

        if ((config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect()) !== true) {
            throw new \RuntimeException('Unable to connect to usenet.');
        }

        return $nntp;
    }
}
