<?php

namespace Blacklight\processing\tv;

use Blacklight\ReleaseImage;
use Tmdb\Client;
use Tmdb\Exception\TmdbApiException;
use Tmdb\Helper\ImageHelper;
use Tmdb\Laravel\Facades\Tmdb as TmdbClient;
use Tmdb\Repository\ConfigurationRepository;
use Tmdb\Token\Api\ApiToken;

class TMDB extends TV
{
    protected const MATCH_PROBABILITY = 75;

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    public ApiToken $token;

    public Client $client;

    public ConfigurationRepository $configRepository;

    public \Tmdb\Model\Configuration $config;

    public ImageHelper $helper;

    /**
     * Fetch banner from site.
     */
    public function getBanner($videoId, $siteId): bool
    {
        return false;
    }

    /**
     * Main processing director function for TMDB
     * Calls work query function and initiates processing.
     */
    public function processSite($groupID, $guidChar, $process, bool $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TMDB);

        $tvcount = \count($res);
        $lookupSetting = true;

        if ($this->echooutput && $tvcount > 0) {
            $this->colorCli->header('Processing TMDB lookup for '.number_format($tvcount).' release(s).', true);
        }

        if ($res instanceof \Traversable) {
            $this->titleCache = [];

            foreach ($res as $row) {
                $tmdbid = false;
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
                        $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);

                        continue;
                    }

                    // Find the Video ID if it already exists by checking the title against stored TMDB titles
                    $videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV, parent::SOURCE_TMDB);

                    // Force local lookup only
                    if ($local === true) {
                        $lookupSetting = false;
                    }

                    // If lookups are allowed lets try to get it.
                    if ($videoId === 0 && $lookupSetting) {
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('Checking TMDB for previously failed title: ').
                                    $this->colorCli->headerOver($release['cleanname']).
                                    $this->colorCli->primary('.', true);
                        }

                        // Get the show from TMDB
                        $tmdbShow = $this->getShowInfo((string) $release['cleanname']);

                        if (\is_array($tmdbShow)) {
                            // Check if we have the TMDB ID already, if we do use that Video ID
                            $dupeCheck = $this->getVideoIDFromSiteID('tvdb', $tmdbShow['tvdb']);
                            if ($dupeCheck === false) {
                                $videoId = $this->add($tmdbShow);
                                $tmdbid = $tmdbShow['tmdb'];
                            } else {
                                $videoId = $dupeCheck;
                                // Update any missing fields and add site IDs
                                $this->update($videoId, $tmdbShow);
                                $tmdbid = $this->getSiteIDFromVideoID('tmdb', $videoId);
                            }
                        }
                    } else {
                        if ($this->echooutput) {
                            $this->colorCli->climate()->info('Found local TMDB match for: '.$release['cleanname']);
                            $this->colorCli->climate()->info('.  Attempting episode lookup!');
                        }
                        $tmdbid = $this->getSiteIDFromVideoID('tmdb', $videoId);
                    }

                    if (is_numeric($videoId) && $videoId > 0 && is_numeric($tmdbid) && $tmdbid > 0) {
                        // Now that we have valid video and tmdb ids, try to get the poster
                        $this->getPoster($videoId);

                        $seasonNo = preg_replace('/^S0*/i', '', $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', $release['episode']);

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->colorCli->climate()->info('Found TMDB Match for Full Season!');

                            continue;
                        }

                        // Download all episodes if new show to reduce API usage
                        if ($this->countEpsByVideoID($videoId) === false) {
                            $this->getEpisodeInfo($tmdbid, -1, -1, '', $videoId);
                        }

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                        if ($episode === false) {
                            // Send the request for the episode to TMDB
                            $tmdbEpisode = $this->getEpisodeInfo(
                                $tmdbid,
                                $seasonNo,
                                $episodeNo,
                                $release['airdate']
                            );

                            if ($tmdbEpisode) {
                                $episode = $this->addEpisode($videoId, $tmdbEpisode);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                $this->colorCli->climate()->info('Found TMDB Match!');
                            }
                        } else {
                            //Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                        }
                    } else {
                        //Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                        $this->titleCache[] = $release['cleanname'] ?? null;
                    }
                } else {
                    //Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                }
            }
        }
    }

    /**
     * Calls the API to perform initial show name match to TMDB title
     * Returns a formatted array of show data or false if no match.
     *
     * @return array|false
     */
    protected function getShowInfo(string $name): bool|array
    {
        $return = $response = false;

        try {
            $response = TmdbClient::getSearchApi()->searchTv($name);
        } catch (TmdbApiException|\ErrorException $e) {
            return false;
        }

        sleep(1);

        if (\is_array($response) && ! empty($response['results'])) {
            $return = $this->matchShowInfo($response['results'], $name);
        }

        return $return;
    }

    /**
     * @return array|false
     */
    private function matchShowInfo(array $shows, string $cleanName): bool|array
    {
        $return = false;
        $highestMatch = 0;

        $show = [];
        foreach ($shows as $show) {
            if ($this->checkRequiredAttr($show, 'tmdbS')) {
                // Check for exact title match first and then terminate if found
                if (strtolower($show['name']) === strtolower($cleanName)) {
                    $highest = $show;
                    break;
                }
                // Check each show title for similarity and then find the highest similar value
                $matchPercent = $this->checkMatch(strtolower($show['name']), strtolower($cleanName), self::MATCH_PROBABILITY);

                // If new match has a higher percentage, set as new matched title
                if ($matchPercent > $highestMatch) {
                    $highestMatch = $matchPercent;
                    $highest = $show;
                }
            }
        }
        if (! empty($highest)) {
            try {
                $showAlternativeTitles = TmdbClient::getTvApi()->getAlternativeTitles($highest['id']);
            } catch (TmdbApiException $e) {
                return false;
            }
            try {
                $showExternalIds = TmdbClient::getTvApi()->getExternalIds($highest['id']);
            } catch (TmdbApiException $e) {
                return false;
            }

            if (\is_array($showAlternativeTitles)) {
                foreach ($showAlternativeTitles['results'] as $aka) {
                    $highest['alternative_titles'][] = $aka['title'];
                }
                $highest['network'] = $show['networks'][0]['name'] ?? '';
                $highest['external_ids'] = $showExternalIds;
            }
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
        $ri = new ReleaseImage();

        $hascover = 0;

        // Try to get the Poster
        if (! empty($this->posterUrl)) {
            $hascover = $ri->saveImage($videoId, $this->posterUrl, $this->imgSavePath);

            // Mark it retrieved if we saved an image
            if ($hascover === 1) {
                $this->setCoverFound($videoId);
            }
        }

        return $hascover;
    }

    /**
     * Gets the specific episode info for the parsed release after match
     * Returns a formatted array of episode data or false if no match.
     *
     * @return array|false
     */
    protected function getEpisodeInfo(int|string $tmdbid, int|string $season, int|string $episode, string $airdate = '', int $videoId = 0): bool|array
    {
        $return = false;

        try {
            $response = TmdbClient::getTvEpisodeApi()->getEpisode($tmdbid, $season, $episode);
        } catch (TmdbApiException $e) {
            return false;
        }

        sleep(1);

        //Handle Single Episode Lookups
        if (\is_array($response) && $this->checkRequiredAttr($response, 'tmdbE')) {
            $return = $this->formatEpisodeInfo($response);
        }

        return $return;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     */
    protected function formatShowInfo($show): array
    {
        $this->posterUrl = isset($show['poster_path']) ? 'https://image.tmdb.org/t/p'.$show['poster_path'] : '';

        if (isset($show['external_ids']['imdb_id'])) {
            preg_match('/tt(?P<imdbid>\d{6,7})$/i', $show['external_ids']['imdb_id'], $imdb);
        }

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) $show['name'],
            'summary' => (string) $show['overview'],
            'started' => (string) $show['first_air_date'],
            'publisher' => isset($show['network']) ? (string) $show['network'] : '',
            'country' => $show['origin_country'][0] ?? '',
            'source' => parent::SOURCE_TMDB,
            'imdb' => isset($imdb['imdbid']) ? (int) $imdb['imdbid'] : 0,
            'tvdb' => isset($show['external_ids']['tvdb_id']) ? (int) $show['external_ids']['tvdb_id'] : 0,
            'trakt' => 0,
            'tvrage' => isset($show['external_ids']['tvrage_id']) ? (int) $show['external_ids']['tvrage_id'] : 0,
            'tvmaze' => 0,
            'tmdb' => (int) $show['id'],
            'aliases' => ! empty($show['alternative_titles']) ? (array) $show['alternative_titles'] : '',
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
            'title' => (string) $episode['name'],
            'series' => (int) $episode['season_number'],
            'episode' => (int) $episode['episode_number'],
            'se_complete' => 'S'.sprintf('%02d', $episode['season_number']).'E'.sprintf('%02d', $episode['episode_number']),
            'firstaired' => (string) $episode['air_date'],
            'summary' => (string) $episode['overview'],
        ];
    }
}
