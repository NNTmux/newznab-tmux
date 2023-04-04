<?php

namespace Blacklight\processing\tv;

use Blacklight\ReleaseImage;
use Blacklight\libraries\FanartTV;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ParseException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Class TVDB -- functions used to post process releases against TVDB.
 */
class TVDB extends TV
{
    private const MATCH_PROBABILITY = 75;

    public TheTVDbAPI $client;

    /**
     * @var string Authorization token for TVDB v2 API
     */
    public string $token = '';

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * @bool Do a local lookup only if server is down
     */
    private bool $local;
    
    private FanartTV $fanart;

    private mixed $fanartapikey;

    /**
     * TVDB constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->client = new TheTVDbAPI();
        $this->local = false;

        // Check if we can get the time for API status
        // If we can't then we set local to true
        try {
            $this->token = $this->client->authentication()->login(config('tvdb.api_key'), config('tvdb.user_pin'));
        } catch (UnauthorizedException $error) {
            $this->colorCli->warning('Could not reach TVDB API. Running in local mode only!', true);
            $this->local = true;
        }

        if ($this->token !== '') {
            $this->client->setToken($this->token);
        }
        
        $this->fanartapikey = config('nntmux_api.fanarttv_api_key');
        if ($this->fanartapikey !== null) {
            $this->fanart = new FanartTV($this->fanartapikey);
        }
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     */
    public function processSite($groupID, $guidChar, $process, bool $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVDB);

        $tvCount = \count($res);

        if ($this->echooutput && $tvCount > 0) {
            $this->colorCli->header('Processing TVDB lookup for '.number_format($tvCount).' release(s).', true);
        }
        $this->titleCache = [];

        foreach ($res as $row) {
            $tvDbId = false;
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
                    $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);

                    continue;
                }

                // Find the Video ID if it already exists by checking the title.
                $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV);

                if ($videoId !== 0) {
                    $tvDbId = $this->getSiteByID('tvdb', $videoId);
                }

                // Force local lookup only
                $lookupSetting = true;
                if ($local === true || $this->local) {
                    $lookupSetting = false;
                }

                if ($tvDbId === false && $lookupSetting) {
                    // If it doesn't exist locally and lookups are allowed lets try to get it.
                    if ($this->echooutput) {
                        $this->colorCli->climate()->error('Video ID for '.$release['cleanname'].' not found in local db, checking web.');
                    }

                    // Check if we have a valid country and set it in the array
                    $country = (
                        isset($release['country']) && \strlen($release['country']) === 2
                            ? (string) $release['country']
                            : ''
                    );

                    // Get the show from TVDB
                    $tvdbShow = $this->getShowInfo((string) $release['cleanname']);

                    if (\is_array($tvdbShow)) {
                        $tvdbShow['country'] = $country;
                        $videoId = $this->add($tvdbShow);
                        $tvDbId = (int) $tvdbShow['tvdb'];
                    }
                } elseif ($this->echooutput && $tvDbId !== false) {
                    $this->colorCli->climate()->info('Video ID for '.$release['cleanname'].' found in local db, attempting episode match.');
                }

                if ((int) $videoId > 0 && (int) $tvDbId > 0) {
                    if (! empty($tvdbShow['poster'])) { // Use TVDB poster if available
                        $this->getPoster($videoId);
                    } else { // Check Fanart.tv for poster
                        $poster = $this->fanart->getTVFanart($tvDbId);
                        $this->posterUrl = collect($poster['tvposter'])->sortByDesc('likes')[0]['url'];
                        $this->getPoster($videoId);
                    }

                    $seasonNo = (! empty($release['season']) ? preg_replace('/^S0*/i', '', $release['season']) : '');
                    $episodeNo = (! empty($release['episode']) ? preg_replace('/^E0*/i', '', $release['episode']) : '');

                    if ($episodeNo === 'all') {
                        // Set the video ID and leave episode 0
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        $this->colorCli->climate()->info('Found TVDB Match for Full Season!');

                        continue;
                    }

                    // Download all episodes if new show to reduce API/bandwidth usage
                    if (! $this->countEpsByVideoID($videoId)) {
                        $this->getEpisodeInfo($tvDbId, -1, -1, $videoId);
                    }

                    // Check if we have the episode for this video ID
                    $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                    if ($episode === false && $lookupSetting) {
                        // Send the request for the episode to TVDB
                        $tvdbEpisode = $this->getEpisodeInfo(
                            $tvDbId,
                            $seasonNo,
                            $episodeNo,
                            $videoId
                        );

                        if ($tvdbEpisode) {
                            $episode = $this->addEpisode($videoId, $tvdbEpisode);
                        }
                    }

                    if ($episode !== false && is_numeric($episode) && $episode > 0) {
                        // Mark the releases video and episode IDs
                        $this->setVideoIdFound($videoId, $row['id'], $episode);
                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('Found TVDB Match!');
                        }
                    } else {
                        //Processing failed, set the episode ID to the next processing group
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                    }
                } else {
                    //Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_TVMAZE, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                }
            } else {
                //Parsing failed, take it out of the queue for examination
                $this->setVideoNotFound(parent::FAILED_PARSE, $row['id']);
                $this->titleCache[] = $release['cleanname'] ?? null;
            }
        }
    }

    /**
     * Placeholder for Videos getBanner.
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
     * @throws UnauthorizedException
     * @throws ParseException
     * @throws ExceptionInterface
     */
    protected function getShowInfo(string $name): bool|array
    {
        $return = $response = false;
        $highestMatch = 0;
        try {
            $response = $this->client->search()->search($name, ['type' => 'series']);
        } catch (ResourceNotFoundException $e) {
            $response = false;
            $this->colorCli->climate()->error('Show not found on TVDB');
        }

        sleep(1);

        if (\is_array($response)) {
            foreach ($response as $show) {
                if ($this->checkRequiredAttr($show, 'tvdbS')) {
                    // Check for exact title match first and then terminate if found
                    if (strtolower($show->name) === strtolower($name)) {
                        $highest = $show;
                        break;
                    }

                    // Check each show title for similarity and then find the highest similar value
                    $matchPercent = $this->checkMatch(strtolower($show->name), strtolower($name), self::MATCH_PROBABILITY);

                    // If new match has a higher percentage, set as new matched title
                    if ($matchPercent > $highestMatch) {
                        $highestMatch = $matchPercent;
                        $highest = $show;
                    }

                    // Check for show aliases and try match those too
                    if (! empty($show->aliases)) {
                        foreach ($show->aliases as $key => $name) {
                            $matchPercent = $this->checkMatch(strtolower($name), strtolower($name), $matchPercent);
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
     * @param  int  $videoId  -- the local Video ID
     */
    public function getPoster(int $videoId): int
    {
        $ri = new ReleaseImage();

        // Try to get the Poster
        $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath, '', '', false);
        // Mark it retrieved if we saved an image
        if ($hasCover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hasCover;
    }

    /**
     * @throws \CanIHaveSomeCoffee\TheTVDbAPI\Exception\ParseException
     * @throws \CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    protected function getEpisodeInfo(int $tvDbId, int $season, int $episode, int $videoId = 0): bool|array
    {
        $return = $response = false;

        if ($videoId > 0) {
            try {
                $response = $this->client->series()->allEpisodes($tvDbId);
            } catch (ResourceNotFoundException $error) {
                return false;
            }
        } else {
            try {
                foreach ($this->client->series()->episodes($tvDbId) as $episodeBaseRecord) {
                    if ($episodeBaseRecord->seasonNumber === $season && $episodeBaseRecord->number === $episode) {
                        $response = $episodeBaseRecord;
                    }
                }
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
     * @throws ExceptionInterface
     * @throws ParseException
     * @throws UnauthorizedException
     */
    protected function formatShowInfo($show): array
    {
        try {
            $poster = $this->client->series()->artworks($show->tvdb_id);
            // Grab the image with the highest score where type == 2
            $poster = collect($poster)->where('type', 2)->sortByDesc('score')->first();  
            $this->posterUrl = ! empty($poster->image) ? $poster->image : '';
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->climate()->error('Poster image not found on TVDB');
        }

        try {
            $imdbId = $this->client->series()->extended($show->tvdb_id);
            preg_match('/tt(?P<imdbid>\d{6,9})$/i', $imdbId->getIMDBId(), $imdb);
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->climate()->error('Show ImdbId not found on TVDB');
        }

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show->name,
            'summary' => (string) $show->overview,
            'started' => $show->first_air_time,
            'publisher' => $imdbId->originalNetwork->name ?? '',
            'poster' => $this->posterUrl,
            'source' => parent::SOURCE_TVDB,
            'imdb' => (int) ($imdb['imdbid'] ?? 0),
            'tvdb' => (int) $show->tvdb_id,
            'trakt' => 0,
            'tvrage' => 0,
            'tvmaze' => 0,
            'tmdb' => 0,
            'aliases' => ! empty($show->aliases) ? $show->aliases : '',
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
         'series' => (int) $episode->seasonNumber,
         'episode' => (int) $episode->number,
         'se_complete' => 'S'.sprintf('%02d', $episode->seasonNumber).'E'.sprintf('%02d', $episode->number),
         'firstaired' => $episode->aired,
         'summary' => (string) $episode->overview,
     ];
 }
}
