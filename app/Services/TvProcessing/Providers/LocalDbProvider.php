<?php

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
    public function processSite($groupID, $guidChar, $process, bool $local = false): void
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
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->headerOver($showInfo['cleanname']);
                        if ($hasAirdate) {
                            $this->colorCli->primaryOver(' | ');
                            $this->colorCli->warningOver($showInfo['airdate']);
                        } else {
                            $this->colorCli->primaryOver(' S');
                            $this->colorCli->warningOver(sprintf('%02d', (int) ($showInfo['season'] ?? 0)));
                            $this->colorCli->primaryOver('E');
                            $this->colorCli->warningOver(sprintf('%02d', (int) ($showInfo['episode'] ?? 0)));
                        }
                        $this->colorCli->primaryOver(' ✓ ');
                        $this->colorCli->primary('MATCHED (Local DB)');
                    }
                } else {
                    // Series matched but no specific episode located
                    $this->setVideoIdFound($videoId, $release->id, 0);
                    $matched = true;
                    $seriesMatchedCount++;

                    if ($hasEpisodeNumbers || $hasAirdate) {
                        $episodeMissingCount++;
                        if ($this->echooutput) {
                            $this->colorCli->primaryOver('    → ');
                            $this->colorCli->headerOver($showInfo['cleanname']);
                            if ($hasAirdate) {
                                $this->colorCli->primaryOver(' | ');
                                $this->colorCli->warningOver($showInfo['airdate']);
                            } else {
                                $this->colorCli->primaryOver(' S');
                                $this->colorCli->warningOver(sprintf('%02d', (int) ($showInfo['season'] ?? 0)));
                                $this->colorCli->primaryOver('E');
                                $this->colorCli->warningOver(sprintf('%02d', (int) ($showInfo['episode'] ?? 0)));
                            }
                            $this->colorCli->headerOver(' → ');
                            $this->colorCli->notice($hasAirdate ? 'Series matched, airdate not in local DB' : 'Series matched, episode not in local DB');
                        }
                    } elseif ($this->echooutput) {
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->headerOver($showInfo['cleanname']);
                        $this->colorCli->primaryOver(' → ');
                        $this->colorCli->notice('Series matched (no episode info)');
                    }
                }
            }

            if (! $matched && $videoId === false || $videoId === 0) {
                $notFoundCount++;
                if ($this->echooutput) {
                    $this->colorCli->primaryOver('    → ');
                    $this->colorCli->alternateOver($showInfo['cleanname']);
                    if (! empty($showInfo['airdate'])) {
                        $this->colorCli->primaryOver(' | ');
                        $this->colorCli->warningOver($showInfo['airdate']);
                    } elseif (isset($showInfo['season'], $showInfo['episode'])) {
                        $this->colorCli->primaryOver(' S');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['season'] ?? 0));
                        $this->colorCli->primaryOver('E');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['episode'] ?? 0));
                    }
                    $this->colorCli->primaryOver(' → ');
                    $this->colorCli->alternate('Not in local DB');
                }
            }
        }

        if ($this->echooutput && ($matchedCount > 0 || $seriesMatchedCount > 0)) {
            echo "\n";
            $this->colorCli->primaryOver('  ✓ Local DB: ');
            $this->colorCli->primary(sprintf(
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

    public function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode): array|bool
    {
        return false;
    }

    public function getPoster(int $videoId): int
    {
        return (new ReleaseImageService)->saveImage($videoId, '', $this->imgSavePath, '', '', parent::TYPE_TV);
    }

    public function getShowInfo(string $name): bool|array
    {
        return false;
    }

    public function formatShowInfo($show): array
    {
        return [];
    }

    public function formatEpisodeInfo($episode): array
    {
        return [];
    }
}
