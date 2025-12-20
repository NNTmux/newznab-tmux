<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\NameFixing\NameFixingService;
use Blacklight\Nfo;
use Blacklight\NNTP;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Contracts\Foundation\Application;

/**
 * Orchestrates post-processing of releases.
 *
 * This service coordinates various post-processing operations including:
 * - NFO processing
 * - Movie/TV/Anime lookups
 * - Music/Books/Games/Console processing
 * - XXX content processing
 * - Additional processing (RAR/ZIP contents, samples, etc.)
 */
final class PostProcessService
{
    private readonly bool $echoOutput;
    private readonly bool $alternateNNTP;
    private readonly bool $addPar2;

    private readonly NameFixingService $nameFixingService;
    private readonly Par2Info $par2Info;
    private readonly Nfo $nfo;

    private readonly Par2Processor $par2Processor;
    private readonly TvProcessor $tvProcessor;
    private readonly NfoProcessor $nfoProcessor;
    private readonly MoviesProcessor $moviesProcessor;
    private readonly MusicProcessor $musicProcessor;
    private readonly BooksProcessor $booksProcessor;
    private readonly ConsolesProcessor $consolesProcessor;
    private readonly GamesProcessor $gamesProcessor;
    private readonly AnimeProcessor $animeProcessor;
    private readonly XXXProcessor $xxxProcessor;

    public function __construct(
        ?NameFixingService $nameFixingService = null,
        ?Par2Info $par2Info = null,
        ?Nfo $nfo = null,
        ?Par2Processor $par2Processor = null,
        ?TvProcessor $tvProcessor = null,
        ?NfoProcessor $nfoProcessor = null,
        ?MoviesProcessor $moviesProcessor = null,
        ?MusicProcessor $musicProcessor = null,
        ?BooksProcessor $booksProcessor = null,
        ?ConsolesProcessor $consolesProcessor = null,
        ?GamesProcessor $gamesProcessor = null,
        ?AnimeProcessor $animeProcessor = null,
        ?XXXProcessor $xxxProcessor = null,
    ) {
        $this->echoOutput = (bool) config('nntmux.echocli');
        $this->addPar2 = (bool) config('nntmux_settings.add_par2');
        $this->alternateNNTP = (bool) config('nntmux_nntp.use_alternate_nntp_server');

        // Core dependencies
        $this->nameFixingService = $nameFixingService ?? new NameFixingService();
        $this->par2Info = $par2Info ?? new Par2Info();
        $this->nfo = $nfo ?? new Nfo();

        // Processors
        $this->par2Processor = $par2Processor ?? new Par2Processor(
            $this->nameFixingService,
            $this->par2Info,
            $this->addPar2,
            $this->alternateNNTP
        );
        $this->tvProcessor = $tvProcessor ?? new TvProcessor($this->echoOutput);
        $this->nfoProcessor = $nfoProcessor ?? new NfoProcessor($this->nfo, $this->echoOutput);
        $this->moviesProcessor = $moviesProcessor ?? new MoviesProcessor($this->echoOutput);
        $this->musicProcessor = $musicProcessor ?? new MusicProcessor($this->echoOutput);
        $this->booksProcessor = $booksProcessor ?? new BooksProcessor($this->echoOutput);
        $this->consolesProcessor = $consolesProcessor ?? new ConsolesProcessor($this->echoOutput);
        $this->gamesProcessor = $gamesProcessor ?? new GamesProcessor($this->echoOutput);
        $this->animeProcessor = $animeProcessor ?? new AnimeProcessor($this->echoOutput);
        $this->xxxProcessor = $xxxProcessor ?? new XXXProcessor($this->echoOutput);
    }

    /**
     * Run all post-processing types.
     *
     * @throws \Exception
     */
    public function processAll(NNTP $nntp): void
    {
        $this->processAdditional();
        $this->processNfos($nntp);
        $this->processMovies();
        $this->processMusic();
        $this->processConsoles();
        $this->processGames();
        $this->processAnime();
        $this->processTv();
        $this->processXXX();
        $this->processBooks();
    }

    /**
     * Process anime releases using AniDB.
     *
     * @param string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     *
     * @throws \Exception
     */
    public function processAnime(string $groupID = '', string $guidChar = ''): void
    {
        $this->animeProcessor->process($groupID, $guidChar);
    }

    /**
     * Process book releases using Amazon.
     *
     * @param string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     *
     * @throws \Exception
     */
    public function processBooks(string $groupID = '', string $guidChar = ''): void
    {
        $this->booksProcessor->process($groupID, $guidChar);
    }

    /**
     * Process console game releases.
     *
     * @throws \Exception
     */
    public function processConsoles(): void
    {
        $this->consolesProcessor->process();
    }

    /**
     * Process PC game releases.
     *
     * @throws \Exception
     */
    public function processGames(): void
    {
        $this->gamesProcessor->process();
    }

    /**
     * Process movie releases using IMDB/TMDB.
     *
     * @param string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     * @param int|string|null $processMovies Processing mode (0=skip, 1=all, 2=renamed only, ''=check setting)
     *
     * @throws \Exception
     */
    public function processMovies(
        string $groupID = '',
        string $guidChar = '',
        int|string|null $processMovies = ''
    ): void {
        $this->moviesProcessor->process($groupID, $guidChar, $processMovies);
    }

    /**
     * Process music releases.
     *
     * @throws \Exception
     */
    public function processMusic(): void
    {
        $this->musicProcessor->process();
    }

    /**
     * Process NFO files for releases.
     *
     * @param NNTP $nntp NNTP connection for downloading NFOs
     * @param string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     *
     * @throws \Exception
     */
    public function processNfos(NNTP $nntp, string $groupID = '', string $guidChar = ''): void
    {
        $this->nfoProcessor->process($nntp, $groupID, $guidChar);
    }

    /**
     * Process TV releases.
     *
     * @param string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     * @param int|string|null $processTV Processing mode (0=skip, 1=all, 2=renamed only, ''=check setting)
     * @param string $mode Processing mode ('pipeline' or 'parallel')
     *
     * @throws \Exception
     */
    public function processTv(
        string $groupID = '',
        string $guidChar = '',
        int|string|null $processTV = '',
        string $mode = 'pipeline'
    ): void {
        if ($guidChar === '') {
            $forkingService = new ForkingService();
            $processTV = is_numeric($processTV)
                ? $processTV
                : \App\Models\Settings::settingValue('lookuptv');
            $renamedOnly = ((int) $processTV === 2);

            $forkingService->processTv($renamedOnly);
        } else {
            $this->tvProcessor->process($groupID, $guidChar, $processTV, $mode);
        }
    }

    /**
     * Process XXX releases.
     *
     * @throws \Exception
     */
    public function processXXX(): void
    {
        $this->xxxProcessor->process();
    }

    /**
     * Process additional release data (RAR/ZIP contents, samples, media info).
     *
     * @param int|string $groupID Optional group ID filter
     * @param string $guidChar Optional GUID character filter
     *
     * @throws \Exception
     */
    public function processAdditional(int|string $groupID = '', string $guidChar = ''): void
    {
        app(AdditionalProcessingOrchestrator::class)->start($groupID, $guidChar);
    }

    /**
     * Attempt to get a better name from a PAR2 file and re-categorize.
     *
     * @param string $messageID Message ID from NZB
     * @param int $relID Release ID
     * @param int $groupID Group ID
     * @param NNTP $nntp NNTP connection
     * @param int $show Display mode (0=apply, 1=show only)
     *
     * @throws \Exception
     */
    public function parsePAR2(
        string $messageID,
        int $relID,
        int $groupID,
        NNTP $nntp,
        int $show
    ): bool {
        return $this->par2Processor->parseFromMessage($messageID, $relID, $groupID, $nntp, $show);
    }
}

