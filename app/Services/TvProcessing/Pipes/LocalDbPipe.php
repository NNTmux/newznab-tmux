<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;

/**
 * Pipe for local database lookups.
 *
 * Matches the release to an already scraped show and episode before any API is
 * called. A show found here without the episode is handed on so the API pipes can
 * fetch the episode with their own id for the show.
 */
class LocalDbPipe extends AbstractTvProviderPipe
{
    protected int $priority = 10;

    public function __construct(private ?LocalDbProvider $localDb = null) {}

    public function getName(): string
    {
        return 'Local DB';
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        /** @var ParsedTvRelease $parsed */
        $parsed = $passable->parsed;
        $localDb = $this->localDb ??= new LocalDbProvider;
        $title = $parsed->cleanName;

        if (! $passable->hasShow()) {
            $videoId = $localDb->getByTitle($title, AbstractTvProvider::TYPE_TV);
            if ($videoId <= 0) {
                $this->outputNotFound($title);

                return TvProcessingResult::notFound($this->getName(), ['title' => $title]);
            }

            $passable->setShow($videoId, $this->getName());
        }

        $videoId = $passable->videoId;

        if ($parsed->seasonPack) {
            $passable->markSeasonPack($this->getName());
            $this->outputSeasonPack($title);

            return TvProcessingResult::seasonPack($videoId, $this->getName());
        }

        $episodeId = $parsed->hasEpisodeReference()
            ? (int) $localDb->getBySeasonEp($videoId, $parsed->season ?? -1, $parsed->episode ?? 0, (string) $parsed->airdate)
            : 0;

        if ($episodeId > 0) {
            $passable->markMatched($episodeId, $this->getName());
            $this->outputMatch($title, $parsed);

            return TvProcessingResult::matched($videoId, $episodeId, $this->getName());
        }

        $this->outputEpisodeMissing($title, $parsed);

        return TvProcessingResult::episodeMissing($videoId, $this->getName());
    }
}
