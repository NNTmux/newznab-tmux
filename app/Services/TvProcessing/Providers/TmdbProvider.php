<?php

namespace App\Services\TvProcessing\Providers;

use App\Services\TmdbClient;
use Blacklight\ReleaseImage;

class TmdbProvider extends AbstractTvProvider
{
    protected const MATCH_PROBABILITY = 75;

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * Custom TMDB API client
     */
    protected TmdbClient $tmdbClient;

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

        if ($tvcount === 0) {

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

                if (is_array($release) && $release['name'] !== '') {
                    if (in_array($release['cleanname'], $this->titleCache, false)) {
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->alternate('Skipped (previously failed)');
                        }
                        $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                        $skipped++;

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
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->info('Searching TMDB...');
                        }

                        // Get the show from TMDB
                        $tmdbShow = $this->getShowInfo((string) $release['cleanname']);

                        if (is_array($tmdbShow)) {
                            // Check if we have the TMDB ID already, if we do use that Video ID
                            $dupeCheck = $this->getVideoIDFromSiteID('tvdb', $tmdbShow['tvdb']);
                            if ($dupeCheck === false) {
                                $videoId = $this->add($tmdbShow);
                                $siteId = $tmdbShow['tmdb'];
                            } else {
                                $videoId = $dupeCheck;
                                // Update any missing fields and add site IDs
                                $this->update($videoId, $tmdbShow);
                                $siteId = $this->getSiteIDFromVideoID('tmdb', $videoId);
                            }
                        }
                    } else {
                        if ($this->echooutput && $videoId > 0) {
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->info('Found in DB');
                        }
                        $siteId = $this->getSiteIDFromVideoID('tmdb', $videoId);
                    }

                    if (is_numeric($videoId) && $videoId > 0 && is_numeric($siteId) && $siteId > 0) {
                        // Now that we have valid video and tmdb ids, try to get the poster
                        $this->getPoster($videoId);

                        $seriesNo = preg_replace('/^S0*/i', '', $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', $release['episode']);
                        $hasAirdate = ! empty($release['airdate']);

                        if ($episodeNo === 'all') {
                            // Set the video ID and leave episode 0
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            if ($this->echooutput) {
                                $this->colorCli->primaryOver('    → ');
                                $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                                $this->colorCli->primaryOver(' → ');
                                $this->colorCli->primary('Full Season matched');
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
                            if ($seriesNo !== '' && $episodeNo !== '') {
                                // Send the request for the episode to TMDB using season/episode numbers
                                $tmdbEpisode = $this->getEpisodeInfo(
                                    $siteId,
                                    $seriesNo,
                                    $episodeNo,
                                    $release['airdate']
                                );

                                if ($tmdbEpisode) {
                                    $episode = $this->addEpisode($videoId, $tmdbEpisode);
                                }
                            }

                            if ($episode === false && $hasAirdate) {
                                // Refresh episode cache and attempt airdate match
                                $this->getEpisodeInfo($siteId, -1, -1, '', $videoId);
                                $episode = $this->getBySeasonEp($videoId, 0, 0, $release['airdate']);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                $this->colorCli->primaryOver('    → ');
                                $this->colorCli->headerOver($this->truncateTitle($release['cleanname']));
                                if ($seriesNo !== '' && $episodeNo !== '') {
                                    $this->colorCli->primaryOver(' S');
                                    $this->colorCli->warningOver(sprintf('%02d', $seriesNo));
                                    $this->colorCli->primaryOver('E');
                                    $this->colorCli->warningOver(sprintf('%02d', $episodeNo));
                                } elseif ($hasAirdate) {
                                    $this->colorCli->primaryOver(' | ');
                                    $this->colorCli->warningOver($release['airdate']);
                                }
                                $this->colorCli->primaryOver(' ✓ ');
                                $this->colorCli->primary('MATCHED (TMDB)');
                            }
                            $matched++;
                        } else {
                            // Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                            if ($this->echooutput) {
                                $this->colorCli->primaryOver('    → ');
                                $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                                if ($hasAirdate) {
                                    $this->colorCli->primaryOver(' | ');
                                    $this->colorCli->warningOver($release['airdate']);
                                }
                                $this->colorCli->primaryOver(' → ');
                                $this->colorCli->warning('Episode not found');
                            }
                        }
                    } else {
                        // Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                        $this->titleCache[] = $release['cleanname'] ?? null;
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->alternateOver($this->truncateTitle($release['cleanname']));
                            $this->colorCli->primaryOver(' → ');
                            $this->colorCli->warning('Not found');
                        }
                    }
                } else {
                    // Processing failed, set the episode ID to the next processing group
                    $this->setVideoNotFound(parent::PROCESS_TRAKT, $row['id']);
                    $this->titleCache[] = $release['cleanname'] ?? null;
                    if ($this->echooutput) {
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->alternateOver(mb_substr($row['searchname'], 0, 50));
                        $this->colorCli->primaryOver(' → ');
                        $this->colorCli->error('Parse failed');
                    }
                }
            }

            // Display summary
            if ($this->echooutput && $matched > 0) {
                echo "\n";
                $this->colorCli->primaryOver('  ✓ TMDB: ');
                $this->colorCli->primary(sprintf('%d matched, %d skipped', $matched, $skipped));
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
     * Calls the API to perform initial show name match to TMDB title
     * Returns a formatted array of show data or false if no match.
     *
     * @return array|false
     */
    public function getShowInfo(string $name): bool|array
    {
        $return = false;

        $this->tmdbClient = app(TmdbClient::class);

        if (! $this->tmdbClient->isConfigured()) {
            return false;
        }

        $response = $this->tmdbClient->searchTv($name);

        sleep(1);

        if ($response !== null && ! empty($response['results']) && is_array($response['results'])) {
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
        $highest = null;

        foreach ($shows as $show) {
            if (! is_array($show) || ! $this->checkRequiredAttr($show, 'tmdbS')) {
                continue;
            }

            $showName = TmdbClient::getString($show, 'name');
            if (empty($showName)) {
                continue;
            }

            // Check for exact title match first and then terminate if found
            if (strtolower($showName) === strtolower($cleanName)) {
                $highest = $show;
                break;
            }

            // Check each show title for similarity and then find the highest similar value
            $matchPercent = $this->checkMatch(strtolower($showName), strtolower($cleanName), self::MATCH_PROBABILITY);

            // If new match has a higher percentage, set as new matched title
            if ($matchPercent > $highestMatch) {
                $highestMatch = $matchPercent;
                $highest = $show;
            }
        }

        if ($highest !== null && is_array($highest)) {
            $showId = TmdbClient::getInt($highest, 'id');
            if ($showId === 0) {
                return false;
            }

            $showAlternativeTitles = $this->tmdbClient->getTvAlternativeTitles($showId);
            $showExternalIds = $this->tmdbClient->getTvExternalIds($showId);

            if ($showAlternativeTitles === null || $showExternalIds === null) {
                return false;
            }

            $alternativeTitles = [];
            $results = TmdbClient::getArray($showAlternativeTitles, 'results');
            foreach ($results as $aka) {
                if (is_array($aka) && isset($aka['title'])) {
                    $alternativeTitles[] = $aka['title'];
                }
            }
            $highest['alternative_titles'] = $alternativeTitles;

            // Use available network info if present
            $networks = TmdbClient::getArray($highest, 'networks');
            $highest['network'] = ! empty($networks[0]['name']) ? $networks[0]['name'] : '';

            $highest['external_ids'] = $showExternalIds;

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
    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode, string $airdate = '', int $videoId = 0): bool|array
    {
        $return = false;

        if (! isset($this->tmdbClient)) {
            $this->tmdbClient = app(TmdbClient::class);
        }

        if (! $this->tmdbClient->isConfigured()) {
            return false;
        }

        // Bulk fetch all episodes for all seasons and insert
        if ($videoId > 0 && (int) $series === -1 && (int) $episode === -1) {
            $tvDetails = $this->tmdbClient->getTvShow((int) $siteId);

            if ($tvDetails === null) {
                return false;
            }

            $seasons = TmdbClient::getArray($tvDetails, 'seasons');
            if (empty($seasons)) {
                return false;
            }

            foreach ($seasons as $seriesInfo) {
                if (! is_array($seriesInfo)) {
                    continue;
                }

                $seasonNumber = TmdbClient::getInt($seriesInfo, 'season_number');
                if ($seasonNumber <= 0) {
                    continue;
                }

                $seriesData = $this->tmdbClient->getTvSeason((int) $siteId, $seasonNumber);
                sleep(1);

                if ($seriesData === null) {
                    continue;
                }

                $episodes = TmdbClient::getArray($seriesData, 'episodes');
                foreach ($episodes as $ep) {
                    if (is_array($ep) && $this->checkRequiredAttr($ep, 'tmdbE')) {
                        $this->addEpisode($videoId, $this->formatEpisodeInfo($ep));
                    }
                }
            }

            return false;
        }

        // Single episode lookup
        $response = $this->tmdbClient->getTvEpisode((int) $siteId, (int) $series, (int) $episode);
        sleep(1);

        // Handle Single Episode Lookups
        if ($response !== null && is_array($response) && $this->checkRequiredAttr($response, 'tmdbE')) {
            $return = $this->formatEpisodeInfo($response);
        }

        return $return;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     */
    public function formatShowInfo($show): array
    {
        if (! is_array($show)) {
            return [];
        }

        $posterPath = TmdbClient::getString($show, 'poster_path');
        // Prefer a reasonable default size for posters if we only have a path
        $this->posterUrl = ! empty($posterPath)
            ? 'https://image.tmdb.org/t/p/w500'.$posterPath
            : '';

        $imdbId = 0;
        $externalIds = TmdbClient::getArray($show, 'external_ids');
        if (! empty($externalIds['imdb_id'])) {
            preg_match('/tt(?P<imdbid>\d{6,7})$/i', $externalIds['imdb_id'], $imdb);
            $imdbId = $imdb['imdbid'] ?? 0;
        }

        $originCountry = TmdbClient::getArray($show, 'origin_country');
        $alternativeTitles = TmdbClient::getArray($show, 'alternative_titles');

        return [
            'type' => parent::TYPE_TV,
            'title' => TmdbClient::getString($show, 'name'),
            'summary' => TmdbClient::getString($show, 'overview'),
            'started' => TmdbClient::getString($show, 'first_air_date'),
            'publisher' => TmdbClient::getString($show, 'network'),
            'country' => ! empty($originCountry[0]) ? $originCountry[0] : '',
            'source' => parent::SOURCE_TMDB,
            'imdb' => $imdbId,
            'tvdb' => TmdbClient::getInt($externalIds, 'tvdb_id'),
            'trakt' => 0,
            'tvrage' => TmdbClient::getInt($externalIds, 'tvrage_id'),
            'tvmaze' => 0,
            'tmdb' => TmdbClient::getInt($show, 'id'),
            'aliases' => ! empty($alternativeTitles) ? $alternativeTitles : '',
            'localzone' => "''",
        ];
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     */
    public function formatEpisodeInfo($episode): array
    {
        if (! is_array($episode)) {
            return [];
        }

        $seasonNumber = TmdbClient::getInt($episode, 'season_number');
        $episodeNumber = TmdbClient::getInt($episode, 'episode_number');

        return [
            'title' => TmdbClient::getString($episode, 'name'),
            'series' => $seasonNumber,
            'episode' => $episodeNumber,
            'se_complete' => 'S'.sprintf('%02d', $seasonNumber).'E'.sprintf('%02d', $episodeNumber),
            'firstaired' => TmdbClient::getString($episode, 'air_date'),
            'summary' => TmdbClient::getString($episode, 'overview'),
        ];
    }
}
