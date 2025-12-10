<?php

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Blacklight\processing\tv\TraktTv;

/**
 * Pipe for Trakt.tv API lookups.
 */
class TraktPipe extends AbstractTvProviderPipe
{
    protected int $priority = 50;
    private ?TraktTv $trakt = null;

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
    private function getTrakt(): TraktTv
    {
        if ($this->trakt === null) {
            $this->trakt = new TraktTv();
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
        $videoId = $trakt->getByTitle($cleanName, TraktTv::TYPE_TV, TraktTv::SOURCE_TRAKT);

        if ($videoId !== 0) {
            $siteId = $trakt->getSiteIDFromVideoID('trakt', $videoId);
        }

        if ($videoId === 0) {
            // Not in local DB, search Trakt
            $this->outputSearching($cleanName);

            $traktShow = $trakt->getShowInfo((string) $cleanName);

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
            $trakt->getEpisodeInfo($siteId, -1, -1);
        }

        // Check if we have the episode for this video ID
        $episode = $trakt->getBySeasonEp($videoId, $seriesNo, $episodeNo, $parsedInfo['airdate'] ?? '');

        if ($episode === false) {
            if ($seriesNo !== '' && $episodeNo !== '') {
                // Try to get episode from Trakt
                $traktEpisode = $trakt->getEpisodeInfo($siteId, (int) $seriesNo, (int) $episodeNo);

                if ($traktEpisode) {
                    $episode = $trakt->addEpisode($videoId, $traktEpisode);
                }
            }

            if ($episode === false && $hasAirdate) {
                // Refresh episode cache and attempt airdate match
                $trakt->getEpisodeInfo($siteId, -1, -1);
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

