<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\FanartTvService;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use App\Services\TvProcessing\Providers\TvdbProvider;

/**
 * Pipe for TVDB API lookups.
 */
class TvdbPipe extends AbstractTvProviderPipe
{
    // Video type constants (matching Videos class protected constants)
    private const TYPE_TV = 0;

    protected int $priority = 20;
    private ?TvdbProvider $tvdb = null;
    private ?FanartTvService $fanart = null;

    public function getName(): string
    {
        return 'TVDB';
    }

    public function getStatusCode(): int
    {
        return 0; // PROCESS_TVDB
    }

    /**
     * Get or create the TVDB instance.
     */
    private function getTvdb(): TvdbProvider
    {
        if ($this->tvdb === null) {
            $this->tvdb = new TvdbProvider();
        }
        return $this->tvdb;
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

        $tvdb = $this->getTvdb();
        $siteId = false;
        $posterUrl = '';

        // Find the Video ID if it already exists by checking the title
        $videoId = $tvdb->getByTitle($cleanName, self::TYPE_TV);

        // If not found and cleanName contains a year in parentheses, try without the year
        if ($videoId === 0 && preg_match('/^(.+?)\s*\(\d{4}\)$/', $cleanName, $yearMatch)) {
            $nameWithoutYear = trim($yearMatch[1]);
            $videoId = $tvdb->getByTitle($nameWithoutYear, self::TYPE_TV);
        }

        if ($videoId !== 0) {
            $siteId = $tvdb->getSiteByID('tvdb', $videoId);
        }

        // Check if we have a valid country
        $country = (
            isset($parsedInfo['country']) && strlen($parsedInfo['country']) === 2
                ? (string) $parsedInfo['country']
                : ''
        );

        if ($siteId === false) {
            // Not in local DB, search TVDB
            $this->outputSearching($cleanName);

            $tvdbShow = $tvdb->getShowInfo((string) $cleanName);

            // If not found and cleanName contains a year in parentheses, try without the year
            if ($tvdbShow === false && preg_match('/^(.+?)\s*\(\d{4}\)$/', $cleanName, $yearMatch)) {
                $nameWithoutYear = trim($yearMatch[1]);
                $tvdbShow = $tvdb->getShowInfo($nameWithoutYear);
            }

            if (is_array($tvdbShow)) {
                $tvdbShow['country'] = $country;
                $videoId = $tvdb->add($tvdbShow);
                $siteId = (int) $tvdbShow['tvdb'];
                $posterUrl = $tvdbShow['poster'] ?? '';
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

        // Fetch poster if available
        if (! empty($posterUrl)) {
            $tvdb->getPoster($videoId);
        } else {
            $this->fetchFanartPoster($videoId, $siteId);
        }

        // Process episode
        $seriesNo = ! empty($parsedInfo['season']) ? preg_replace('/^S0*/i', '', (string) $parsedInfo['season']) : '';
        $episodeNo = ! empty($parsedInfo['episode']) ? preg_replace('/^E0*/i', '', (string) $parsedInfo['episode']) : '';
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($episodeNo === 'all') {
            // Full season release
            $tvdb->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);
            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Download all episodes if new show to reduce API/bandwidth usage
        if (! $tvdb->countEpsByVideoID($videoId)) {
            $tvdb->getEpisodeInfo($siteId, -1, -1, $videoId);
        }

        // Check if we have the episode for this video ID
        $episode = $tvdb->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from TVDB
                $tvdbEpisode = $tvdb->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo, $videoId);

                if ($tvdbEpisode) {
                    $episode = $tvdb->addEpisode($videoId, $tvdbEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $tvdb->getEpisodeInfo($siteId, -1, -1, $videoId);
                $episode = $tvdb->getBySeasonEp($videoId, 0, 0, $parsedInfo['airdate']);
            }
        }

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            // Success!
            $tvdb->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );
            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found
        $tvdb->setVideoIdFound($videoId, $context->releaseId, 0);

        if ($this->echoOutput) {
            $this->colorCli->primaryOver('    → ');
            $this->colorCli->alternateOver($this->truncateTitle($cleanName));
            if ($hasAirdate) {
                $this->colorCli->primaryOver(' | ');
                $this->colorCli->warningOver($parsedInfo['airdate']);
            }
            $this->colorCli->primaryOver(' → ');
            $this->colorCli->warning('Episode not found');
        }

        return TvProcessingResult::notFound($this->getName(), [
            'video_id' => $videoId,
            'episode_not_found' => true,
        ]);
    }

    /**
     * Fetch poster from Fanart.tv.
     */
    private function fetchFanartPoster(int $videoId, int $siteId): void
    {
        if ($this->fanart === null) {
            $this->fanart = new FanartTvService();
        }

        if (! $this->fanart->isConfigured()) {
            return;
        }

        $posterUrl = $this->fanart->getBestTvPoster($siteId);
        if (! empty($posterUrl)) {
            $this->getTvdb()->posterUrl = $posterUrl;
            $this->getTvdb()->getPoster($videoId);
        }
    }

    /**
     * Output full season match message.
     */
    private function outputFullSeason(string $title): void
    {
        if (! $this->echoOutput) {
            return;
        }

        $this->colorCli->primaryOver('    → ');
        $this->colorCli->headerOver($this->truncateTitle($title));
        $this->colorCli->primaryOver(' → ');
        $this->colorCli->primary('Full Season matched');
    }
}

