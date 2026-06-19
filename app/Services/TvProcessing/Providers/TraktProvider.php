<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Models\Video;
use App\Services\ReleaseImageService;
use App\Services\TraktService;
use DariusIII\TVMaze\TVMaze;
use Illuminate\Support\Carbon;

/**
 * Class TraktProvider.
 *
 * Process information retrieved from the Trakt API.
 */
class TraktProvider extends AbstractTvProvider
{
    private const MATCH_PROBABILITY = 75;

    public TraktService $client;

    public int $time;

    /**
     * @string URL for show poster art
     */
    public string $posterUrl = '';

    /**
     * The URL to grab the TV fanart.
     */
    public string $fanartUrl = '';

    /**
     * The localized (network airing) timezone of the show.
     */
    private string $localizedTZ = '';

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->client = new TraktService;
    }

    /**
     * Main processing director function for scrapers
     * Calls work query function and initiates processing.
     */
    public function processSite(mixed $groupID, mixed $guidChar, int $process, bool $local = false): void
    {
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TRAKT);

        $tvcount = \count($res);

        if ($tvcount === 0) {

            return;
        }

        if ($res instanceof \Traversable) {
            $processed = 0;
            $matched = 0;
            $skipped = 0;

            foreach ($res as $row) {
                $processed++;
                $traktid = false;
                $this->posterUrl = $this->fanartUrl = $this->localizedTZ = '';

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
                        $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
                        $skipped++;

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
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->info('Searching Trakt...');
                        }

                        // Get the show from TRAKT
                        $traktShow = $this->getShowInfo((string) $release['cleanname']);

                        if (\is_array($traktShow)) {
                            $videoId = $this->add($traktShow);
                            $traktid = (int) $traktShow['trakt'];
                        }
                    } else {
                        if ($this->echooutput && $videoId > 0) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($this->truncateTitle($release['cleanname']));
                            cli()->primaryOver(' → ');
                            cli()->info('Found in DB');
                        }
                        $traktid = $this->getSiteIDFromVideoID('trakt', (int) $videoId);
                        $this->localizedTZ = $this->getLocalZoneFromVideoID($videoId);
                    }

                    if ((int) $videoId > 0 && (int) $traktid > 0) {
                        // Now that we have valid video and trakt ids, try to get the poster
                        // $this->getPoster($videoId, $traktid);

                        $seasonNo = preg_replace('/^S0*/i', '', (string) $release['season']);
                        $episodeNo = preg_replace('/^E0*/i', '', (string) $release['episode']);

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

                        // Check if we have the episode for this video ID
                        $episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

                        if ($episode === false && $lookupSetting) {
                            // Send the request for the episode to TRAKT with fallback to other IDs
                            $traktEpisode = $this->getEpisodeInfo(
                                $traktid,
                                $seasonNo,
                                $episodeNo,
                                $videoId
                            );

                            if ($traktEpisode) {
                                $episode = $this->addEpisode($videoId, $traktEpisode);
                            }
                        }

                        if ($episode !== false && is_numeric($episode) && $episode > 0) {
                            // Mark the releases video and episode IDs
                            $this->setVideoIdFound($videoId, $row['id'], $episode);
                            if ($this->echooutput) {
                                cli()->primaryOver('    → ');
                                cli()->headerOver($this->truncateTitle($release['cleanname']));
                                cli()->primaryOver(' S');
                                cli()->warningOver(sprintf('%02d', $seasonNo));
                                cli()->primaryOver('E');
                                cli()->warningOver(sprintf('%02d', $episodeNo));
                                cli()->primaryOver(' ✓ ');
                                cli()->primary('MATCHED (Trakt)');
                            }
                            $matched++;
                        } else {
                            // Processing failed, set the episode ID to the next processing group
                            $this->setVideoIdFound($videoId, $row['id'], 0);
                            $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
                            if ($this->echooutput) {
                                cli()->primaryOver('    → ');
                                cli()->alternateOver($this->truncateTitle($release['cleanname']));
                                cli()->primaryOver(' → ');
                                cli()->warning('Episode not found');
                            }
                        }
                    } else {
                        // Processing failed, set the episode ID to the next processing group
                        $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
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
                    $this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
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
                cli()->primaryOver('  ✓ Trakt: ');
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
     * Fetch banner from site.
     */
    public function getBanner(mixed $videoID, mixed $siteId): bool
    {
        return false;
    }

    /**
     * Get all external IDs for a video from the database.
     *
     * @param  int  $videoId  The local video ID
     * @return array<string, mixed> Array of external IDs: ['trakt' => X, 'tmdb' => Y, 'tvdb' => Z, 'imdb' => W]
     */
    protected function getAllSiteIdsFromVideoID(int $videoId): array
    {
        $result = Video::query()
            ->where('id', $videoId)
            ->first(['trakt', 'tmdb', 'tvdb', 'imdb']);

        if ($result === null) {
            return ['trakt' => 0, 'tmdb' => 0, 'tvdb' => 0, 'imdb' => ''];
        }

        return [
            'trakt' => (int) ($result->trakt ?? 0),
            'tmdb' => (int) ($result->tmdb ?? 0),
            'tvdb' => (int) ($result->tvdb ?? 0),
            'imdb' => (string) ($result->imdb ?? ''),
        ];
    }

    /**
     * Get episode information from Trakt using all available IDs with fallback.
     *
     * @param  int|string  $siteId  The primary site ID (Trakt)
     * @param  int|string  $series  The season number
     * @param  int|string  $episode  The episode number
     * @param  int  $videoId  Optional video ID to fetch all external IDs for fallback
     * @return array<string, mixed>|bool Episode data or false on failure
     */
    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode, int $videoId = 0): array|bool
    {
        $return = false;

        if ($videoId > 0 && (int) $series === -1 && (int) $episode === -1) {
            $this->addEpisodesForShow($siteId, $videoId);

            return false;
        }

        // If we have a video ID, get all available external IDs for fallback
        if ($videoId > 0) {
            $ids = $this->getAllSiteIdsFromVideoID($videoId);
            // Ensure the provided siteId is used as the Trakt ID if available
            if ((int) $siteId > 0) {
                $ids['trakt'] = (int) $siteId;
            }
            $response = $this->client->getEpisodeSummaryWithFallback($ids, $series, $episode);
        } else {
            // Legacy behavior: just use the provided site ID as Trakt ID
            $response = $this->client->getEpisodeSummary($siteId, $series, $episode);
        }

        sleep(1);

        if (\is_array($response)) {
            if ($this->checkRequiredAttr($response, 'traktE')) {
                $return = $this->formatEpisodeInfo($response);
            }
        }

        return $return;
    }

    public function getMovieInfo(): void {}

    /**
     * Retrieve poster image for TV episode from site using its API.
     *
     * @param  int  $videoId  ID from videos table.
     */
    public function getPoster(int $videoId): int
    {
        $hasCover = 0;
        $ri = new ReleaseImageService;

        if ($this->posterUrl !== '') {
            // Try to get the Poster
            $hasCover = $ri->saveImage((string) $videoId, $this->posterUrl, $this->imgSavePath);
        }

        // Couldn't get poster, try fan art instead
        if ($hasCover !== 1 && $this->fanartUrl !== '') {
            $hasCover = $ri->saveImage((string) $videoId, $this->fanartUrl, $this->imgSavePath);
        }

        // Mark it retrieved if we saved an image
        if ($hasCover === 1) {
            $this->setCoverFound($videoId);
        }

        return $hasCover;
    }

    /**
     * Get show information from Trakt by name.
     *
     * @return array<string, mixed>|false
     */
    public function getShowInfo(string $name): array|bool
    {
        $return = $response = false;
        $highestMatch = 0;
        $highest = null;

        // Trakt does NOT like shows with the year in them even without the parentheses
        // Do this for the API Search only as a local lookup should require it
        $name = preg_replace('# \((19|20)\d{2}\)$#', '', $name);

        $response = (array) $this->client->searchShows($name);

        sleep(1);

        foreach ($response as $show) {
            if (is_array($show) && isset($show['show']) && is_array($show['show'])) {
                $showTitle = (string) ($show['show']['title'] ?? '');
                if ($showTitle === '') {
                    continue;
                }

                // Check for exact title match first and then terminate if found
                if (strcasecmp($showTitle, $name) === 0) {
                    $highest = $show;
                    break;
                }

                // Check each show title for similarity and then find the highest similar value
                $matchPercent = $this->checkMatch($showTitle, $name, self::MATCH_PROBABILITY);

                // If new match has a higher percentage, set as new matched title
                if ($matchPercent > $highestMatch) {
                    $highestMatch = $matchPercent;
                    $highest = $show;
                }
            }
        }
        if ($highest !== null && ! empty($highest['show']['ids']['trakt'])) {
            $fullShow = $this->client->getShowSummary($highest['show']['ids']['trakt']);
            if ($this->checkRequiredAttr($fullShow, 'traktS')) {
                $return = $this->formatShowInfo($fullShow);
            }
        }

        return $return;
    }

    /**
     * Assigns API show response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        if (! is_array($show)) {
            return [];
        }

        $ids = is_array($show['ids'] ?? null) ? $show['ids'] : [];
        $airs = is_array($show['airs'] ?? null) ? $show['airs'] : [];
        $images = is_array($show['images'] ?? null) ? $show['images'] : [];

        preg_match('/tt(?P<imdbid>\d{6,})$/i', (string) ($ids['imdb'] ?? ''), $imdb);
        $this->posterUrl = is_array($images['poster'] ?? null) ? (string) ($images['poster']['thumb'] ?? '') : '';
        $this->fanartUrl = is_array($images['fanart'] ?? null) ? (string) ($images['fanart']['thumb'] ?? '') : '';
        $this->localizedTZ = (string) ($airs['timezone'] ?? '');

        $imdbId = (string) ($imdb['imdbid'] ?? '');
        $tvdbId = (int) ($ids['tvdb'] ?? 0);

        // Look up TVMaze ID using TVDB or IMDB
        $tvmazeId = $this->lookupTvMazeId($tvdbId, $imdbId);

        return [
            'type' => parent::TYPE_TV,
            'title' => (string) ($show['title'] ?? ''),
            'summary' => (string) ($show['overview'] ?? ''),
            'started' => $this->formatTraktDate($show['first_aired'] ?? null),
            'publisher' => (string) ($show['network'] ?? ''),
            'country' => (string) ($show['country'] ?? ''),
            'source' => parent::SOURCE_TRAKT,
            'imdb' => $imdbId,
            'tvdb' => $tvdbId,
            'trakt' => (int) ($ids['trakt'] ?? 0),
            'tvrage' => (int) ($ids['tvrage'] ?? 0),
            'tvmaze' => $tvmazeId,
            'tmdb' => (int) ($ids['tmdb'] ?? 0),
            'aliases' => isset($show['aliases']) && ! empty($show['aliases']) ? $show['aliases'] : '',
            'localzone' => $this->localizedTZ,
        ];
    }

    /**
     * Look up TVMaze ID using TVDB ID or IMDB ID.
     *
     * @param  int  $tvdbId  TVDB show ID
     * @param  int|string  $imdbId  IMDB ID (numeric, without 'tt' prefix)
     * @return int TVMaze ID or 0 if not found
     */
    protected function lookupTvMazeId(int $tvdbId, int|string $imdbId): int
    {
        try {
            $tvmazeClient = new TVMaze;

            // Try TVDB ID first
            if ($tvdbId > 0) {
                $result = $tvmazeClient->getShowBySiteID('thetvdb', $tvdbId);
                if ($result !== null && isset($result->id)) {
                    return (int) $result->id;
                }
            }

            // Try IMDB ID as fallback
            if (! empty($imdbId) && imdb_id_is_valid($imdbId)) {
                $imdbFormatted = 'tt'.(string) $imdbId;
                $result = $tvmazeClient->getShowBySiteID('imdb', $imdbFormatted);
                if ($result !== null && isset($result->id)) {
                    return (int) $result->id;
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - TVMaze ID lookup is optional enrichment
        }

        return 0;
    }

    /**
     * Assigns API episode response values to a formatted array for insertion
     * Returns the formatted array.
     *
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        $season = (int) ($episode['season'] ?? 0);
        $episodeNumber = (int) ($episode['number'] ?? ($episode['episode'] ?? 0));

        return [
            'title' => (string) ($episode['title'] ?? ''),
            'series' => $season,
            // Prefer the Trakt 'number' field for episode number, fall back to 'episode' if present
            'episode' => $episodeNumber,
            'se_complete' => 'S'.sprintf('%02d', $season).'E'.sprintf('%02d', $episodeNumber),
            'firstaired' => $this->formatTraktDate($episode['first_aired'] ?? null),
            'summary' => (string) ($episode['overview'] ?? ''),
        ];
    }

    private function addEpisodesForShow(int|string $siteId, int $videoId): void
    {
        $seasons = $this->client->getShowSeasons((string) $siteId, 'episodes');
        if (! is_array($seasons)) {
            return;
        }

        foreach ($seasons as $seasonInfo) {
            if (! is_array($seasonInfo)) {
                continue;
            }

            $seasonNumber = (int) ($seasonInfo['number'] ?? -1);
            if ($seasonNumber < 0) {
                continue;
            }

            $episodes = is_array($seasonInfo['episodes'] ?? null)
                ? $seasonInfo['episodes']
                : $this->client->getSeasonEpisodes((string) $siteId, $seasonNumber, 'full');

            if (! is_array($episodes)) {
                continue;
            }

            foreach ($episodes as $singleEpisode) {
                if (is_array($singleEpisode) && $this->checkRequiredAttr($singleEpisode, 'traktE')) {
                    $this->addEpisode($videoId, $this->formatEpisodeInfo($singleEpisode));
                }
            }
        }
    }

    private function formatTraktDate(mixed $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return Carbon::parse((string) $date, $this->localizedTZ !== '' ? $this->localizedTZ : null)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
