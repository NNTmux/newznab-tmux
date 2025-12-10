<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Blacklight\processing\tv\TMDB;

/**
 * Pipe for TMDB API lookups.
 */
class TmdbPipe extends AbstractTvProviderPipe
{
    protected int $priority = 40;
    private ?TMDB $tmdb = null;

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
    private function getTmdb(): TMDB
    {
        if ($this->tmdb === null) {
            $this->tmdb = new TMDB();
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
        $videoId = $tmdb->getByTitle($cleanName, TMDB::TYPE_TV, TMDB::SOURCE_TMDB);

        if ($videoId !== 0) {
            $siteId = $tmdb->getSiteByID('tmdb', $videoId);
        }

        if ($videoId === 0) {
            // Not in local DB, search TMDB
            $this->outputSearching($cleanName);

            $tmdbShow = $tmdb->getShowInfo((string) $cleanName);

            if (is_array($tmdbShow)) {
                // Check if we have a valid country
                if (isset($parsedInfo['country']) && strlen($parsedInfo['country']) === 2) {
                    $tmdbShow['country'] = $parsedInfo['country'];
                }
                $videoId = $tmdb->add($tmdbShow);
                $siteId = (int) $tmdbShow['tmdb'];
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
            $tmdb->getEpisodeInfo($siteId, -1, -1);
        }

        // Check if we have the episode for this video ID
        $episode = $tmdb->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from TMDB
                $tmdbEpisode = $tmdb->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo);

                if ($tmdbEpisode) {
                    $episode = $tmdb->addEpisode($videoId, $tmdbEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $tmdb->getEpisodeInfo($siteId, -1, -1);
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

