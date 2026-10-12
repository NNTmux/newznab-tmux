<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lookup states stored in releases.tv_episodes_id while no episode is linked.
 *
 * Positive values are tv_episodes ids. The values deliberately avoid the legacy
 * -1..-4 / -6 provider hand-off codes so `tv:repair-links --legacy` can tell them apart.
 */
enum TvLookupStatus: int
{
    /** Waiting for the bucket workers (transient failures also wait here until tv_lookup_retry_at). */
    case Pending = 0;

    /** Every provider confirmed it has no matching show. */
    case ShowNotFound = -10;

    /** The show is linked but the episode is not available yet; retried on a schedule. */
    case EpisodeMissing = -11;

    /** Full-season release linked to its show by design. */
    case SeasonPack = -12;

    /** The release name could not be parsed into show and episode information. */
    case ParseFailed = -100;

    /** Legacy statuses that meant "waiting for the next provider". */
    public const array LEGACY_PENDING = [-1, -2, -3];

    /** Legacy statuses that meant "every provider gave up". */
    public const array LEGACY_EXHAUSTED = [-4, -6];
}
