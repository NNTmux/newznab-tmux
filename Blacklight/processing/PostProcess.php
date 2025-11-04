<?php

namespace Blacklight\processing;

use App\Services\AnimeProcessor;
use App\Services\BooksProcessor;
use App\Services\ConsolesProcessor;
use App\Services\GamesProcessor;
use App\Services\MoviesProcessor;
use App\Services\MusicProcessor;
use App\Services\NfoProcessor;
use App\Services\Par2Processor;
use App\Services\TvProcessor;
use App\Services\XXXProcessor;
use Blacklight\NameFixer;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\processing\post\ProcessAdditional;
use dariusiii\rarinfo\Par2Info;

class PostProcess
{
    protected NameFixer $nameFixer;

    protected Par2Info $_par2Info;

    /**
     * Use alternate NNTP provider when download fails?
     */
    private bool $alternateNNTP;

    /**
     * Add par2 info to rar list?
     */
    private bool $addpar2;

    /**
     * Should we echo to CLI?
     */
    private bool $echooutput;

    private Nfo $Nfo;

    private Par2Processor $par2Processor;

    private TvProcessor $tvProcessor;

    private NfoProcessor $nfoProcessor;

    private MoviesProcessor $moviesProcessor;

    private MusicProcessor $musicProcessor;

    private BooksProcessor $booksProcessor;

    private ConsolesProcessor $consolesProcessor;

    private GamesProcessor $gamesProcessor;

    private AnimeProcessor $animeProcessor;

    private XXXProcessor $xxxProcessor;

    public function __construct()
    {
        // Various.
        $this->echooutput = config('nntmux.echocli');

        // Class instances.
        $this->_par2Info = new Par2Info;
        $this->nameFixer = new NameFixer;
        $this->Nfo = new Nfo;

        // Site settings.
        $this->addpar2 = config('nntmux_settings.add_par2');
        $this->alternateNNTP = config('nntmux_nntp.use_alternate_nntp_server');

        // Services.
        $this->par2Processor = new Par2Processor($this->nameFixer, $this->_par2Info, $this->addpar2, $this->alternateNNTP);
        $this->tvProcessor = new TvProcessor($this->echooutput);
        $this->nfoProcessor = new NfoProcessor($this->Nfo, $this->echooutput);

        $this->moviesProcessor = new MoviesProcessor($this->echooutput);
        $this->musicProcessor = new MusicProcessor($this->echooutput);
        $this->booksProcessor = new BooksProcessor($this->echooutput);
        $this->consolesProcessor = new ConsolesProcessor($this->echooutput);
        $this->gamesProcessor = new GamesProcessor($this->echooutput);
        $this->animeProcessor = new AnimeProcessor($this->echooutput);
        $this->xxxProcessor = new XXXProcessor($this->echooutput);
    }

    /**
     * Go through every type of post proc.
     *
     *
     * @throws \Exception
     */
    public function processAll($nntp): void
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
     * Lookup anidb if enabled.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     *
     * @throws \Exception
     */
    public function processAnime(string $groupID = '', string $guidChar = ''): void
    {
        $this->animeProcessor->process($groupID, $guidChar);
    }

    /**
     * Process books using amazon.com.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     *
     * @throws \Exception
     */
    public function processBooks(string $groupID = '', string $guidChar = ''): void
    {
        $this->booksProcessor->process($groupID, $guidChar);
    }

    /**
     * @throws \Exception
     */
    public function processConsoles(): void
    {
        $this->consolesProcessor->process();
    }

    /**
     * @throws \Exception
     */
    public function processGames(): void
    {
        $this->gamesProcessor->process();
    }

    /**
     * Lookup imdb if enabled.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     * @param  int|string|null  $processMovies  (Optional) 0 Don't process, 1 process all releases,
     *                                          2 process renamed releases only, '' check site setting
     *
     * @throws \Exception
     */
    public function processMovies(string $groupID = '', string $guidChar = '', int|string|null $processMovies = ''): void
    {
        $this->moviesProcessor->process($groupID, $guidChar, $processMovies);
    }

    /**
     * @throws \Exception
     */
    public function processMusic(): void
    {
        $this->musicProcessor->process();
    }

    /**
     * Process nfo files.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     *
     * @throws \Exception
     */
    public function processNfos(NNTP $nntp, string $groupID = '', string $guidChar = ''): void
    {
        $this->nfoProcessor->process($nntp, $groupID, $guidChar);
    }

    /**
     * Process all TV related releases which will assign their series/episode/rage data.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     * @param  int|string|null  $processTV  (Optional) 0 Don't process, 1 process all releases,
     *                                      2 process renamed releases only, '' check site setting
     * @param  string  $mode  (Optional) Processing mode: 'pipeline' (default) or 'parallel'
     *
     * @throws \Exception
     */
    public function processTv(string $groupID = '', string $guidChar = '', int|string|null $processTV = '', string $mode = 'pipeline'): void
    {
        // If no GUID character specified, use parallel-pipeline processing via Forking
        if ($guidChar === '') {
            $forking = new \Blacklight\libraries\Forking;
            $options = [];

            // Convert processTV setting to renamed-only flag
            $processTV = (is_numeric($processTV) ? $processTV : \App\Models\Settings::settingValue('lookuptv'));
            if ($processTV == 2) {
                $options = [0 => true]; // renamed only
            }

            $forking->processWorkType('postProcess_tv', $options);
        } else {
            // Process single GUID bucket with pipeline
            $this->tvProcessor->process($groupID, $guidChar, $processTV, $mode);
        }
    }

    /**
     * Lookup xxx if enabled.
     *
     * @throws \Exception
     */
    public function processXXX(): void
    {
        $this->xxxProcessor->process();
    }

    /**
     * Check for passworded releases, RAR/ZIP contents and Sample/Media info.
     *
     * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
     *
     * @param  int|string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First char of release GUID, can be used to select work.
     *
     * @throws \Exception
     */
    public function processAdditional(int|string $groupID = '', string $guidChar = ''): void
    {
        (new ProcessAdditional)->start($groupID, $guidChar);
    }

    /**
     * Attempt to get a better name from a par2 file and categorize the release.
     *
     * @note Called from NZBContents.php
     *
     * @param  string  $messageID  MessageID from NZB file.
     * @param  int  $relID  ID of the release.
     * @param  int  $groupID  Group ID of the release.
     * @param  NNTP  $nntp  Class NNTP
     * @param  int  $show  Only show result or apply iy.
     *
     * @throws \Exception
     */
    public function parsePAR2(string $messageID, int $relID, int $groupID, NNTP $nntp, int $show): bool
    {
        return $this->par2Processor->parseFromMessage($messageID, $relID, $groupID, $nntp, $show);
    }
}
