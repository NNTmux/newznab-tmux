<?php

namespace Blacklight\processing\tv;

use Blacklight\libraries\TraktAPI;
use Blacklight\ReleaseImage;
use Blacklight\utility\Time;

/**
 * Class TraktTv.
 *
 * Process information retrieved from the Trakt API.
 */
class TraktTv extends TV
{
    private const MATCH_PROBABILITY = 75;

    /**
     * @var \Blacklight\libraries\TraktAPI
     */
    public $client;

    public $time;

    /**
     * @string URL for show poster art
     */
    public $posterUrl = '';

    /**
     * The URL to grab the TV fanart.
     *
     * @var string
     */
    public $fanartUrl;

    /**
     * The localized (network airing) timezone of the show.
     *
     * @var string
     */
    private $localizedTZ;

    /**
     * Construct. Set up API key.
     *
     * @param  array  $options  Class instances.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $clientId = config('nntmux_api.trakttv_api_key');
        $requestHeaders = [
            'Content-Type' => 'application/json',
            'trakt-api-version' => 2,
            'trakt-api-key' => $clientId,
            'Content-Length' => 0,
        ];
        $this->client = new TraktAPI($requestHeaders);
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     *
     * @param  bool  $local
     */
    public function processSite($groupID, $guidChar, $process, $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TRAKT);

        $tvcount = \count($res);

        if ($this->echooutput && $tvcount > 1) {
            $this->colorCli->header('Processing TRAKT lookup for '.number_format($tvcount).' release(s).', true);
        }

        if ($res instanceof \Traversable) {
            foreach ($res as $row) {
                $traktid = false;
                $this->posterUrl = $this->fanartUrl = $this->localizedTZ = '';

                // Clean the show name for better match probability
                $release = $this->parseInfo($row['searchname']);
                if (\is_array($release) && $release['name'] !== '') {
                    if (\in_array($release['cleanname'], $this->titleCache, false)) {
                        if ($this->echooutput) {
                            $this->colorCli->headerOver('Title: ').
                                    $this->colorCli->warningOver($release['cleanname']).
                                    $this->colorCli->header(' already failed lookup for this site.  Skipping.', true);
                        }
                        $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);

                        continue;
                    }

                    // Find the Video ID if it already exists by checking the title.
                    $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV, parent::SOURCE_TRAKT);

                    // Force local lookup only
                    if ($local === true) {
                        $lookupSetting = false;
                    } else {
                        $lookupSetting = true;
                    }

                    if ($videoId === false && $lookupSetting) {
                        // If it doesn't exist locally and lookups are allowed lets try to get it.
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('Checking Trakt for previously failed title: ').
                                    $this->colorCli->headerOver($release['cleanname']).
                                    $this->colorCli->primary('.', true);
                        }

                        // Get the show from TRAKT
                        $traktShow = $this->getShowInfo((string) $release['cleanname']);

                        if (\is_array($traktShow)) {
                            $videoId = $this->add($traktShow);
                            $traktid = (int) $traktShow['trakt'];
                        }
                    } else {
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('Found local TMDB match for: ').
                                    $this->colorCli->headerOver($release['cleanname']).
                                    $this->colorCli->primary('.  Attempting episode lookup!', true);
                        }
                        $traktid = $this->getSiteIDFromVideoID('trakt', $videoId);
                        $this->localizedTZ = $this->getLocalZoneFromVideoID($videoId);
                    }

                    if ((int) $videoId > 0 && (int) $traktid > 0) {
                        // Now that we have valid video and trakt ids, try to get the poster
                        //$this->getPoster($videoId, $traktid);

                        $seasonNo = preg_replace('/^S0*/i', '', $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', $release['episode']);

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->colorCli->primary('Found TRAKT Match for Full Season!', true);

                            continue;
                        }

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                        if ($episode === false && $lookupSetting) {
                            // Send the request for the episode to TRAKT
                            $traktEpisode = $this->getEpisodeInfo(
                                $traktid,
                                $seasonNo,
                                $episodeNo
                            );

                            if ($traktEpisode) {
                                $episode = $this->addEpisode($videoId, $traktEpisode);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                $this->colorCli->primary('Found TRAKT Match!', true);
                            }
                        } else {
                            //Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
                        }
                    } else {
                        //Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
                        $this->titleCache[] = $release['cleanname'] ?? null;
                    }
                } else {
                    //Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                }
            }
        }
    }

    /**
     * Fetch banner from site.
     */
    public function getBanner($videoId, $siteID): bool
    {
        return false;
    }

    /**
     * Retrieve info of TV episode from site using its API.
     *
     * @param  int  $siteId
     * @param  int  $series
     * @param  int  $episode
     * @return array|false False on failure, an array of information fields otherwise.
     */
    public function getEpisodeInfo($siteId, $series, $episode)
    {
        $return = false;

        $response = $this->client->episodeSummary($siteId, $series, $episode);

        sleep(1);

        if (\is_array($response)) {
            if ($this->checkRequiredAttr($response, 'traktE')) {
                $return = $this->formatEpisodeInfo($response);
            }
        }

        return $return;
    }

    public function getMovieInfo(): void
    {
    }

    /**
     * Retrieve poster image for TV episode from site using its API.
     *
     * @param  int  $videoId  ID from videos table.
     */
    public function getPoster($videoId): int
    {
        $hascover = 0;
        $ri = new ReleaseImage();

        if ($this->posterUrl !== '') {
            // Try to get the Poster
            $hascover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath, '', '');
        }

        // Couldn't get poster, try fan art instead
        if ($hascover !== 1 && $this->fanartUrl !== '') {
            $hascover = $ri->saveImage($videoId, $this->fanartUrl, $this->imgSavePath, '', '');
        }

        // Mark it retrieved if we saved an image
        if ($hascover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hascover;
    }

    /**
     * Retrieve info of TV programme from site using it's API.
     *
     * @param  string|null|array  $name  Title of programme to look up. Usually a cleaned up version from releases table.
     * @return array|false False on failure, an array of information fields otherwise.
     */
    public function getShowInfo($name)
    {
        $return = $response = false;
        $highestMatch = 0;
        $highest = null;

        // Trakt does NOT like shows with the year in them even without the parentheses
        // Do this for the API Search only as a local lookup should require it
        $name = preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        $response = (array) $this->client->showSearch($name);

        sleep(1);

        if (\is_array($response)) {
            foreach ($response as $show) {
                if (! is_bool($show)) {
                    // Check for exact title match first and then terminate if found
                    if ($show['show']['title'] === $name) {
                        $highest = $show;
                        break;
                    }

                    // Check each show title for similarity and then find the highest similar value
                    $matchPercent = $this->checkMatch($show['show']['title'], $name, self::MATCH_PROBABILITY);

                    // If new match has a higher percentage, set as new matched title
                    if ($matchPercent > $highestMatch) {
                        $highestMatch = $matchPercent;
                        $highest = $show;
                    }
                }
            }
            if ($highest !== null) {
                $fullShow = $this->client->showSummary($highest['show']['ids']['trakt']);
                if ($this->checkRequiredAttr($fullShow, 'traktS')) {
                    $return = $this->formatShowInfo($fullShow);
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
        preg_match('/tt(?P<imdbid>\d{6,7})$/i', $show['ids']['imdb'], $imdb);
        $this->posterUrl = $show['images']['poster']['thumb'] ?? '';
        $this->fanartUrl = $show['images']['fanart']['thumb'] ?? '';
        $this->localizedTZ = $show['airs']['timezone'];

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show['title'],
            'summary' => (string) $show['overview'],
            'started' => Time::localizeAirdate($show['first_aired'], $this->localizedTZ),
            'publisher' => (string) $show['network'],
            'country' => (string) $show['country'],
            'source' => parent::SOURCE_TRAKT,
            'imdb' => $imdb['imdbid'] ?? 0,
            'tvdb' => $show['ids']['tvdb'] ?? 0,
            'trakt' => (int) $show['ids']['trakt'],
            'tvrage' => $show['ids']['tvrage'] ?? 0,
            'tvmaze' => 0,
            'tmdb' => $show['ids']['tmdb'] ?? 0,
            'aliases' => isset($show['aliases']) && ! empty($show['aliases']) ? (array) $show['aliases'] : '',
            'localzone' => $this->localizedTZ,
        ];
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     */
    public function formatEpisodeInfo($episode): array
    {
        return [
            'title' => (string) $episode['title'],
            'series' => (int) $episode['season'],
            'episode' => (int) $episode['epsiode'],
            'se_complete' => 'S'.sprintf('%02d', $episode['season']).'E'.sprintf('%02d', $episode['episode']),
            'firstaired' => Time::localizeAirdate($episode['first_aired'], $this->localizedTZ),
            'summary' => (string) $episode['overview'],
        ];
    }
}
