<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Services\XrefService;
use Illuminate\Support\Facades\DB;

/**
 * Decides which group's worker stores a cross-posted article.
 *
 * Collections are keyed by subject, not group, so every group an article is
 * posted to would otherwise write the same collection, binary and part rows
 * at the same time, deadlocking on the cascade foreign keys. The Xref header
 * is identical in every group, so all workers agree on one owner: the first
 * group in the Xref that is active and whose own forward scan covers the
 * article (its number there is at or after that group's first_record).
 *
 * Forward updates scan contiguously from last_record, so the owner always
 * reaches the article. first_record only moves lower (backfill), so a stale
 * snapshot under-reports coverage and errs toward keeping the article.
 */
final class CrossPostOwnership
{
    private readonly XrefService $xrefService;

    /**
     * @param  array<string, int>|null  $firstRecordsByGroup  active group name => first_record; loaded on first use when null
     */
    public function __construct(private ?array $firstRecordsByGroup = null, ?XrefService $xrefService = null)
    {
        $this->xrefService = $xrefService ?? new XrefService;
    }

    /**
     * Whether the worker scanning $groupName should store this header.
     *
     * @param  array<string, mixed>  $header
     */
    public function isOwnedBy(array $header, string $groupName): bool
    {
        $numbers = $this->xrefService->extractGroupArticleNumbers((string) ($header['Xref'] ?? ''));
        if (\count($numbers) < 2) {
            return true;
        }

        $firstRecords = $this->firstRecords();
        foreach ($numbers as $group => $number) {
            $firstRecord = $firstRecords[$group] ?? 0;
            if ($firstRecord > 0 && $number >= $firstRecord) {
                return $group === $groupName;
            }
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function firstRecords(): array
    {
        return $this->firstRecordsByGroup ??= DB::table('usenet_groups')
            ->where('active', 1)
            ->pluck('first_record', 'name')
            ->map(static fn ($firstRecord): int => (int) $firstRecord)
            ->all();
    }
}
