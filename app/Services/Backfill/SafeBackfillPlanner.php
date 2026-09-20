<?php

declare(strict_types=1);

namespace App\Services\Backfill;

final class SafeBackfillPlanner
{
    /**
     * @param  iterable<object|array<string, mixed>>  $candidates
     * @return list<array{name: string, articles: int, target_days: int}>
     */
    public function plan(
        iterable $candidates,
        int $articleLimit,
        int $groupLimit,
        int $targetMode,
        int $globalTargetDays,
    ): array {
        $articleLimit = max(1, min(1_000_000, $articleLimit));
        $groupLimit = max(1, min(16, $groupLimit));
        $planned = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            $row = is_array($candidate) ? $candidate : (array) $candidate;
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '' || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $available = (int) ($row['our_first'] ?? 0) - (int) ($row['their_first'] ?? 0);
            if ($available <= 0) {
                continue;
            }

            $targetDays = match ($targetMode) {
                1 => max(0, (int) ($row['backfill_target'] ?? 0)),
                2 => max(0, $globalTargetDays),
                default => 0,
            };

            $planned[] = [
                'name' => $name,
                'articles' => min($articleLimit, $available),
                'target_days' => $targetDays,
            ];

            if (count($planned) >= $groupLimit) {
                break;
            }
        }

        return $planned;
    }

    public function targetPost(int $currentFirst, int $articleLimit, int $serverOldest, int $dateTarget = 0): int
    {
        return max(
            $currentFirst - max(1, $articleLimit),
            $serverOldest,
            $dateTarget,
        );
    }

    /**
     * @return list<array{first: int, last: int}>
     */
    public function ranges(int $currentFirst, int $targetPost, int $maxMessages): array
    {
        if ($targetPost >= $currentFirst) {
            return [];
        }

        $maxMessages = max(1, $maxMessages);
        $ranges = [];
        $last = $currentFirst - 1;

        while ($last >= $targetPost) {
            $first = max($targetPost, $last - $maxMessages + 1);
            $ranges[] = ['first' => $first, 'last' => $last];
            $last = $first - 1;
        }

        return $ranges;
    }
}
