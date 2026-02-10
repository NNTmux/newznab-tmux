<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\TmdbProvider;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;

/**
 * Pipe for TMDB API lookups.
 */
class TmdbPipe extends AbstractTvProviderPipe
{
    // Video type and source constants (matching Videos class protected constants)
    private const TYPE_TV = 0;

    private const SOURCE_TMDB = 2;

    protected int $priority = 40;

    private ?TmdbProvider $tmdb = null;

    public function getName(): string
    {
        return 'TMDB';
    }

    public function getStatusCode(): int
    {
        return -2; // PROCESS_TMDB
    }

    /**
     * Get or create the TMDB instance.
     */
    private function getTmdb(): TmdbProvider
    {
        if ($this->tmdb === null) {
            $this->tmdb = new TmdbProvider;
        }

        return $this->tmdb;
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        $parsedInfo = $passable->getParsedInfo();
        $context = $passable->context;

        if ($parsedInfo === null || empty($parsedInfo['cleanname'])) {
            return TvProcessingResult::notFound($this->getName());
        }

        $cleanName = $parsedInfo['cleanname'];

        // Check if we've already failed this title
        if ($this->isInTitleCache($cleanName)) {
            $this->outputSkipped($cleanName);

            return TvProcessingResult::skipped('previously failed', $this->getName());
        }

        $tmdb = $this->getTmdb();
        $siteId = false;

        // Find the Video ID if it already exists
        $videoId = $tmdb->getByTitle($cleanName, self::TYPE_TV, self::SOURCE_TMDB);

        // If not found and cleanName contains a year in parentheses, try without the year
        if ($videoId === 0 && preg_match('/^(.+?)\s*\(\d{4}\)$/', $cleanName, $yearMatch)) {
            $nameWithoutYear = trim($yearMatch[1]);
            $videoId = $tmdb->getByTitle($nameWithoutYear, self::TYPE_TV, self::SOURCE_TMDB);
        }

        if ($videoId !== 0) {
            $siteId = $tmdb->getSiteByID('tmdb', $videoId);
            // If show exists in local DB with a TMDB ID, use it directly
            if ($siteId !== false && $siteId !== 0) { // @phpstan-ignore notIdentical.alwaysTrue
                $this->outputFoundInDb($cleanName);
            } else {
                // Show exists in local DB but without TMDB ID (from another source)
                // Skip TMDB API search and proceed to episode matching
                $this->outputFoundInDb($cleanName);

                return $this->processEpisodeForExistingVideo($passable, $tmdb, $videoId, $parsedInfo);
            }
        }

        if ($videoId === 0) {
            // Not in local DB, search TMDB
            $this->outputSearching($cleanName);

            $tmdbShow = $tmdb->getShowInfo((string) $cleanName);

            // If not found and cleanName contains a year in parentheses, try without the year
            if ($tmdbShow === false && preg_match('/^(.+?)\s*\(\d{4}\)$/', $cleanName, $yearMatch)) {
                $nameWithoutYear = trim($yearMatch[1]);
                $tmdbShow = $tmdb->getShowInfo($nameWithoutYear);
            }

            if (is_array($tmdbShow)) {
                // Check if we have a valid country
                if (isset($parsedInfo['country']) && strlen($parsedInfo['country']) === 2) {
                    $tmdbShow['country'] = $parsedInfo['country'];
                }
                $videoId = $tmdb->add($tmdbShow);
                $siteId = (int) $tmdbShow['tmdb'];
            }
        }

        if ((int) $videoId === 0 || (int) $siteId === 0) {
            // Show not found
            $this->addToTitleCache($cleanName);
            $this->outputNotFound($cleanName);

            return TvProcessingResult::notFound($this->getName(), ['title' => $cleanName]);
        }

        // Fetch poster if we have one
        if (! empty($tmdbShow['poster'] ?? '')) {
            $tmdb->getPoster($videoId);
        }

        // Process episode
        $seriesNo = ! empty($parsedInfo['season']) ? preg_replace('/^S0*/i', '', (string) $parsedInfo['season']) : '';
        $episodeNo = ! empty($parsedInfo['episode']) ? preg_replace('/^E0*/i', '', (string) $parsedInfo['episode']) : '';
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($episodeNo === 'all') {
            // Full season release
            $tmdb->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);

            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Download all episodes if new show to reduce API/bandwidth usage
        if (! $tmdb->countEpsByVideoID($videoId)) {
            $tmdb->getEpisodeInfo($siteId, -1, -1, '', $videoId);
        }

        // Check if we have the episode for this video ID
        $episode = $tmdb->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from TMDB with fallback to other IDs
                $tmdbEpisode = $tmdb->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo, '', $videoId);

                if ($tmdbEpisode) {
                    $episode = $tmdb->addEpisode($videoId, $tmdbEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $tmdb->getEpisodeInfo($siteId, -1, -1, '', $videoId);
                $episode = $tmdb->getBySeasonEp($videoId, 0, 0, $parsedInfo['airdate']);
            }
        }

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            // Success!
            $tmdb->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );

            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found
        $tmdb->setVideoIdFound($videoId, $context->releaseId, 0);

        if ($this->echoOutput) {
            cli()->primaryOver('    → ');
            cli()->alternateOver($this->truncateTitle($cleanName));
            if ($hasAirdate) {
                cli()->primaryOver(' | ');
                cli()->warningOver($parsedInfo['airdate']);
            }
            cli()->primaryOver(' → ');
            cli()->warning('Episode not found');
        }

        return TvProcessingResult::notFound($this->getName(), [
            'video_id' => $videoId,
            'episode_not_found' => true,
        ]);
    }

    /**
     * Output full season match message.
     */
    private function outputFullSeason(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        cli()->primaryOver('    → ');
        cli()->headerOver($this->truncateTitle($title));
        cli()->primaryOver(' → ');
        cli()->primary('Full Season matched');
    }

    /**
     * Process episode matching for a video that already exists in local DB.
     * This is used when the show was added from another source and doesn't have a TMDB ID.
     */
    private function processEpisodeForExistingVideo(
        TvProcessingPassable $passable,
        TmdbProvider $tmdb,
        int $videoId,
        array $parsedInfo
    ): TvProcessingResult {
        $context = $passable->context;
        $cleanName = $parsedInfo['cleanname'];

        $seriesNo = ! empty($parsedInfo['season']) ? preg_replace('/^S0*/i', '', (string) $parsedInfo['season']) : '';
        $episodeNo = ! empty($parsedInfo['episode']) ? preg_replace('/^E0*/i', '', (string) $parsedInfo['episode']) : '';
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($episodeNo === 'all') {
            // Full season release
            $tmdb->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);

            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Try to find episode in local DB
        $episode = $tmdb->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            $tmdb->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );

            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found in local DB - mark video but episode not matched
        $tmdb->setVideoIdFound($videoId, $context->releaseId, 0);

        if ($this->echoOutput) {
            cli()->primaryOver('    → ');
            cli()->alternateOver($this->truncateTitle($cleanName));
            if ($hasAirdate) {
                cli()->primaryOver(' | ');
                cli()->warningOver($parsedInfo['airdate']);
            }
            cli()->primaryOver(' → ');
            cli()->warning('Episode not in local DB');
        }

        return TvProcessingResult::notFound($this->getName(), [
            'video_id' => $videoId,
            'episode_not_found' => true,
        ]);
    }
}
