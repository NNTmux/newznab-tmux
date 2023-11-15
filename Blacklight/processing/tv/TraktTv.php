<?php

namespace Blacklight\processing\tv;

use Blacklight\libraries\TraktAPI;
use Blacklight\ReleaseImage;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;

/**
 * Class TraktTv.
 *
 * Process information retrieved from the Trakt API.
 */
class TraktTv extends TV
{
    private const MATCH_PROBABILITY = 75;

    public TraktAPI $client;

    public $time;

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * The URL to grab the TV fanart.
     */
    public string $fanartUrl;

    /**
     * The localized (network airing) timezone of the show.
     */
    private string $localizedTZ;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
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
     */
    public function processSite($groupID, $guidChar, int $process, bool $local = false): void
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

                    if ($videoId === 0 && $lookupSetting) {
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
                            $this->colorCli->climate()->info('Found local TMDB match for: '.$release['cleanname']);
                            $this->colorCli->climate()->info(' Attempting episode lookup!');
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
                            $this->colorCli->climate()->info('Found TRAKT Match for Full Season!');

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
                                $this->colorCli->climate()->info('Found TRAKT Match!');
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
    public function getBanner($videoId, $siteId): bool
    {
        return false;
    }

    /**
     * @throws GuzzleException
     */
    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode): array|bool
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
    public function getPoster(int $videoId): int
    {
        $hasCover = 0;
        $ri = new ReleaseImage();

        if ($this->posterUrl !== '') {
            // Try to get the Poster
            $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath, '', '');
        }

        // Couldn't get poster, try fan art instead
        if ($hasCover !== 1 && $this->fanartUrl !== '') {
            $hasCover = $ri->saveImage($videoId, $this->fanartUrl, $this->imgSavePath, '', '');
        }

        // Mark it retrieved if we saved an image
        if ($hasCover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hasCover;
    }

    /**
     * @return array|false
     *
     * @throws GuzzleException
     */
    public function getShowInfo(string $name): array|bool
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
            'started' => Carbon::parse($show['first_aired'], $this->localizedTZ)->format('Y-m-d'),
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
            'firstaired' => Carbon::parse($episode['first_aired'], $this->localizedTZ)->format('Y-m-d'),
            'summary' => (string) $episode['overview'],
        ];
    }
}
