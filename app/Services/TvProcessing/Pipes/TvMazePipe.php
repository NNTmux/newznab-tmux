<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Blacklight\processing\tv\TVMaze;

/**
 * Pipe for TVMaze API lookups.
 */
class TvMazePipe extends AbstractTvProviderPipe
{
    // Video type and source constants (matching Videos class protected constants)
    private const TYPE_TV = 0;
    private const SOURCE_TVMAZE = 4;

    protected int $priority = 30;
    private ?TVMaze $tvmaze = null;

    public function getName(): string
    {
        return 'TVMaze';
    }

    public function getStatusCode(): int
    {
        return -1; // PROCESS_TVMAZE
    }

    /**
     * Get or create the TVMaze instance.
     */
    private function getTvMaze(): TVMaze
    {
        if ($this->tvmaze === null) {
            $this->tvmaze = new TVMaze();
        }
        return $this->tvmaze;
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

        $tvmaze = $this->getTvMaze();
        $siteId = false;

        // Find the Video ID if it already exists
        $videoId = $tvmaze->getByTitle($cleanName, self::TYPE_TV, self::SOURCE_TVMAZE);

        if ($videoId !== 0) {
            $siteId = $tvmaze->getSiteByID('tvmaze', $videoId);
        }

        if ($videoId === 0) {
            // Not in local DB, search TVMaze
            $this->outputSearching($cleanName);

            $tvmazeShow = $tvmaze->getShowInfo((string) $cleanName);

            if (is_array($tvmazeShow)) {
                // Check if we have a valid country
                if (isset($parsedInfo['country']) && strlen($parsedInfo['country']) === 2) {
                    $tvmazeShow['country'] = $parsedInfo['country'];
                }
                $videoId = $tvmaze->add($tvmazeShow);
                $siteId = (int) $tvmazeShow['tvmaze'];
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

        // Fetch poster if we have one
        if (! empty($tvmazeShow['poster'] ?? '')) {
            $tvmaze->getPoster($videoId);
        }

        // Process episode
        $seriesNo = ! empty($parsedInfo['season']) ? preg_replace('/^S0*/i', '', (string) $parsedInfo['season']) : '';
        $episodeNo = ! empty($parsedInfo['episode']) ? preg_replace('/^E0*/i', '', (string) $parsedInfo['episode']) : '';
        $hasAirdate = ! empty($parsedInfo['airdate']);

        if ($episodeNo === 'all') {
            // Full season release
            $tvmaze->setVideoIdFound($videoId, $context->releaseId, 0);
            $this->outputFullSeason($cleanName);
            return TvProcessingResult::matched($videoId, 0, $this->getName(), ['full_season' => true]);
        }

        // Download all episodes if new show to reduce API/bandwidth usage
        if (! $tvmaze->countEpsByVideoID($videoId)) {
            $tvmaze->getEpisodeInfo($siteId, -1, -1);
        }

        // Check if we have the episode for this video ID
        $episode = $tvmaze->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from TVMaze
                $tvmazeEpisode = $tvmaze->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo);

                if ($tvmazeEpisode) {
                    $episode = $tvmaze->addEpisode($videoId, $tvmazeEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $tvmaze->getEpisodeInfo($siteId, -1, -1);
                $episode = $tvmaze->getBySeasonEp($videoId, 0, 0, $parsedInfo['airdate']);
            }
        }

        if ($episode !== false && is_numeric($episode) && $episode > 0) {
            // Success!
            $tvmaze->setVideoIdFound($videoId, $context->releaseId, $episode);
            $this->outputMatch(
                $cleanName,
                $seriesNo !== '' ? (int) $seriesNo : null,
                $episodeNo !== '' ? (int) $episodeNo : null,
                $hasAirdate ? $parsedInfo['airdate'] : null
            );
            return TvProcessingResult::matched($videoId, (int) $episode, $this->getName());
        }

        // Episode not found
        $tvmaze->setVideoIdFound($videoId, $context->releaseId, 0);

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

