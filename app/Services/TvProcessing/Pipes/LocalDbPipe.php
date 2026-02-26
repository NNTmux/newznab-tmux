<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;

/**
 * Pipe for local database lookups.
 * Attempts to match releases against existing video data before hitting external APIs.
 */
class LocalDbPipe extends AbstractTvProviderPipe
{
    // Video type constants (matching Videos class protected constants)
    private const TYPE_TV = 0;

    protected int $priority = 10;

    private ?LocalDbProvider $localDb = null;

    public function getName(): string
    {
        return 'Local DB';
    }

    public function getStatusCode(): int
    {
        return 0; // PROCESS_TVDB - Local DB processes unmatched releases first
    }

    /**
     * Get or create the LocalDB instance.
     */
    private function getLocalDb(): LocalDbProvider
    {
        if ($this->localDb === null) {
            $this->localDb = new LocalDbProvider;
        }

        return $this->localDb;
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        $parsedInfo = $passable->getParsedInfo();
        $context = $passable->context;

        if ($parsedInfo === null || empty($parsedInfo['cleanname'])) {
            return TvProcessingResult::notFound($this->getName());
        }

        $cleanName = $parsedInfo['cleanname'];
        $localDb = $this->getLocalDb();

        // Try to find the show in our local database by title
        $videoId = $localDb->getByTitle($cleanName, self::TYPE_TV, 0);

        // If not found and cleanName contains a year in parentheses, try without the year
        if (($videoId === 0 || $videoId === false) && preg_match('/^(.+?)\s*\(\d{4}\)$/', (string) $cleanName, $yearMatch)) {
            $nameWithoutYear = trim($yearMatch[1]);
            $videoId = $localDb->getByTitle($nameWithoutYear, self::TYPE_TV, 0);
        }

        if ($videoId === 0 || $videoId === false) {
            $this->outputNotFound($cleanName);

            return TvProcessingResult::notFound($this->getName(), ['title' => $cleanName]);
        }

        // Found a matching show in local DB
        $episodeId = false;
        $hasEpisodeNumbers = $this->hasEpisodeNumbers($parsedInfo);
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($hasEpisodeNumbers || $hasAirdate) {
            // Try to find the specific episode
            $episodeId = $localDb->getBySeasonEp(
                $videoId,
                (int) ($parsedInfo['season'] ?? 0),
                (int) ($parsedInfo['episode'] ?? 0),
                $parsedInfo['airdate'] ?? ''
            );
        }

        if ($episodeId !== false && $episodeId > 0) {
            // Complete match - both show and episode found
            $localDb->setVideoIdFound($videoId, $context->releaseId, $episodeId);

            $this->outputMatch(
                $cleanName,
                $hasEpisodeNumbers ? (int) $parsedInfo['season'] : null,
                $hasEpisodeNumbers ? (int) $parsedInfo['episode'] : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );

            return TvProcessingResult::matched($videoId, $episodeId, $this->getName());
        }

        // Series matched but no specific episode located
        // Set video ID but mark episode as 0 (needs further lookup)
        $localDb->setVideoIdFound($videoId, $context->releaseId, 0);

        if ($this->echoOutput) {
            cli()->primaryOver('    → ');
            cli()->headerOver($this->truncateTitle($cleanName));
            if ($hasAirdate) {
                cli()->primaryOver(' | ');
                cli()->warningOver($parsedInfo['airdate']);
                cli()->headerOver(' → ');
                cli()->notice('Series matched, airdate not in local DB');
            } elseif ($hasEpisodeNumbers) {
                cli()->primaryOver(' S');
                cli()->warningOver(sprintf('%02d', (int) $parsedInfo['season']));
                cli()->primaryOver('E');
                cli()->warningOver(sprintf('%02d', (int) $parsedInfo['episode']));
                cli()->headerOver(' → ');
                cli()->notice('Series matched, episode not in local DB');
            } else {
                cli()->primaryOver(' → ');
                cli()->notice('Series matched (no episode info)');
            }
        }

        // Return not found so external APIs can try to get the episode
        return TvProcessingResult::notFound($this->getName(), [
            'video_id' => $videoId,
            'series_matched' => true,
            'episode_missing' => true,
        ]);
    }

    /**
     * Check if parsed info has valid episode numbers.
     *
     * @param  array<string, mixed>  $parsedInfo
     */
    private function hasEpisodeNumbers(array $parsedInfo): bool
    {
        return isset($parsedInfo['season'], $parsedInfo['episode'])
            && $parsedInfo['episode'] !== 'all'
            && (int) $parsedInfo['season'] > 0
            && (int) $parsedInfo['episode'] > 0;
    }
}
