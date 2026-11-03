<?php

declare(strict_types=1);

namespace App\Services\TvProcessing\Pipes;

use App\Exceptions\TvProviderException;
use App\Models\Video;
use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\TvPosterResolver;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Shared flow for the pipes backed by an external API (TVDB, TVMaze, TMDB, Trakt).
 *
 * 1. Without a show yet: search the API, store the show and its poster.
 * 2. Season packs stop there.
 * 3. Otherwise resolve the episode with this provider's id for the show: the local
 *    episode table first, then the API, downloading each show's episode list at most
 *    once per refresh window.
 *
 * A transient API failure marks the release for a later retry instead of a miss.
 */
abstract class AbstractApiProviderPipe extends AbstractTvProviderPipe
{
    /** How long a failed site id cross-reference isn't tried again for a show. */
    private const int SITE_ID_MISS_TTL = 86400;

    public function __construct(
        private ?AbstractTvProvider $provider = null,
        private readonly ?TvPosterResolver $posterResolver = null,
    ) {}

    /** The videos column holding this provider's show id. */
    abstract protected function siteColumn(): string;

    abstract protected function createProvider(): AbstractTvProvider;

    /** Whether the provider is configured; unconfigured providers are skipped silently. */
    protected function isAvailable(): bool
    {
        return true;
    }

    protected function provider(): AbstractTvProvider
    {
        return $this->provider ??= $this->createProvider();
    }

    protected function process(TvProcessingPassable $passable): TvProcessingResult
    {
        $name = $this->getName();

        if (! $this->isAvailable()) {
            return TvProcessingResult::skipped('not configured', $name);
        }

        /** @var ParsedTvRelease $parsed */
        $parsed = $passable->parsed;

        if ($passable->runState->isCircuitOpen($name)) {
            $passable->markTransient($name);

            return TvProcessingResult::transient($name, null, ['reason' => 'circuit open']);
        }

        try {
            $result = $this->attempt($passable, $parsed);
            $passable->runState->recordSuccess($name);

            return $result;
        } catch (TvProviderException $e) {
            $passable->runState->recordTransient($name);
            $passable->markTransient($name, $e->retryAfterSeconds);
            $this->outputTransient($parsed->cleanName, $e->getMessage());
            Log::info('TV provider failed transiently', [
                'provider' => $name,
                'release_id' => $passable->context->releaseId,
                'status' => $e->statusCode,
                'retry_after' => $e->retryAfterSeconds,
                'error' => $e->getMessage(),
            ]);

            return TvProcessingResult::transient($name, $e->retryAfterSeconds, ['error' => $e->getMessage()]);
        }
    }

    private function attempt(TvProcessingPassable $passable, ParsedTvRelease $parsed): TvProcessingResult
    {
        $name = $this->getName();
        $title = $parsed->cleanName;
        $provider = $this->provider();

        if (! $passable->hasShow()) {
            if ($passable->runState->isTitleNotFound($name, $title)) {
                $this->outputSkipped($title, 'previously not found');

                return TvProcessingResult::skipped('previously not found', $name);
            }

            $this->outputSearching($title);
            $show = $this->searchShow($provider, $parsed);
            if (! \is_array($show) || empty($show['title'])) {
                $passable->runState->rememberTitleNotFound($name, $title);
                $this->outputNotFound($title);

                return TvProcessingResult::notFound($name, ['title' => $title]);
            }

            if (empty($show['country'])) {
                $show['country'] = $parsed->countryCode();
            }

            $videoId = $provider->add($show);
            if ($videoId <= 0) {
                $this->outputNotFound($title);

                return TvProcessingResult::notFound($name, ['title' => $title]);
            }

            $passable->setShow($videoId, $name);
            $this->storePoster($provider, $videoId, $show);
        }

        $videoId = $passable->videoId;

        if ($parsed->seasonPack) {
            $passable->markSeasonPack($name);
            $this->outputSeasonPack($title);

            return TvProcessingResult::seasonPack($videoId, $name);
        }

        $siteId = (int) $provider->getSiteIDFromVideoID($this->siteColumn(), $videoId);
        if ($siteId <= 0 && $parsed->hasEpisodeReference()) {
            $siteId = $this->resolveSiteId($provider, $videoId);
        }
        if ($siteId <= 0) {
            return TvProcessingResult::skipped('show has no '.$this->siteColumn().' id', $name);
        }

        $episodeId = $this->resolveEpisode($passable, $parsed, $provider, $siteId, $videoId);
        if ($episodeId > 0) {
            $passable->markMatched($episodeId, $name);
            $this->outputMatch($title, $parsed);

            return TvProcessingResult::matched($videoId, $episodeId, $name);
        }

        $this->outputEpisodeMissing($title, $parsed);

        return TvProcessingResult::episodeMissing($videoId, $name);
    }

    /**
     * Find this provider's id for a show another provider found (most shows only carry
     * the id of the provider that found them) and store it, so this provider can look
     * up the show's episodes too. A miss is remembered for a day across workers.
     */
    private function resolveSiteId(AbstractTvProvider $provider, int $videoId): int
    {
        $missKey = 'tv:siteid-miss:'.$this->siteColumn().':'.$videoId;
        if (Cache::has($missKey)) {
            return 0;
        }

        $siteId = $provider->resolveSiteId($provider->knownSiteIds($videoId));
        if ($siteId <= 0) {
            Cache::put($missKey, 1, self::SITE_ID_MISS_TTL);

            return 0;
        }

        // Another stored show may already own the id (a duplicate row); use it for this
        // lookup but don't copy it, so site ids stay unique.
        if (Video::query()->where($this->siteColumn(), $siteId)->where('id', '<>', $videoId)->doesntExist()) {
            $provider->update($videoId, [$this->siteColumn() => $siteId]);
        }

        return $siteId;
    }

    /**
     * Search with the full title first ("MasterChef Australia" is its own show), then
     * without a trailing country qualifier ("Big Brother Australia" is "Big Brother (AU)").
     *
     * @return array<string, mixed>|false
     */
    private function searchShow(AbstractTvProvider $provider, ParsedTvRelease $parsed): array|false
    {
        $show = $provider->getShowInfo($parsed->titleWithoutYear(), $parsed->year, $parsed->countryCode());
        if ((! \is_array($show) || empty($show['title'])) && $parsed->searchTitle() !== $parsed->titleWithoutYear()) {
            $show = $provider->getShowInfo($parsed->searchTitle(), $parsed->year, $parsed->countryCode());
        }

        return \is_array($show) ? $show : false;
    }

    private function resolveEpisode(TvProcessingPassable $passable, ParsedTvRelease $parsed, AbstractTvProvider $provider, int $siteId, int $videoId): int
    {
        if (! $parsed->hasEpisodeReference()) {
            return 0;
        }

        $episodeId = $this->findLocalEpisode($provider, $videoId, $parsed);
        if ($episodeId > 0) {
            return $episodeId;
        }

        // A show we hold no episodes for gets its whole list in one go.
        if (! $provider->countEpsByVideoID($videoId) && $this->refreshEpisodeList($passable, $provider, $siteId, $videoId)) {
            $episodeId = $this->findLocalEpisode($provider, $videoId, $parsed);
            if ($episodeId > 0) {
                return $episodeId;
            }
        }

        if ($parsed->hasEpisodeNumbers()
            && $passable->runState->firstEpisodeLookup($this->getName(), $videoId, $parsed->season.'x'.$parsed->episode)) {
            $episode = $provider->fetchEpisode($siteId, (int) $parsed->season, (int) $parsed->episode, $videoId);
            if (\is_array($episode)) {
                $episodeId = (int) $provider->addEpisode($videoId, $episode);
                if ($episodeId > 0) {
                    return $episodeId;
                }
            }
        }

        if ($parsed->hasAirdate() && $provider->supportsAirdateLookup()
            && $passable->runState->firstEpisodeLookup($this->getName(), $videoId, (string) $parsed->airdate)) {
            $provider->fetchEpisodesByAirdate($siteId, $videoId, (string) $parsed->airdate);
            $episodeId = $this->findLocalEpisode($provider, $videoId, $parsed);
            if ($episodeId > 0) {
                return $episodeId;
            }
        }

        // Last resort: the stored list may be stale or renumbered.
        if ($this->refreshEpisodeList($passable, $provider, $siteId, $videoId)) {
            return $this->findLocalEpisode($provider, $videoId, $parsed);
        }

        return 0;
    }

    private function findLocalEpisode(AbstractTvProvider $provider, int $videoId, ParsedTvRelease $parsed): int
    {
        return (int) $provider->getBySeasonEp($videoId, $parsed->season ?? -1, $parsed->episode ?? 0, (string) $parsed->airdate);
    }

    /**
     * Download the show's episode list unless it was refreshed within the refresh window.
     */
    private function refreshEpisodeList(TvProcessingPassable $passable, AbstractTvProvider $provider, int $siteId, int $videoId): bool
    {
        if ($passable->runState->wasEpisodeListRefreshed($this->getName(), $videoId)) {
            return false;
        }

        $provider->fetchAllEpisodes($siteId, $videoId);
        $passable->runState->markEpisodeListRefreshed($this->getName(), $videoId);

        return true;
    }

    /**
     * @param  array<string, mixed>  $show
     */
    private function storePoster(AbstractTvProvider $provider, int $videoId, array $show): void
    {
        try {
            if ($provider->hasCover($videoId)) {
                return;
            }

            $url = ($this->posterResolver ?? new TvPosterResolver)->resolve($show);
            if ($url !== '') {
                $provider->savePoster($videoId, $url);
            }
        } catch (\Throwable $e) {
            Log::info('TV poster download failed', ['video_id' => $videoId, 'error' => $e->getMessage()]);
        }
    }
}
