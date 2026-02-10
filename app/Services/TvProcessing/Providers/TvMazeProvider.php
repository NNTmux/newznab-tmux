<?php

namespace App\Services\TvProcessing\Providers;

use App\Services\ReleaseImageService;
use DariusIII\TVMaze\TVMaze as Client;

/**
 * Class TvMazeProvider.
 *
 * Process information retrieved from the TVMaze API.
 */
class TvMazeProvider extends AbstractTvProvider
{
    private const MATCH_PROBABILITY = 75;

    /**
     * Client for TVMaze API.
     */
    public Client $client;

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * TVMaze constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new Client;
    }

    /**
     * Fetch banner from site.
     */
    public function getBanner($videoID, $siteId): bool
    {
        return false;
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     */
    public function processSite($groupID, $guidChar, $process, bool $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVMAZE);

        $tvCount = \count($res);

        if ($tvCount === 0) {

            return;
        }

        if ($res instanceof \Traversable) {
            $this->titleCache = [];
            $processed = 0;
            $matched = 0;
            $skipped = 0;

            foreach ($res as $row) {
                $processed++;
                $siteId = false;
                $this->posterUrl = '';

                // Clean the show name for better match probability
                $release = $this->parseInfo($row['searchname']);
                if (\is_array($release) && $release['name'] !== '') {
                    if (\in_array($release['cleanname'], $this->titleCache, false)) {
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->alternateOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->alternate('Skipped (previously failed)');
                        }
                        $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                        $skipped++;

                        continue;
                    }

                    // Find the Video ID if it already exists by checking the title against stored TVMaze titles
                    $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV, parent::SOURCE_TVMAZE);

                    // Force local lookup only
                    // $local = true, $lookupsetting = false and vice versa
                    $lookupSetting = $local !== true;

                    if ($videoId === 0 && $lookupSetting) {
                        // If lookups are allowed lets try to get it.
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->info('Searching TVMaze...');
                        }

                        // Get the show from TVMaze
                        $tvMazeShow = $this->getShowInfo((string) $release['cleanname']);

                        $dupeCheck = false;

                        if (\is_array($tvMazeShow)) {
                            $siteId = (int) $tvMazeShow['tvmaze'];
                            // Check if we have the TVDB ID already, if we do use that Video ID, unless it is 0
                            if ((int) $tvMazeShow['tvdb'] !== 0) {
                                $dupeCheck = $this->getVideoIDFromSiteID('tvdb', $tvMazeShow['tvdb']);
                            }
                            if ($dupeCheck === false) {
                                $videoId = $this->add($tvMazeShow);
                            } else {
                                $videoId = $dupeCheck;
                                // Update any missing fields and add site IDs
                                $this->update($videoId, $tvMazeShow);
                            }
                        } else {
                            $videoId = $dupeCheck;
                        }
                    } else {
                        if ($this->echooutput && $videoId > 0) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->info('Found in DB');
                        }
                        $siteId = $this->getSiteIDFromVideoID('tvmaze', $videoId);
                    }

                    if (is_numeric($videoId) && $videoId > 0 && is_numeric($siteId) && $siteId > 0) {
                        // Now that we have valid video and tvmaze ids, try to get the poster
                        $this->getPoster($videoId);

                        $seriesNo = preg_replace('/^S0*/i', '', $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', $release['episode']);

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            if ($this->echooutput) {
                                cli()->primaryOver('    → ');
                                cli()->headerOver($this->truncateTitle($release['cleanname']));
                                cli()->primaryOver(' → ');
                                cli()->primary('Full Season matched');
                            }
                            $matched++;

                            continue;
                        }

                        // Download all episodes if new show to reduce API usage
                        if ($this->countEpsByVideoID($videoId) === false) {
                            $this->getEpisodeInfo($siteId, -1, -1, '', $videoId);
                        }

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seriesNo, $episodeNo, $release['airdate']);

                        if ($episode === false) {
                            // Send the request for the episode to TVMaze
                            $tvMazeEpisode = $this->getEpisodeInfo(
                                $siteId,
                                (int) $seriesNo,
                                (int) $episodeNo,
                                $release['airdate']
                            );

                            if ($tvMazeEpisode) {
                                $episode = $this->addEpisode($videoId, $tvMazeEpisode);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                cli()->primaryOver('    → ');
                                cli()->headerOver($this->truncateTitle($release['cleanname']));
                                cli()->primaryOver(' S');
                                cli()->warningOver(sprintf('%02d', $seriesNo));
                                cli()->primaryOver('E');
                                cli()->warningOver(sprintf('%02d', $episodeNo));
                                cli()->primaryOver(' ✓ ');
                                cli()->primary('MATCHED (TVMaze)');
                            }
                            $matched++;
                        } else {
                            // Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                            if ($this->echooutput) {
                                cli()->primaryOver('    → ');
                                cli()->alternateOver($this->truncateTitle($release['cleanname']));
                                cli()->primaryOver(' → ');
                                cli()->warning('Episode not found');
                            }
                        }
                    } else {
                        // Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                        $this->titleCache[] = $release['cleanname'] ?? null;
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->alternateOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->warning('Not found');
                        }
                    }
                } else {
                    // Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                    if ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->alternateOver(mb_substr($row['searchname'], 0, 50));
                        cli()->primaryOver(' → ');
                        cli()->error('Parse failed');
                    }
                }
            }

            // Display summary
            if ($this->echooutput && $matched > 0) {
                echo "\n";
                cli()->primaryOver('  ✓ TVMaze: ');
                cli()->primary(sprintf('%d matched, %d skipped', $matched, $skipped));
            }
        }
    }

    /**
     * Truncate title for display purposes.
     */
    protected function truncateTitle(string $title, int $maxLength = 45): string
    {
        if (mb_strlen($title) <= $maxLength) {
            return $title;
        }

        return mb_substr($title, 0, $maxLength - 3).'...';
    }

    /**
     * Calls the API to lookup the TvMaze info for a given TVDB or TVRage ID
     * Returns a formatted array of show data or false if no match.
     *
     * @return array|false
     */
    protected function getShowInfoBySiteID($site, $siteId)
    {
        $return = $response = false;

        // Try for the best match with AKAs embedded
        $response = $this->client->getShowBySiteID($site, $siteId);

        sleep(1);

        if (\is_array($response)) {
            $return = $this->formatShowInfo($response);
        }

        return $return;
    }

    /**
     * Calls the API to perform initial show name match to TVDB title
     * Returns a formatted array of show data or false if no match.
     */
    public function getShowInfo(string $name): array|bool
    {
        $return = $response = false;

        // TVMaze does NOT like shows with the year in them even without the parentheses
        // Do this for the API Search only as a local lookup should require it
        $name = preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        // Try for the best match with AKAs embedded
        $response = $this->client->singleSearchAkas($name);

        sleep(1);

        if (\is_array($response)) {
            $return = $this->matchShowInfo($response, $name);
        }
        if ($return === false) {
            // Try for the best match via full search (no AKAs can be returned but the search is better)
            $response = $this->client->search($name);
            if (\is_array($response)) {
                $return = $this->matchShowInfo($response, $name);
            }
        }
        // If we didn't get any aliases do a direct alias lookup
        if (\is_array($return) && empty($return['aliases']) && is_numeric($return['tvmaze'])) {
            $return['aliases'] = $this->client->getShowAKAs($return['tvmaze']);
        }

        return $return;
    }

    /**
     * Attempts to find the best matching show by title or aliases.
     */
    private function matchShowInfo(array $shows, string $cleanName): false|array
    {
        $return = false;
        $highestMatch = 0;
        $highest = null;

        foreach ($shows as $show) {
            if ($this->checkRequiredAttr($show, 'tvmazeS')) {
                // Exact title match
                if (strcasecmp($show->name, $cleanName) === 0) {
                    $highest = $show;
                    $highestMatch = 100;
                    break;
                }

                // Title similarity
                $matchPercent = $this->checkMatch(strtolower($show->name), strtolower($cleanName), self::MATCH_PROBABILITY);
                if ($matchPercent > $highestMatch) {
                    $highestMatch = $matchPercent;
                    $highest = $show;
                }

                // Alias matches
                if (is_array($show->akas) && ! empty($show->akas)) {
                    foreach ($show->akas as $aka) {
                        if (! isset($aka['name'])) {
                            continue;
                        }

                        // Exact alias match
                        if (strcasecmp($aka['name'], $cleanName) === 0) {
                            $highest = $show;
                            $highestMatch = 100;
                            break 2;
                        }

                        // Alias similarity
                        $aliasPercent = $this->checkMatch(strtolower($aka['name']), strtolower($cleanName), self::MATCH_PROBABILITY);
                        if ($aliasPercent > $highestMatch) {
                            $highestMatch = $aliasPercent;
                            $highest = $show;
                        }
                    }
                }
            }
        }

        if ($highest !== null) {
            $return = $this->formatShowInfo($highest);
        }

        return $return;
    }

    /**
     * Retrieves the poster art for the processed show.
     *
     * @param  int  $videoId  -- the local Video ID
     */
    public function getPoster(int $videoId): int
    {
        $ri = new ReleaseImageService;

        $hasCover = 0;

        // Try to get the Poster
        if (! empty($this->posterUrl)) {
            $hasCover = $ri->saveImage((string) $videoId, $this->posterUrl, $this->imgSavePath);

            // Mark it retrieved if we saved an image
            if ($hasCover === 1) {
                $this->setCoverFound($videoId);
            }
        }

        return $hasCover;
    }

    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode, string $airDate = '', int $videoId = 0): array|bool
    {
        $return = $response = false;

        if ($airDate !== '') {
            $response = $this->client->getEpisodesByAirdate($siteId, $airDate);
        } elseif ($videoId > 0) {
            $response = $this->client->getEpisodesByShowID($siteId);
        } else {
            $response = $this->client->getEpisodeByNumber($siteId, $series, $episode);
        }

        sleep(1);

        // Handle Single Episode Lookups
        if (\is_object($response)) {
            if ($this->checkRequiredAttr($response, 'tvmazeE')) {
                $return = $this->formatEpisodeInfo($response);
            }
        } elseif (\is_array($response)) {
            // Handle new show/all episodes and airdate lookups
            foreach ($response as $singleEpisode) {
                if ($this->checkRequiredAttr($singleEpisode, 'tvmazeE')) {
                    // If this is an airdate lookup and it matches the airdate, set a return
                    if ($airDate !== '' && $airDate === $singleEpisode->airdate) {
                        $return = $this->formatEpisodeInfo($singleEpisode);
                    } else {
                        // Insert the episode
                        $this->addEpisode($videoId, $this->formatEpisodeInfo($singleEpisode));
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     */
    public function formatShowInfo($show): array
    {
        $this->posterUrl = (string) ($show->mediumImage ?? '');

        $tvdbId = (int) ($show->externalIDs['thetvdb'] ?? 0);
        $imdbId = 0;

        // Extract IMDB ID if available
        if (! empty($show->externalIDs['imdb'])) {
            preg_match('/tt(?P<imdbid>\d{6,9})$/i', $show->externalIDs['imdb'], $imdb);
            $imdbId = (int) ($imdb['imdbid'] ?? 0);
        }

        // Look up TMDB and Trakt IDs using available external IDs
        $externalIds = $this->lookupExternalIds($tvdbId, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show->name,
            'summary' => (string) $show->summary,
            'started' => (string) $show->premiered,
            'publisher' => (string) $show->network,
            'country' => (string) $show->country,
            'source' => parent::SOURCE_TVMAZE,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'tvmaze' => (int) $show->id,
            'trakt' => $externalIds['trakt'],
            'tvrage' => (int) ($show->externalIDs['tvrage'] ?? 0),
            'tmdb' => $externalIds['tmdb'],
            'aliases' => ! empty($show->akas) ? (array) $show->akas : '',
            'localzone' => "''",
        ];
    }

    /**
     * Look up TMDB and Trakt IDs using TVDB ID and IMDB ID.
     *
     * @param  int  $tvdbId  TVDB show ID
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return array ['tmdb' => int, 'trakt' => int]
     */
    protected function lookupExternalIds(int $tvdbId, int|string $imdbId): array
    {
        $result = ['tmdb' => 0, 'trakt' => 0];

        try {
            // Try to get TMDB ID via TMDB's find endpoint
            $tmdbClient = app(\App\Services\TmdbClient::class);
            if ($tmdbClient->isConfigured()) {
                // Try TVDB ID first
                if ($tvdbId > 0) {
                    $tmdbIds = $tmdbClient->lookupTvShowIds($tvdbId, 'tvdb');
                    if ($tmdbIds !== null) {
                        $result['tmdb'] = $tmdbIds['tmdb'] ?? 0;
                    }
                }
                // Try IMDB ID if TMDB not found
                if ($result['tmdb'] === 0 && ! empty($imdbId) && $imdbId > 0) {
                    $tmdbIds = $tmdbClient->lookupTvShowIds($imdbId, 'imdb');
                    if ($tmdbIds !== null) {
                        $result['tmdb'] = $tmdbIds['tmdb'] ?? 0;
                    }
                }
            }

            // Try to get Trakt ID via Trakt's search endpoint
            $traktService = app(\App\Services\TraktService::class);
            if ($traktService->isConfigured()) {
                // Try TVDB ID first
                if ($tvdbId > 0) {
                    $traktIds = $traktService->lookupShowIds($tvdbId, 'tvdb');
                    if ($traktIds !== null && ! empty($traktIds['trakt'])) {
                        $result['trakt'] = (int) $traktIds['trakt'];
                        // Also get TMDB if we didn't find it above
                        if ($result['tmdb'] === 0 && ! empty($traktIds['tmdb'])) {
                            $result['tmdb'] = (int) $traktIds['tmdb'];
                        }

                        return $result;
                    }
                }

                // Try IMDB ID as fallback
                if (! empty($imdbId) && $imdbId > 0) {
                    $traktIds = $traktService->lookupShowIds($imdbId, 'imdb');
                    if ($traktIds !== null && ! empty($traktIds['trakt'])) {
                        $result['trakt'] = (int) $traktIds['trakt'];
                        if ($result['tmdb'] === 0 && ! empty($traktIds['tmdb'])) {
                            $result['tmdb'] = (int) $traktIds['tmdb'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - external ID lookup is optional enrichment
        }

        return $result;
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     */
    public function formatEpisodeInfo($episode): array
    {
        return [
            'title' => (string) $episode->name,
            'series' => (int) $episode->season,
            'episode' => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->season).'E'.sprintf('%02d', $episode->number),
            'firstaired' => (string) $episode->airdate,
            'summary' => (string) $episode->summary,
        ];
    }
}
