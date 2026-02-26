<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Providers;

use App\Services\ReleaseImageService;

/**
 * Class LocalDbProvider -- performs local database lookups before hitting external APIs.
 * This reduces unnecessary API calls by matching releases against already scraped data.
 */
class LocalDbProvider extends AbstractTvProvider
{
    /**
     * Main processing director function for local database lookups.
     * Attempts to match releases against existing video data in the database.
     */
    public function processSite(mixed $groupID, mixed $guidChar, mixed $process, bool $local = false): void
    {
        // Get releases that need processing
        $res = $this->getTvReleases($groupID, $guidChar, $process, parent::PROCESS_TVDB);

        $tvCount = \count($res);
        $matchedCount = 0;
        $seriesMatchedCount = 0;
        $episodeMissingCount = 0;
        $notFoundCount = 0;

        if ($tvCount === 0) {
            return;
        }

        foreach ($res as $index => $release) {
            $matched = false;

            // Parse release name to extract show info
            $showInfo = $this->parseInfo($release->searchname);

            if ($showInfo === false) {
                continue;
            }

            // Try to find the show in our local database by title
            $videoId = $this->getByTitle($showInfo['cleanname'], parent::TYPE_TV, 0);

            if ($videoId !== 0 && $videoId !== false) {
                // Found a matching show in local DB
                $episodeId = false;
                $hasEpisodeNumbers = isset($showInfo['season'], $showInfo['episode']) && $showInfo['episode'] !== 'all' && (int) $showInfo['season'] > 0 && (int) $showInfo['episode'] > 0;
                $hasAirdate = ! empty($showInfo['airdate']);

                if ($hasEpisodeNumbers || $hasAirdate) {
                    // Try to find the specific episode
                    $episodeId = $this->getBySeasonEp(
                        $videoId,
                        (int) ($showInfo['season'] ?? 0),
                        (int) ($showInfo['episode'] ?? 0),
                        $showInfo['airdate'] ?? ''
                    );
                }

                if ($episodeId !== false && $episodeId > 0) {
                    // Complete match - both show and episode found
                    $this->setVideoIdFound($videoId, $release->id, $episodeId);
                    $matched = true;
                    $matchedCount++;

                    if ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->headerOver($showInfo['cleanname']);
                        if ($hasAirdate) {
                            cli()->primaryOver(' | ');
                            cli()->warningOver($showInfo['airdate']);
                        } else {
                            cli()->primaryOver(' S');
                            cli()->warningOver(sprintf('%02d', (int) ($showInfo['season'] ?? 0)));
                            cli()->primaryOver('E');
                            cli()->warningOver(sprintf('%02d', (int) ($showInfo['episode'] ?? 0)));
                        }
                        cli()->primaryOver(' ✓ ');
                        cli()->primary('MATCHED (Local DB)');
                    }
                } else {
                    // Series matched but no specific episode located
                    $this->setVideoIdFound($videoId, $release->id, 0);
                    $matched = true;
                    $seriesMatchedCount++;

                    if ($hasEpisodeNumbers || $hasAirdate) {
                        $episodeMissingCount++;
                        if ($this->echooutput) {
                            cli()->primaryOver('    → ');
                            cli()->headerOver($showInfo['cleanname']);
                            if ($hasAirdate) {
                                cli()->primaryOver(' | ');
                                cli()->warningOver($showInfo['airdate']);
                            } else {
                                cli()->primaryOver(' S');
                                cli()->warningOver(sprintf('%02d', (int) ($showInfo['season'] ?? 0)));
                                cli()->primaryOver('E');
                                cli()->warningOver(sprintf('%02d', (int) ($showInfo['episode'] ?? 0)));
                            }
                            cli()->headerOver(' → ');
                            cli()->notice($hasAirdate ? 'Series matched, airdate not in local DB' : 'Series matched, episode not in local DB');
                        }
                    } elseif ($this->echooutput) {
                        cli()->primaryOver('    → ');
                        cli()->headerOver($showInfo['cleanname']);
                        cli()->primaryOver(' → ');
                        cli()->notice('Series matched (no episode info)');
                    }
                }
            }

            if (! $matched && $videoId === false || $videoId === 0) {
                $notFoundCount++;
                if ($this->echooutput) {
                    cli()->primaryOver('    → ');
                    cli()->alternateOver($showInfo['cleanname']);
                    if (! empty($showInfo['airdate'])) {
                        cli()->primaryOver(' | ');
                        cli()->warningOver($showInfo['airdate']);
                    } elseif (isset($showInfo['season'], $showInfo['episode'])) {
                        cli()->primaryOver(' S');
                        cli()->warningOver(sprintf('%02d', $showInfo['season'] ?? 0));
                        cli()->primaryOver('E');
                        cli()->warningOver(sprintf('%02d', $showInfo['episode'] ?? 0));
                    }
                    cli()->primaryOver(' → ');
                    cli()->alternate('Not in local DB');
                }
            }
        }

        if ($this->echooutput && ($matchedCount > 0 || $seriesMatchedCount > 0)) {
            echo "\n";
            cli()->primaryOver('  ✓ Local DB: ');
            cli()->primary(sprintf(
                '%d episode matches, %d series matches, %d missing episodes, %d not found',
                $matchedCount,
                $seriesMatchedCount,
                $episodeMissingCount,
                $notFoundCount
            ));
        }
    }

    /**
     * These abstract methods are required by parent TV class but not used for local lookups.
     */
    public function getBanner(int $videoID, int $siteId): mixed
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode): array|bool
    {
        return false;
    }

    public function getPoster(int $videoId): int
    {
        return (new ReleaseImageService)->saveImage((string) $videoId, '', $this->imgSavePath, 0, 0, (bool) parent::TYPE_TV);
    }

    /**
     * @return array<string, mixed>
     */
    public function getShowInfo(string $name): bool|array
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatShowInfo(mixed $show): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEpisodeInfo(mixed $episode): array
    {
        return [];
    }
}
