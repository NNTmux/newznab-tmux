<?php

namespace Blacklight\processing;

use App\Models\Category;
use App\Models\Release;
use App\Models\ReleaseFile;
use App\Models\Settings;
use App\Models\UsenetGroup;
use Blacklight\Books;
use Blacklight\Console;
use Blacklight\Games;
use Blacklight\Movie;
use Blacklight\Music;
use Blacklight\NameFixer;
use Blacklight\Nfo;
use Blacklight\NNTP;
use Blacklight\processing\post\AniDB;
use Blacklight\processing\post\ProcessAdditional;
use Blacklight\processing\tv\TMDB;
use Blacklight\processing\tv\TraktTv;
use Blacklight\processing\tv\TVDB;
use Blacklight\processing\tv\TVMaze;
use Blacklight\XXX;
use dariusiii\rarinfo\Par2Info;
use Illuminate\Support\Carbon;

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

    /**
     * @param  array  $options  Class instances / Echo to cli.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Echo' => false,
            'Settings' => null,
        ];
        $options += $defaults;

        $this->echooutput = ($options['Echo'] && config('nntmux.echocli'));

        // Class instances.
        $this->_par2Info = new Par2Info();
        $this->nameFixer = new NameFixer();
        $this->Nfo = new Nfo();

        // Site settings.
        $this->addpar2 = (int) Settings::settingValue('..addpar2') !== 0;
        $this->alternateNNTP = (int) Settings::settingValue('..alternate_nntp') === 1;
    }

    /**
     * Go through every type of post proc.
     *
     *
     * @throws \Exception
     */
    public function processAll($nntp): void
    {
        $this->processAdditional($nntp);
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
     *
     * @throws \Exception
     */
    public function processAnime(): void
    {
        if ((int) Settings::settingValue('..lookupanidb') !== 0) {
            (new AniDB(['Echo' => $this->echooutput]))->processAnimeReleases();
        }
    }

    /**
     * Process books using amazon.com.
     *
     *
     * @throws \Exception
     */
    public function processBooks(): void
    {
        if ((int) Settings::settingValue('..lookupbooks') !== 0) {
            (new Books(['Echo' => $this->echooutput]))->processBookReleases();
        }
    }

    /**
     * @throws \Exception
     */
    public function processConsoles(): void
    {
        if ((int) Settings::settingValue('..lookupgames') !== 0) {
            (new Console(['Echo' => $this->echooutput]))->processConsoleReleases();
        }
    }

    /**
     * @throws \Exception
     */
    public function processGames(): void
    {
        if ((int) Settings::settingValue('..lookupgames') !== 0) {
            (new Games(['Echo' => $this->echooutput]))->processGamesReleases();
        }
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
        $processMovies = (is_numeric($processMovies) ? $processMovies : Settings::settingValue('..lookupimdb'));
        if ($processMovies > 0) {
            (new Movie(['Echo' => $this->echooutput]))->processMovieReleases($groupID, $guidChar, $processMovies);
        }
    }

    /**
     * @throws \Exception
     */
    public function processMusic(): void
    {
        if ((int) Settings::settingValue('..lookupmusic') !== 0) {
            (new Music(['Echo' => $this->echooutput]))->processMusicReleases();
        }
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
        if ((int) Settings::settingValue('..lookupnfo') === 1) {
            $this->Nfo->processNfoFiles($nntp, $groupID, $guidChar, (int) Settings::settingValue('..lookupimdb'), (int) Settings::settingValue('..lookuptvrage'));
        }
    }

    /**
     * Process all TV related releases which will assign their series/episode/rage data.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     * @param  int|string|null  $processTV  (Optional) 0 Don't process, 1 process all releases,
     *                                      2 process renamed releases only, '' check site setting
     *
     * @throws \Exception
     */
    public function processTv(string $groupID = '', string $guidChar = '', int|string|null $processTV = ''): void
    {
        $processTV = (is_numeric($processTV) ? $processTV : Settings::settingValue('..lookuptvrage'));
        if ($processTV > 0) {
            (new TVDB(['Echo' => $this->echooutput]))->processSite($groupID, $guidChar, $processTV);
            (new TVMaze(['Echo' => $this->echooutput]))->processSite($groupID, $guidChar, $processTV);
            (new TMDB(['Echo' => $this->echooutput]))->processSite($groupID, $guidChar, $processTV);
            (new TraktTv(['Echo' => $this->echooutput]))->processSite($groupID, $guidChar, $processTV);
        }
    }

    /**
     * Lookup xxx if enabled.
     *
     * @throws \Exception
     */
    public function processXXX(): void
    {
        if ((int) Settings::settingValue('..lookupxxx') === 1) {
            (new XXX(['Echo' => $this->echooutput]))->processXXXReleases();
        }
    }

    /**
     * Check for passworded releases, RAR/ZIP contents and Sample/Media info.
     *
     * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
     *
     * @param  NNTP  $nntp  Class NNTP
     * @param  int|string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First char of release GUID, can be used to select work.
     *
     * @throws \Exception
     */
    public function processAdditional(NNTP $nntp, int|string $groupID = '', string $guidChar = ''): void
    {
        (new ProcessAdditional(['Echo' => $this->echooutput, 'NNTP' => $nntp, 'NameFixer' => $this->nameFixer, 'Nfo' => $this->Nfo]))->start($groupID, $guidChar);
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
        if ($messageID === '') {
            return false;
        }

        $query = Release::query()
            ->where(['isrenamed' => 0, 'id' => $relID])
            ->select(['id', 'groups_id', 'categories_id', 'name', 'searchname', 'postdate', 'id as releases_id'])
            ->first();

        if ($query === null) {
            return false;
        }

        // Only get a new name if the category is OTHER.
        $foundName = true;
        if (\in_array((int) $query['categories_id'], Category::OTHERS_GROUP, false)) {
            $foundName = false;
        }

        // Get the PAR2 file.
        $par2 = $nntp->getMessages(UsenetGroup::getNameByID($groupID), $messageID, $this->alternateNNTP);
        if ($nntp->isError($par2)) {
            return false;
        }

        // Put the PAR2 into Par2Info, check if there's an error.
        $this->_par2Info->setData($par2);
        if ($this->_par2Info->error) {
            return false;
        }

        // Get the file list from Par2Info.
        $files = $this->_par2Info->getFileList();
        if ($files !== false && \count($files) > 0) {
            $filesAdded = 0;

            // Loop through the files.
            foreach ($files as $file) {
                if (! isset($file['name'])) {
                    continue;
                }

                // If we found a name and added 20 files, stop.
                if ($foundName === true && $filesAdded > 20) {
                    break;
                }

                if ($this->addpar2) {
                    // Add to release files.
                    if ($filesAdded < 21 && ReleaseFile::query()->where(['releases_id' => $relID, 'name' => $file['name']])->first() === null) {
                        // Try to add the files to the DB.
                        if (ReleaseFile::addReleaseFiles($relID, $file['name'], $file['size'], $query['postdate'] !== null ? Carbon::createFromFormat('Y-m-d H:i:s', $query['postdate']) : now(), 0, $file['hash_16K'])) {
                            $filesAdded++;
                        }
                    }
                } else {
                    $filesAdded++;
                }

                // Try to get a new name.
                if ($foundName === false) {
                    $query['textstring'] = $file['name'];
                    if ($this->nameFixer->checkName($query, 1, 'PAR2, ', 1, $show)) {
                        $foundName = true;
                    }
                }
            }

            // If we found some files.
            if ($filesAdded > 0) {
                // Update the file count with the new file count + old file count.
                Release::whereId($relID)->increment('rarinnerfilecount', $filesAdded);
            }
            if ($foundName === true) {
                return true;
            }
        }

        return false;
    }
}
