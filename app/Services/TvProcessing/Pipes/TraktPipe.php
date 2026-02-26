<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\TraktProvider;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;

/**
 * Pipe for Trakt.tv API lookups.
 */
class TraktPipe extends AbstractTvProviderPipe
{
    // Video type and source constants (matching Videos class protected constants)
    private const TYPE_TV = 0;

    private const SOURCE_TRAKT = 5;

    protected int $priority = 50;

    private ?TraktProvider $trakt = null;

    public function getName(): string
    {
        return 'Trakt';
    }

    public function getStatusCode(): int
    {
        return -3; // PROCESS_TRAKT
    }

    /**
     * Get or create the Trakt instance.
     */
    private function getTrakt(): TraktProvider
    {
        if ($this->trakt === null) {
            $this->trakt = new TraktProvider;
        }

        return $this->trakt;
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

        $trakt = $this->getTrakt();
        $siteId = false;

        // Find the Video ID if it already exists
        $videoId = $trakt->getByTitle($cleanName, self::TYPE_TV, self::SOURCE_TRAKT);

        // If not found and cleanName contains a year in parentheses, try without the year
        if ($videoId === 0 && preg_match('/^(.+?)\s*\(\d{4}\)$/', (string) $cleanName, $yearMatch)) {
            $nameWithoutYear = trim($yearMatch[1]);
            $videoId = $trakt->getByTitle($nameWithoutYear, self::TYPE_TV, self::SOURCE_TRAKT);
        }

        if ($videoId !== 0) {
            $siteId = $trakt->getSiteIDFromVideoID('trakt', (int) $videoId);
            // If show exists in local DB but doesn't have a Trakt ID, use the existing video
            // and process episode matching without trying to search Trakt API
            if ($siteId === false || $siteId === 0) {
                // Show exists in our DB (likely from another source like TMDB)
                // Skip Trakt API search and proceed to episode matching
                $this->outputFoundInDb($cleanName);

                return $this->processEpisodeForExistingVideo($passable, $trakt, $videoId, $parsedInfo);
            }
        }

        if ($videoId === 0) {
            // Not in local DB, search Trakt
            $this->outputSearching($cleanName);

            $traktShow = $trakt->getShowInfo((string) $cleanName);

            // If not found and cleanName contains a year in parentheses, try without the year
            if ($traktShow === false && preg_match('/^(.+?)\s*\(\d{4}\)$/', (string) $cleanName, $yearMatch)) {
                $nameWithoutYear = trim($yearMatch[1]);
                $traktShow = $trakt->getShowInfo($nameWithoutYear);
            }

            if (is_array($traktShow)) {
                $videoId = $trakt->add($traktShow);
                $siteId = (int) $traktShow['trakt'];
            }
        } else {
            $this->outputFoundInDb($cleanName);
        }

        if ((int) $videoId === 0 || (int) $siteId === 0) {
            // Show not found
            $this->addToTitleCache($cleanName);
            $this->outputNotFound($cleanName);

            return TvProcessingResult::notFound($this->getName(), ['title' => $cleanName]);
        }

        // Process episode
        $seriesNo = ! empty($parsedInfo['season']) ? preg_replace('/^S0*/i', '', (string) $parsedInfo['season']) : '';
        $episodeNo = ! empty($parsedInfo['episode']) ? preg_replace('/^E0*/i', '', (string) $parsedInfo['episode']) : '';
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($episodeNo === 'all') {
            // Full season release
            $trakt->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);

            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Download all episodes if new show to reduce API/bandwidth usage
        if (! $trakt->countEpsByVideoID($videoId)) {
            $trakt->getEpisodeInfo($siteId, -1, -1, $videoId);
        }

        // Check if we have the episode for this video ID
        $episode = $trakt->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from Trakt with fallback to other IDs
                $traktEpisode = $trakt->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo, $videoId);

                if ($traktEpisode) {
                    $episode = $trakt->addEpisode($videoId, $traktEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $trakt->getEpisodeInfo($siteId, -1, -1, $videoId);
                $episode = $trakt->getBySeasonEp($videoId, 0, 0, $parsedInfo['airdate']);
            }
        }

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            // Success!
            $trakt->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );

            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found
        $trakt->setVideoIdFound($videoId, $context->releaseId, 0);

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
     * This is used when the show was added from another source (e.g., TMDB) and doesn't have a Trakt ID.
     *
     * @param  array<string, mixed>  $parsedInfo
     */
    private function processEpisodeForExistingVideo(
        TvProcessingPassable $passable,
        TraktProvider $trakt,
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
            $trakt->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);

            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Try to find episode in local DB
        $episode = $trakt->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            $trakt->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );

            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found in local DB - mark video but episode not matched
        $trakt->setVideoIdFound($videoId, $context->releaseId, 0);

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
