<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;

final class UserInactivityEvaluator
{
    public function shouldPurge(
        ?CarbonInterface $createdAt,
        ?CarbonInterface $updatedAt,
        ?CarbonInterface $lastLoginAt,
        ?CarbonInterface $apiAccessAt,
        ?CarbonInterface $lastDownloadAt,
        int $grabs,
        CarbonInterface $threshold
    ): bool {
        return $this->isActivityStale($lastLoginAt, $createdAt, $threshold)
            && $this->isActivityStale($apiAccessAt, $createdAt, $threshold)
            && $this->isDownloadActivityStale($createdAt, $updatedAt, $lastDownloadAt, $grabs, $threshold);
    }

    private function isActivityStale(
        ?CarbonInterface $activityAt,
        ?CarbonInterface $fallbackAt,
        CarbonInterface $threshold
    ): bool {
        $comparisonDate = $activityAt ?? $fallbackAt;

        return $comparisonDate !== null && $comparisonDate->lt($threshold);
    }

    private function isDownloadActivityStale(
        ?CarbonInterface $createdAt,
        ?CarbonInterface $updatedAt,
        ?CarbonInterface $lastDownloadAt,
        int $grabs,
        CarbonInterface $threshold
    ): bool {
        if ($lastDownloadAt !== null) {
            return $lastDownloadAt->lt($threshold);
        }

        if ($grabs > 0) {
            return $updatedAt !== null && $updatedAt->lt($threshold);
        }

        return $createdAt !== null && $createdAt->lt($threshold);
    }
}
