<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Settings;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\NfoService;
use App\Services\PostProcessService;
use App\Services\NNTP\NNTPService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PostProcessGuid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postprocess:guid
                            {type : Type: additional, nfo, movie, tv, anime, or books}
                            {guid : First character of release guid (a-f, 0-9)}
                            {renamed? : For movie/tv: process renamed only (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post process releases by GUID character';

    public function __construct(
        private readonly PostProcessService $postProcessService,
        private readonly AdditionalProcessingOrchestrator $additionalProcessor
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $guid = $this->argument('guid');
        $renamed = $this->argument('renamed') ?? '';

        if (!$this->isValidChar($guid)) {
            $this->error('GUID character must be a-f or 0-9.');

            return self::FAILURE;
        }

        try {
            match ($type) {
                'additional' => $this->processAdditional($guid),
                'nfo' => $this->processNfo($guid),
                'movie' => $this->postProcessService->processMovies('', $guid, $renamed),
                'tv' => $this->postProcessService->processTv('', $guid, $renamed),
                'anime' => $this->postProcessService->processAnime('', $guid),
                'books' => $this->postProcessService->processBooks('', $guid),
                default => throw new \InvalidArgumentException(
                    'Invalid type. Must be: additional, nfo, movie, tv, anime, or books.'
                ),
            };

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error($e->getTraceAsString());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Process additional data for releases.
     */
    private function processAdditional(string $guid): void
    {
        $this->additionalProcessor->start('', $guid);
    }

    /**
     * Process NFO files for releases.
     */
    private function processNfo(string $guid): void
    {
        $nntp = $this->getNntp();
        (new NfoService())->processNfoFiles(
            $nntp,
            '',
            $guid,
            (bool) Settings::settingValue('lookupimdb'),
            (bool) Settings::settingValue('lookuptv')
        );
    }

    /**
     * Check if the character contains a-f or 0-9.
     */
    private function isValidChar(string $char): bool
    {
        return \in_array(
            $char,
            ['a', 'b', 'c', 'd', 'e', 'f', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            true
        );
    }

    /**
     * Get NNTP connection.
     */
    private function getNntp(): NNTPService
    {
        $nntp = new NNTPService();

        $connectResult = config('nntmux_nntp.use_alternate_nntp_server') === true
            ? $nntp->doConnect(false, true)
            : $nntp->doConnect();

        if ($connectResult !== true) {
            $errorMessage = 'Unable to connect to usenet.';
            if (NNTPService::isError($connectResult)) {
                $errorMessage .= ' Error: '.$connectResult->getMessage();
            }
            throw new \RuntimeException($errorMessage);
        }

        return $nntp;
    }
}
