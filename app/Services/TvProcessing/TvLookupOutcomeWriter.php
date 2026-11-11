<?php

declare(strict_types=1);

namespace App\Services\TvProcessing;

use App\Enums\TvLookupStatus;
use App\Models\Release;
use App\Services\Releases\ReleaseBrowseService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The single place that writes a TV lookup outcome back to its release.
 *
 * Writes only when the searchname is unchanged, so a rename during processing isn't
 * overwritten. A changed show or episode link is saved through Eloquent so
 * ReleaseObserver reindexes the release; lookup-state-only changes skip the reindex,
 * because the search index treats every tv_episodes_id <= 0 the same.
 */
class TvLookupOutcomeWriter
{
    /** While a batch runs, the browse cache is bumped once at its end instead of per match. */
    private bool $batching = false;

    private bool $cacheBumpPending = false;

    public function beginBatch(): void
    {
        $this->batching = true;
    }

    /** Ends a batch and applies the browse cache bump it deferred. */
    public function endBatch(): void
    {
        $this->batching = false;
        if ($this->cacheBumpPending) {
            $this->cacheBumpPending = false;
            $this->bumpBrowseCache();
        }
    }

    /** TV links arrive continuously, so their cache bumps are rate limited. */
    private function bumpBrowseCache(): void
    {
        ReleaseBrowseService::bumpCacheVersion(max(0, (int) config('nntmux.api.release_cache_tv_bump_interval', 60)));
    }

    public function record(TvReleaseContext $context, TvProcessingResult $result): void
    {
        $linked = DB::transaction(function () use ($context, $result): bool {
            /** @var Release|null $release */
            $release = Release::query()
                ->whereKey($context->releaseId)
                ->where('searchname', $context->searchName)
                ->lockForUpdate()
                ->first(['id', 'searchname', 'videos_id', 'tv_episodes_id', 'tv_lookup_attempts', 'tv_lookup_retry_at']);

            if ($release === null) {
                return false;
            }

            $columns = $this->columnsFor($release, $result);
            $linkChanged = $columns['videos_id'] !== (int) $release->videos_id
                || ($columns['tv_episodes_id'] !== (int) $release->tv_episodes_id
                    && max($columns['tv_episodes_id'], (int) $release->tv_episodes_id) > 0);

            if ($linkChanged) {
                $release->forceFill($columns)->save();
            } else {
                Release::query()->whereKey($release->id)->update($columns);
            }

            return $linkChanged;
        });

        if ($linked && \in_array($result->status, [TvProcessingResult::STATUS_MATCHED, TvProcessingResult::STATUS_SEASON_PACK], true)) {
            if ($this->batching) {
                $this->cacheBumpPending = true;
            } else {
                $this->bumpBrowseCache();
            }
        }
    }

    /**
     * @return array{videos_id: int, tv_episodes_id: int, tv_lookup_attempts: int, tv_lookup_retry_at: CarbonInterface|null}
     */
    private function columnsFor(Release $release, TvProcessingResult $result): array
    {
        $previousStatus = (int) $release->tv_episodes_id;
        $previousAttempts = (int) $release->tv_lookup_attempts;

        switch ($result->status) {
            case TvProcessingResult::STATUS_MATCHED:
                return $this->columns((int) $result->videoId, (int) $result->episodeId);

            case TvProcessingResult::STATUS_SEASON_PACK:
                return $this->columns((int) $result->videoId, TvLookupStatus::SeasonPack->value);

            case TvProcessingResult::STATUS_EPISODE_MISSING:
                $attempts = $previousStatus === TvLookupStatus::EpisodeMissing->value ? $previousAttempts + 1 : 1;
                $retryAt = null;
                if ($result->transient) {
                    // A provider outage says nothing about the episode: it never spends the
                    // last attempt, and backs off like any other transient failure.
                    $attempts = min($attempts, TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS - 1);
                    $retryAt = now()->addSeconds(max(
                        $result->retryAfter ?? 0,
                        TvProcessingCandidateQuery::KNOWN_SHOW_TRANSIENT_DELAY,
                        TvProcessingCandidateQuery::transientDelay($attempts),
                    ));
                } elseif ($attempts < TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS) {
                    $retryAt = now()->addSeconds(TvProcessingCandidateQuery::episodeDelay($attempts));
                }

                return $this->columns((int) $result->videoId, TvLookupStatus::EpisodeMissing->value, $attempts, $retryAt);

            case TvProcessingResult::STATUS_TRANSIENT:
                $attempts = $previousStatus === TvLookupStatus::Pending->value ? $previousAttempts + 1 : 1;
                if ($attempts >= TvProcessingCandidateQuery::MAX_TRANSIENT_ATTEMPTS) {
                    return $this->columns(0, TvLookupStatus::ShowNotFound->value);
                }

                return $this->columns(
                    0,
                    TvLookupStatus::Pending->value,
                    $attempts,
                    now()->addSeconds(TvProcessingCandidateQuery::transientDelay($attempts, $result->retryAfter ?? 0)),
                );

            case TvProcessingResult::STATUS_PARSE_FAILED:
                return $this->columns(0, TvLookupStatus::ParseFailed->value);

            case TvProcessingResult::STATUS_NOT_FOUND:
                return $this->columns(0, TvLookupStatus::ShowNotFound->value);

            default:
                // Skipped or pending: nothing was decided, so look again later rather than
                // recording a miss that is never retried.
                return $this->columns(0, TvLookupStatus::Pending->value, 0, now()->addSeconds(TvProcessingCandidateQuery::transientDelay(1)));
        }
    }

    /**
     * @return array{videos_id: int, tv_episodes_id: int, tv_lookup_attempts: int, tv_lookup_retry_at: CarbonInterface|null}
     */
    private function columns(int $videoId, int $episodeStatus, int $attempts = 0, ?CarbonInterface $retryAt = null): array
    {
        return [
            'videos_id' => $videoId,
            'tv_episodes_id' => $episodeStatus,
            'tv_lookup_attempts' => $attempts,
            'tv_lookup_retry_at' => $retryAt,
        ];
    }
}
