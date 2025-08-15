<?php

namespace Blacklight\processing\tv;

use Blacklight\ReleaseImage;
use DariusIII\TVMaze\TVMaze as Client;

/**
 * Class TVMaze.
 *
 * Process information retrieved from the TVMaze API.
 */
class TVMaze extends TV
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

        if ($this->echooutput && $tvCount > 0) {
            $this->colorCli->header('Processing TVMaze lookup for '.number_format($tvCount).' release(s).', true);
        }

        if ($res instanceof \Traversable) {
            $this->titleCache = [];

            foreach ($res as $row) {
                $tvMazeId = false;
                $this->posterUrl = '';

                // Clean the show name for better match probability
                $release = $this->parseInfo($row['searchname']);
                if (\is_array($release) && $release['name'] !== '') {
                    if (\in_array($release['cleanname'], $this->titleCache, false)) {
                        if ($this->echooutput) {
                            $this->colorCli->headerOver('Title: ').
                                    $this->colorCli->warningOver($release['cleanname']).
                                    $this->colorCli->header(' already failed lookup for this site.  Skipping.', true);
                        }
                        $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);

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
                            $this->colorCli->primaryOver('Checking TVMaze for previously failed title: ').
                                    $this->colorCli->headerOver($release['cleanname']).
                                    $this->colorCli->primary('.', true);
                        }

                        // Get the show from TVMaze
                        $tvMazeShow = $this->getShowInfo((string) $release['cleanname']);

                        if (\is_array($tvMazeShow)) {
                            $tvMazeId = (int) $tvMazeShow['tvmaze'];
                            // Check if we have the TVDB ID already, if we do use that Video ID, unless it is 0
                            $dupeCheck = false;
                            if ((int) $tvMazeShow['tvdb'] !== 0) {
                                $dupeCheck = $this->getVideoIDFromSiteID('tvdb', $tvMazeShow['tvdb']);
                            }
                            if ($dupeCheck === false) {
                                $videoId = $this->add($tvMazeShow);
                            } else {
                                $videoId = $dupeCheck;
                                // Update any missing fields and add site IDs
                                $this->update($videoId, $tvMazeShow);
                                $tvMazeId = $this->getSiteIDFromVideoID('tvmaze', $videoId);
                            }
                        }
                    } else {
                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('Found local TVMaze match for: '.$release['cleanname'].'. Attempting episode lookup!');
                        }
                        $tvMazeId = $this->getSiteIDFromVideoID('tvmaze', $videoId);
                    }

                    if (is_numeric($videoId) && $videoId > 0 && is_numeric($tvMazeId) && $tvMazeId > 0) {
                        // Now that we have valid video and tvmaze ids, try to get the poster
                        $this->getPoster($videoId);

                        $seasonNo = preg_replace('/^S0*/i', '', $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', $release['episode']);

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->colorCli->climate()->info('Found TVMaze Match for Full Season!', true);

                            continue;
                        }

                        // Download all episodes if new show to reduce API usage
                        if ($this->countEpsByVideoID($videoId) === false) {
                            $this->getEpisodeInfo($tvMazeId, -1, -1, '', $videoId);
                        }

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                        if ($episode === false) {
                            // Send the request for the episode to TVMaze
                            $tvMazeEpisode = $this->getEpisodeInfo(
                                $tvMazeId,
                                (int) $seasonNo,
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
                                $this->colorCli->climate()->info('Found TVMaze Match!', true);
                            }
                        } else {
                            // Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                        }
                    } else {
                        // Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                        $this->titleCache[] = $release['cleanname'] ?? null;
                    }
                } else {
                    // Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_TMDB, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                }
            }
        }
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
    protected function getShowInfo(string $name): array|bool
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
     *
     * @return array|false
     */
    private function matchShowInfo(array $shows, $cleanName)
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
        $ri = new ReleaseImage;

        $hasCover = 0;

        // Try to get the Poster
        if (! empty($this->posterUrl)) {
            $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath);

            // Mark it retrieved if we saved an image
            if ($hasCover === 1) {
                $this->setCoverFound($videoId);
            }
        }

        return $hasCover;
    }

    protected function getEpisodeInfo(int|string $tvMazeId, int|string $season, int|string $episode, string $airDate = '', int $videoId = 0): array|bool
    {
        $return = $response = false;

        if ($airDate !== '') {
            $response = $this->client->getEpisodesByAirdate($tvMazeId, $airDate);
        } elseif ($videoId > 0) {
            $response = $this->client->getEpisodesByShowID($tvMazeId);
        } else {
            $response = $this->client->getEpisodeByNumber($tvMazeId, $season, $episode);
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
    protected function formatShowInfo($show): array
    {
        $this->posterUrl = (string) ($show->mediumImage ?? '');

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show->name,
            'summary' => (string) $show->summary,
            'started' => (string) $show->premiered,
            'publisher' => (string) $show->network,
            'country' => (string) $show->country,
            'source' => parent::SOURCE_TVMAZE,
            'imdb' => 0,
            'tvdb' => (int) ($show->externalIDs['thetvdb'] ?? 0),
            'tvmaze' => (int) $show->id,
            'trakt' => 0,
            'tvrage' => (int) ($show->externalIDs['tvrage'] ?? 0),
            'tmdb' => 0,
            'aliases' => ! empty($show->akas) ? (array) $show->akas : '',
            'localzone' => "''",
        ];
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     */
    protected function formatEpisodeInfo($episode): array
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
