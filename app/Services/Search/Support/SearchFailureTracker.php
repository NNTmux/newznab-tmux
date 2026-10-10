<?php

declare(strict_types=1);

namespace App\Services\Search\Support;

/**
 * Counts search-engine query failures within the current request or job.
 *
 * Drivers swallow query errors and return empty results so pages keep
 * rendering; this lets callers that cache results (the API row cache) tell a
 * genuine empty result from a failed query. Bound as a scoped singleton.
 */
final class SearchFailureTracker
{
    private int $failures = 0;

    public function record(): void
    {
        $this->failures++;
    }

    public function count(): int
    {
        return $this->failures;
    }
}
