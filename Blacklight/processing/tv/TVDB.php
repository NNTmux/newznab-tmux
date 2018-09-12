<?php

namespace Blacklight\processing\tv;

use Blacklight\ColorCLI;
use Blacklight\ReleaseImage;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;

/**
 * Class TVDB -- functions used to post process releases against TVDB.
 */
class TVDB extends TV
{
    private const TVDB_URL = 'https://api.thetvdb.com';
    private const TVDB_API_KEY = '31740C28BAC74DEF';
    private const MATCH_PROBABILITY = 75;

    /**
     * @var \CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI
     */
    public $client;

    /**
     * @var string Authorization token for TVDB v2 API
     */
    public $token;

    /**
     * @string URL for show poster art
     */
    public $posterUrl;

    /**
     * @var string URL for show fanart
     */
    public $fanartUrl;

    /**
     * @bool Do a local lookup only if server is down
     */
    private $local;

    /**
     * TVDB constructor.
     *
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->client = new TheTVDbAPI();
        $this->client->setAcceptedLanguages(['en']);
        $this->local = false;

        // Check if we can get the time for API status
        // If we can't then we set local to true
        try {
            $this->token = $this->client->authentication()->login(self::TVDB_API_KEY);
        } catch (UnauthorizedException $error) {
            ColorCLI::doEcho(ColorCLI::warning('Could not reach TVDB API. Running in local mode only!'), true);
            $this->local = true;
        }

        if (\strlen($this->token) > 0) {
            $this->client->setToken($this->token);
        }
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     *
     * @param      $groupID
     * @param      $guidChar
     * @param      $process
     * @param bool $local
     */
    public function processSite($groupID, $guidChar, $process, $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVDB);

        $tvCount = \count($res);

        if ($this->echooutput && $tvCount > 0) {
            echo ColorCLI::header('Processing TVDB lookup for '.number_format($tvCount).' release(s).');
        }

        if ($res instanceof \Traversable) {
            $this->titleCache = [];

            foreach ($res as $row) {
                $tvDbId = false;

                // Clean the show name for better match probability
                $release = $this->parseInfo($row['searchname']);
                if (\is_array($release) && $release['name'] !== '') {
                    if (\in_array($release['cleanname'], $this->titleCache, false)) {
                        if ($this->echooutput) {
                            echo ColorCLI::headerOver('Title: ').
                                    ColorCLI::warningOver($release['cleanname']).
                                    ColorCLI::header(' already failed lookup for this site.  Skipping.');
                        }
                        $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                        continue;
                    }

                    // Find the Video ID if it already exists by checking the title.
                    $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV);

                    if ($videoId !== false) {
                        $tvDbId = $this->getSiteByID('tvdb', $videoId);
                    }

                    // Force local lookup only
                    $lookupSetting = true;
                    if ($local === true || $this->local === true) {
                        $lookupSetting = false;
                    }

                    if ($tvDbId === false && $lookupSetting) {

                        // If it doesnt exist locally and lookups are allowed lets try to get it.
                        if ($this->echooutput) {
                            echo ColorCLI::primaryOver('Video ID for ').
                                ColorCLI::headerOver($release['cleanname']).
                                ColorCLI::primary(' not found in local db, checking web.');
                        }

                        // Check if we have a valid country and set it in the array
                        $country = (
                            isset($release['country']) && \strlen($release['country']) === 2
                            ? (string) $release['country']
                            : ''
                        );

                        // Get the show from TVDB
                        $tvdbShow = $this->getShowInfo((string) $release['cleanname'], $country);

                        if (\is_array($tvdbShow)) {
                            $tvdbShow['country'] = $country;
                            $videoId = $this->add($tvdbShow);
                            $tvDbId = (int) $tvdbShow['tvdb'];
                        }
                    } elseif ($this->echooutput && $tvDbId !== false) {
                        echo ColorCLI::primaryOver('Video ID for ').
                            ColorCLI::headerOver($release['cleanname']).
                            ColorCLI::primary(' found in local db, attempting episode match.');
                    }

                    if (is_numeric($videoId) && $videoId > 0 && is_numeric($tvDbId) && $tvDbId > 0) {
                        // Now that we have valid video and tvdb ids, try to get the poster
                        if (! empty($tvdbShow['banner'])) {
                            $this->posterUrl = self::TVDB_URL.'/'.$tvdbShow['banner'];
                        }

                        if (! empty($tvdbShow['fanart'])) {
                            $this->fanartUrl = self::TVDB_URL.'/'.$tvdbShow['fanart'];
                        }

                        if (! empty($tvdbShow['banner']) || ! empty($tvdbShow['fanart'])) {
                            $this->getPoster($videoId, $tvDbId);
                        }

                        $seasonNo = (! empty($release['season']) ? preg_replace('/^S0*/i', '', $release['season']) : '');
                        $episodeNo = (! empty($release['episode']) ? preg_replace('/^E0*/i', '', $release['episode']) : '');

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            echo ColorCLI::primary('Found TVDB Match for Full Season!');
                            continue;
                        }

                        // Download all episodes if new show to reduce API/bandwidth usage
                        if ($this->countEpsByVideoID($videoId) === false) {
                            $this->getEpisodeInfo($tvDbId, -1, -1, '', $videoId);
                        }

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                        if ($episode === false && $lookupSetting) {
                            // Send the request for the episode to TVDB
                            $tvdbEpisode = $this->getEpisodeInfo(
                                $tvDbId,
                                $seasonNo,
                                $episodeNo,
                                $release['airdate']
                            );

                            if ($tvdbEpisode) {
                                $episode = $this->addEpisode($videoId, $tvdbEpisode);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                echo ColorCLI::primary('Found TVDB Match!');
                            }
                        } else {
                            //Processing failed, set the episode ID to the next processing group
                            $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                        }
                    } else {
                        //Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                        $this->titleCache[] = $release['cleanname'];
                    }
                } else {
                    //Parsing failed, take it out of the queue for examination
                    $this->setVideoNotFound(parent::FAILED_PARSE, $row['id']);
                    $this->titleCache[] = $release['cleanname'];
                }
            }
        }
    }

    /**
     * Placeholder for Videos getBanner.
     *
     * @param $videoID
     * @param $siteId
     *
     * @return bool
     */
    protected function getBanner($videoID, $siteId): bool
    {
        return false;
    }

    /**
     * Calls the API to perform initial show name match to TVDB title
     * Returns a formatted array of show data or false if no match.
     *
     *
     * @param string $cleanName
     * @param string $country
     *
     * @return array|bool|false
     */
    protected function getShowInfo($cleanName, $country = '')
    {
        $return = $response = false;
        $highestMatch = 0;
        try {
            $response = $this->client->search()->searchByName($cleanName);
        } catch (ResourceNotFoundException $e) {
            $response = false;
        }

        if ($response === false && $country !== '') {
            try {
                $response = $this->client->search()->searchByName(rtrim(str_replace($country, '', $cleanName)));
            } catch (ResourceNotFoundException $e) {
                $response = false;
                ColorCLI::doEcho(ColorCLI::notice('Show not found on TVDB'), true);
            }
        }

        sleep(1);

        if (\is_array($response)) {
            foreach ($response as $show) {
                if ($this->checkRequiredAttr($show, 'tvdbS')) {
                    // Check for exact title match first and then terminate if found
                    if (strtolower($show->seriesName) === strtolower($cleanName)) {
                        $highest = $show;
                        break;
                    }

                    // Check each show title for similarity and then find the highest similar value
                    $matchPercent = $this->checkMatch(strtolower($show->seriesName), strtolower($cleanName), self::MATCH_PROBABILITY);

                    // If new match has a higher percentage, set as new matched title
                    if ($matchPercent > $highestMatch) {
                        $highestMatch = $matchPercent;
                        $highest = $show;
                    }

                    // Check for show aliases and try match those too
                    if (! empty($show->aliases)) {
                        foreach ($show->aliases as $key => $name) {
                            $matchPercent = $this->checkMatch(strtolower($name), strtolower($cleanName), $matchPercent);
                            if ($matchPercent > $highestMatch) {
                                $highestMatch = $matchPercent;
                                $highest = $show;
                            }
                        }
                    }
                }
            }
            if (! empty($highest)) {
                $return = $this->formatShowInfo($highest);
            }
        }

        return $return;
    }

    /**
     * Retrieves the poster art for the processed show.
     *
     * @param int $videoId -- the local Video ID
     * @param int $showId  -- the TVDB ID
     *
     * @return int
     */
    public function getPoster($videoId, $showId): int
    {
        $ri = new ReleaseImage();

        // Try to get the Poster
        $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath, '', '', false, $this->token);

        // Couldn't get poster, try fan art instead
        if ($hasCover !== 1) {
            $hasCover = $ri->saveImage($videoId, $this->fanartUrl, $this->imgSavePath, '', '', false, $this->token);
        }
        // Mark it retrieved if we saved an image
        if ($hasCover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hasCover;
    }

    /**
     * Gets the specific episode info for the parsed release after match
     * Returns a formatted array of episode data or false if no match.
     *
     * @param int $tvDbId
     * @param int $season
     * @param int $episode
     * @param string  $airDate
     * @param int $videoId
     *
     * @return array|false
     */
    protected function getEpisodeInfo($tvDbId, $season, $episode, $airDate = '', $videoId = 0)
    {
        $return = $response = false;

        if ($airDate !== '') {
            try {
                $response = $this->client->series()->getEpisodesWithQuery($tvDbId, ['firstAired' => $airDate])->getData();
            } catch (ResourceNotFoundException $error) {
                return false;
            }
        } elseif ($videoId > 0) {
            try {
                $response = $this->client->series()->getEpisodes($tvDbId)->getData();
            } catch (ResourceNotFoundException $error) {
                return false;
            }
        } else {
            try {
                $response = $this->client->series()->getEpisodesWithQuery($tvDbId, ['airedSeason' => $season, 'airedEpisode' => $episode])->getData();
            } catch (ResourceNotFoundException $error) {
                return false;
            }
        }

        sleep(1);

        if (\is_object($response)) {
            if ($this->checkRequiredAttr($response, 'tvdbE')) {
                $return = $this->formatEpisodeInfo($response);
            }
        } elseif ($videoId > 0 && \is_array($response)) {
            foreach ($response as $singleEpisode) {
                if ($this->checkRequiredAttr($singleEpisode, 'tvdbE')) {
                    $this->addEpisode($videoId, $this->formatEpisodeInfo($singleEpisode));
                }
            }
        }

        return $return;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @param $show
     *
     * @return array
     */
    protected function formatShowInfo($show): array
    {
        preg_match('/tt(?P<imdbid>\d{6,7})$/i', $show->imdbId, $imdb);

        return [
            'type'      => parent::TYPE_TV,
            'title'     => (string) $show->seriesName,
            'summary'   => (string) $show->overview,
            'started'   => $show->firstAired,
            'publisher' => (string) $show->network,
            'source'    => parent::SOURCE_TVDB,
            'imdb'      => (int) ($imdb['imdbid'] ?? 0),
            'tvdb'      => (int) $show->id,
            'trakt'     => 0,
            'tvrage'    => 0,
            'tvmaze'    => 0,
            'tmdb'      => 0,
            'aliases'   => ! empty($show->aliases) ? $show->aliases : '',
            'localzone' => "''",
        ];
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @param $episode
     *
     * @return array
     */
    protected function formatEpisodeInfo($episode): array
    {
        return [
            'title'       => (string) $episode->episodeName,
            'series'      => (int) $episode->airedSeason,
            'episode'     => (int) $episode->airedEpisodeNumber,
            'se_complete' => 'S'.sprintf('%02d', $episode->airedSeason).'E'.sprintf('%02d', $episode->airedEpisodeNumber),
            'firstaired'  => $episode->firstAired,
            'summary'     => (string) $episode->overview,
        ];
    }
}
