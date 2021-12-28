<?php

namespace Blacklight\processing\tv;

use Blacklight\ReleaseImage;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\ResourceNotFoundException;
use CanIHaveSomeCoffee\TheTVDbAPI\Exception\UnauthorizedException;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;

/**
 * Class TVDB -- functions used to post process releases against TVDB.
 */
class TVDB extends TV
{
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
    public $posterUrl = '';

    /**
     * @var string URL for show fanart
     */
    public $fanartUrl = '';

    /**
     * @bool Do a local lookup only if server is down
     */
    private $local;

    /**
     * TVDB constructor.
     *
     * @param  array  $options
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
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     *
     * @param  $groupID
     * @param  $guidChar
     * @param  $process
     * @param  bool  $local
     */
    public function processSite($groupID, $guidChar, $process, $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVDB);

        $tvCount = \count($res);

        if ($this->echooutput && $tvCount > 0) {
            $this->colorCli->header('Processing TVDB lookup for '.number_format($tvCount).' release(s).', true);
        }
        $this->titleCache = [];

        foreach ($res as $row) {
            $tvDbId = false;
            $this->posterUrl = $this->fanartUrl = '';

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
                        $this->colorCli->primaryOver('Video ID for ').
                                $this->colorCli->headerOver($release['cleanname']).
                                $this->colorCli->primary(' not found in local db, checking web.', true);
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
                    $this->colorCli->primaryOver('Video ID for ').
                            $this->colorCli->headerOver($release['cleanname']).
                            $this->colorCli->primary(' found in local db, attempting episode match.', true);
                }

                if ((int) $videoId > 0 && (int) $tvDbId > 0) {
                    if (! empty($tvdbShow['poster']) || ! empty($tvdbShow['fanart'])) {
                        $this->getPoster($videoId);
                    }

                    $seasonNo = (! empty($release['season']) ? preg_replace('/^S0*/i', '', $release['season']) : '');
                    $episodeNo = (! empty($release['episode']) ? preg_replace('/^E0*/i', '', $release['episode']) : '');

                    if ($episodeNo === 'all') {
                        // Set the video ID and leave episode 0
                        $this->setVideoIdFound($videoId, $row['id'], 0);
                        $this->colorCli->primary('Found TVDB Match for Full Season!', true);
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
                            $this->colorCli->primary('Found TVDB Match!', true);
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
     *
     * @param $videoID
     * @param $siteId
     * @return bool
     */
    protected function getBanner($videoID, $siteId): bool
    {
        return false;
    }

    /*
     * Calls the API to perform initial show name match to TVDB title
     * Returns a formatted array of show data or false if no match.
     *
     *
     * @param  string  $cleanName
     * @param  string  $country
     * @return array|bool|false
     */
    protected function getShowInfo($cleanName, $country = '')
    {
        $return = $response = false;
        $highestMatch = 0;
        try {
            $response = $this->client->search()->search($cleanName);
        } catch (ResourceNotFoundException $e) {
            $response = false;
            $this->colorCli->notice('Show not found on TVDB', true);
        }

        if ($response === false && $country !== '') {
            try {
                $response = $this->client->search()->search(rtrim(str_replace($country, '', $cleanName)));
            } catch (ResourceNotFoundException $e) {
                $response = false;
                $this->colorCli->notice('Show not found on TVDB', true);
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
     * @param  int  $videoId  -- the local Video ID
     * @return int
     */
    public function getPoster($videoId): int
    {
        $ri = new ReleaseImage();

        // Try to get the Poster
        $hasCover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath, '', '', false);

        // Couldn't get poster, try fan art instead
        if ($hasCover !== 1) {
            $hasCover = $ri->saveImage($videoId, $this->fanartUrl, $this->imgSavePath, '', '', false);
        }
        // Mark it retrieved if we saved an image
        if ($hasCover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hasCover;
    }

    /*
     * Gets the specific episode info for the parsed release after match
     * Returns a formatted array of episode data or false if no match.
     *
     * @param  int  $tvDbId
     * @param  int  $season
     * @param  int  $episode
     * @param  int  $videoId
     * @return array|false
     */
    protected function getEpisodeInfo($tvDbId, $season, $episode, $videoId = 0)
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

    /*
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @param $show
     * @return array
     */
    protected function formatShowInfo($show): array
    {
        try {
            $poster = $this->client->episodes()->extended($show->id);
            $this->posterUrl = ! empty($poster[0]->thumbnail) ? $poster[0]->thumbnail : '';
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->notice('Poster image not found on TVDB', true);
        }

        try {
            $fanArt = $this->client->series()->extended($show->id);
            $this->fanartUrl = ! empty($fanArt[0]->thumbnail) ? $fanArt[0]->thumbnail : '';
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->notice('Fanart image not found on TVDB', true);
        }

        try {
            $imdbId = $this->client->series()->extended($show->id);
            preg_match('/tt(?P<imdbid>\d{6,7})$/i', $imdbId->getIMDBId(), $imdb);
        } catch (ResourceNotFoundException $e) {
            $this->colorCli->notice('Show ID not found on TVDB', true);
        }

        return [
            'type'      => parent::TYPE_TV,
            'title'     => (string) $show->seriesName,
            'summary'   => (string) $show->overview,
            'started'   => $show->firstAired,
            'publisher' => (string) $show->network,
            'poster'    => $this->posterUrl,
            'fanart'    => $this->fanartUrl,
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
     * @return array
     */
    protected function formatEpisodeInfo($episode): array
    {
        return [
            'title'       => (string) $episode->name,
            'series'      => (int) $episode->seasonNumber,
            'episode'     => (int) $episode->number,
            'se_complete' => 'S'.sprintf('%02d', $episode->seasonNumber).'E'.sprintf('%02d', $episode->number),
            'firstaired'  => $episode->firstAired,
            'summary'     => (string) $episode->overview,
        ];
    }
}
