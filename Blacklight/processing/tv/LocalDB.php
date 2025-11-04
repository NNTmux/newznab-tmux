<?php

namespace Blacklight\processing\tv;

use Blacklight\ReleaseImage;

/**
 * Class LocalDB -- performs local database lookups before hitting external APIs.
 * This reduces unnecessary API calls by matching releases against already scraped data.
 */
class LocalDB extends TV
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
        $notFoundCount = 0;
        $episodeMissingCount = 0;

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

                if (isset($showInfo['season'], $showInfo['episode']) && $showInfo['episode'] !== 'all') {
                    // Try to find the specific episode
                    $episodeId = $this->getBySeasonEp(
                        $videoId,
                        $showInfo['season'],
                        $showInfo['episode'],
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
                        $this->colorCli->primaryOver(' S');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['season'] ?? 0));
                        $this->colorCli->primaryOver('E');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['episode'] ?? 0));
                        $this->colorCli->primaryOver(' ✓ ');
                        $this->colorCli->primary('MATCHED (Local DB)');
                    }
                } elseif ($videoId > 0 && isset($showInfo['season'], $showInfo['episode']) && $showInfo['episode'] !== 'all') {
                    // Show found but episode not in database yet
                    $episodeMissingCount++;
                    if ($this->echooutput) {
                        $this->colorCli->primaryOver('    → ');
                        $this->colorCli->headerOver($showInfo['cleanname']);
                        $this->colorCli->primaryOver(' S');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['season'] ?? 0));
                        $this->colorCli->primaryOver('E');
                        $this->colorCli->warningOver(sprintf('%02d', $showInfo['episode'] ?? 0));
                        $this->colorCli->headerOver(' → ');
                        $this->colorCli->notice('Show found, episode missing');
                    }
                }
            }

            if (! $matched && $videoId === false || $videoId === 0) {
                $notFoundCount++;
                if ($this->echooutput) {
                    $this->colorCli->primaryOver('    → ');
                    $this->colorCli->alternateOver($showInfo['cleanname']);
                    if (isset($showInfo['season'], $showInfo['episode'])) {
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

        if ($this->echooutput && $matchedCount > 0) {
            echo "\n";
            $this->colorCli->primaryOver('  ✓ Local DB: ');
            $this->colorCli->primary(sprintf('%d matched, %d missing episodes, %d not found', $matchedCount, $episodeMissingCount, $notFoundCount));
        }
    }

    /**
     * These abstract methods are required by parent TV class but not used for local lookups.
     */
    protected function getBanner(int $videoID, int $siteId): mixed
    {
        return false;
    }

    protected function getEpisodeInfo(int|string $siteId, int|string $series, int|string $episode): array|bool
    {
        return false;
    }

    protected function getPoster(int $videoId): int
    {
        return (new ReleaseImage)->saveImage($videoId, '', $this->imgSavePath, '', '', parent::TYPE_TV);
    }

    protected function getShowInfo(string $name): bool|array
    {
        return false;
    }

    protected function formatShowInfo($show): array
    {
        return [];
    }

    protected function formatEpisodeInfo($episode): array
    {
        return [];
    }
}
