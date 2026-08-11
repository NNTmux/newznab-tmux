<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\Release;
use App\Models\Settings;
use App\Services\Runners\PostProcessRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "needs additional postprocessing" release selection.
 *
 * Both the bucket-fanout query in
 * {@see PostProcessRunner::processAdditional()} and the
 * per-worker release fetch in
 * {@see AdditionalProcessingOrchestrator::fetchReleases()}
 * MUST go through this class so their predicates can never drift apart.
 *
 * History: the two queries were maintained independently, and a mismatch on
 * size filters / nzbstatus caused releases to be advertised by the bucket
 * query but rejected by the orchestrator, accumulating forever in the
 * "passwordstatus=-1, haspreview=-1" backlog.
 */
final class AdditionalCandidateQuery
{
    /** Default size lower bound when the setting is empty/unset (megabytes). */
    public const int DEFAULT_MIN_SIZE_MB = 1;

    /** Default size upper bound when the setting is empty/unset (gigabytes). */
    public const int DEFAULT_MAX_SIZE_GB = 100;

    /**
     * Hard cap on the bucket fan-out. `leftguid` is the first character of a
     * hex GUID, so there are at most 16 distinct values (0-9, a-f). There is
     * no reason to dispatch more buckets than that per scheduler cycle.
     * Per-cycle concurrency is governed by the existing `postthreads` setting
     * inside {@see PostProcessRunner::runPostProcess()},
     * so a separate setting is unnecessary.
     */
    public const int BUCKET_LIMIT = 16;

    public const string CLAIMED_AT_COLUMN = 'additional_pp_claimed_at';

    public const string CLAIM_TOKEN_COLUMN = 'additional_pp_claim_token';

    private static ?bool $supportsClaims = null;

    /**
     * Resolve the minimum-size filter (megabytes). Returns 0 when disabled.
     *
     * An explicit '0' setting means "no minimum size filter". An empty/null
     * setting falls back to {@see self::DEFAULT_MIN_SIZE_MB}.
     */
    public static function minSizeMB(): int
    {
        $value = Settings::settingValue('minsizetopostprocess');
        if ($value === '' || $value === null) {
            return self::DEFAULT_MIN_SIZE_MB;
        }

        return max(0, (int) $value);
    }

    /**
     * Resolve the maximum-size filter (gigabytes). Returns 0 when disabled.
     *
     * An explicit '0' setting means "no maximum size filter". An empty/null
     * setting falls back to {@see self::DEFAULT_MAX_SIZE_GB}.
     */
    public static function maxSizeGB(): int
    {
        $value = Settings::settingValue('maxsizetopostprocess');
        if ($value === '' || $value === null) {
            return self::DEFAULT_MAX_SIZE_GB;
        }

        return max(0, (int) $value);
    }

    /**
     * Apply the candidate-selection predicates to an Eloquent builder.
     *
     * The builder MUST already be aliased as `r` for releases and joined to
     * `categories as c`. Optional group / GUID-character constraints can be
     * applied on top.
     *
     * @param  Builder<Release>  $query
     * @return Builder<Release>
     */
    public static function applyPredicates(
        Builder $query,
        int|string $groupID = '',
        string $guidChar = '',
        ?int $minSizeMB = null,
        ?int $maxSizeGB = null,
        bool $includeClaimed = false,
    ): Builder {
        $min = $minSizeMB ?? self::minSizeMB();
        $max = $maxSizeGB ?? self::maxSizeGB();
        $query
            ->where('r.passwordstatus', -1)
            ->where('r.haspreview', -1)
            ->where('r.nzbstatus', 1)
            ->where('c.disablepreview', 0);
        if ($min > 0) {
            $query->where('r.size', '>', $min * 1048576);
        }
        if ($max > 0) {
            $query->where('r.size', '<', $max * 1073741824);
        }
        if ($groupID !== '' && $groupID !== 0 && $groupID !== '0') {
            $query->where('r.groups_id', $groupID);
        }
        if ($guidChar !== '') {
            $query->where('r.leftguid', $guidChar);
        }
        if (! $includeClaimed) {
            self::applyClaimWindow($query);
        }

        return $query;
    }

    /**
     * Return a fresh Eloquent builder, joined and predicate-applied, ready for
     * the orchestrator to add selects / order / limit.
     *
     * @return Builder<Release>
     */
    public static function baseBuilder(
        int|string $groupID = '',
        string $guidChar = '',
        ?int $minSizeMB = null,
        ?int $maxSizeGB = null,
        bool $includeClaimed = false,
    ): Builder {
        $query = Release::query()
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id');

        return self::applyPredicates($query, $groupID, $guidChar, $minSizeMB, $maxSizeGB, $includeClaimed);
    }

    /**
     * Return up to {@see self::BUCKET_LIMIT} distinct GUID first-characters
     * that have at least one release matching the candidate predicates.
     *
     * The fan-out is capped at 16 because `leftguid` is a single hex digit.
     * Worker concurrency is then capped further by the `postthreads` setting
     * in {@see PostProcessRunner::runPostProcess()}.
     *
     * @return array<int, string>
     */
    public static function bucketChars(?int $limit = null): array
    {
        $effectiveLimit = $limit !== null && $limit > 0
            ? min($limit, self::BUCKET_LIMIT)
            : self::BUCKET_LIMIT;

        return array_slice(
            array_column(self::availableBucketCounts(), 'bucket'),
            0,
            $effectiveLimit,
        );
    }

    /**
     * Return available candidate counts keyed by GUID bucket.
     *
     * @return list<array{bucket: string, count: int}>
     */
    public static function availableBucketCounts(): array
    {
        $counts = [];

        foreach (self::bucketBacklog() as $backlog) {
            if ($backlog['available'] > 0) {
                $counts[] = [
                    'bucket' => $backlog['bucket'],
                    'count' => $backlog['available'],
                ];
            }
        }

        return $counts;
    }

    /**
     * Return total and currently claimable candidates for every GUID bucket.
     *
     * @return list<array{bucket: string, total: int, available: int}>
     */
    public static function bucketBacklog(): array
    {
        $query = self::baseBuilder(includeClaimed: true)
            ->select('r.leftguid')
            ->selectRaw('COUNT(*) AS total_count')
            ->groupBy('r.leftguid')
            ->orderBy('r.leftguid');

        if (self::supportsClaims()) {
            $query->selectRaw(
                'SUM(CASE WHEN r.'.self::CLAIMED_AT_COLUMN.' IS NULL OR r.'.self::CLAIMED_AT_COLUMN.' < ? THEN 1 ELSE 0 END) AS available_count',
                [self::claimStaleBefore()],
            );
        } else {
            $query->selectRaw('COUNT(*) AS available_count');
        }

        $backlog = [];
        foreach ($query->get() as $row) {
            $bucket = strtolower(substr((string) ($row->leftguid ?? ''), 0, 1));
            if ($bucket === '') {
                continue;
            }

            $backlog[] = [
                'bucket' => $bucket,
                'total' => (int) ($row->total_count ?? 0),
                'available' => (int) ($row->available_count ?? 0),
            ];
        }

        return $backlog;
    }

    /**
     * @return array{total: int, available: int}
     */
    public static function backlogCounts(): array
    {
        $query = self::baseBuilder(includeClaimed: true)
            ->selectRaw('COUNT(*) AS total_count');

        if (self::supportsClaims()) {
            $query->selectRaw(
                'SUM(CASE WHEN r.'.self::CLAIMED_AT_COLUMN.' IS NULL OR r.'.self::CLAIMED_AT_COLUMN.' < ? THEN 1 ELSE 0 END) AS available_count',
                [self::claimStaleBefore()],
            );
        } else {
            $query->selectRaw('COUNT(*) AS available_count');
        }

        /** @var object{total_count: int|string|null, available_count: int|string|null}|null $counts */
        $counts = $query->toBase()->first();

        return [
            'total' => (int) ($counts->total_count ?? 0),
            'available' => (int) ($counts->available_count ?? 0),
        ];
    }

    /**
     * True when there is at least one candidate release anywhere (any char).
     * Used by the drain command to know when to stop looping.
     */
    public static function hasAnyCandidate(): bool
    {
        return self::baseBuilder()->limit(1)->exists();
    }

    /**
     * Claim a bounded batch of release rows for one worker.
     *
     * @param  list<string>  $columns
     * @param  list<int>  $excludedReleaseIds
     * @return EloquentCollection<int, Release>
     */
    public static function claimBatch(
        string $guidChar,
        int $limit,
        string $token,
        int|string $groupID = '',
        ?int $minSizeMB = null,
        ?int $maxSizeGB = null,
        array $columns = ['*'],
        array $excludedReleaseIds = [],
    ): EloquentCollection {
        $effectiveLimit = max(1, $limit);

        return DB::transaction(function () use ($guidChar, $effectiveLimit, $token, $groupID, $minSizeMB, $maxSizeGB, $columns, $excludedReleaseIds): EloquentCollection {
            $supportsClaims = self::supportsClaims();
            $query = self::baseBuilder($groupID, $guidChar, $minSizeMB, $maxSizeGB)
                ->select('r.id')
                ->orderByDesc('r.postdate')
                ->orderBy('r.id')
                ->limit($effectiveLimit);

            if ($excludedReleaseIds !== []) {
                $query->whereNotIn('r.id', $excludedReleaseIds);
            }

            if (DB::getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $ids = $query
                ->pluck('r.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids === []) {
                return (new Release)->newCollection();
            }

            if ($supportsClaims) {
                Release::query()
                    ->whereIn('id', $ids)
                    ->update([
                        self::CLAIMED_AT_COLUMN => now(),
                        self::CLAIM_TOKEN_COLUMN => $token,
                    ]);
            }

            return Release::query()
                ->whereIn('id', $ids)
                ->select(self::selectableColumns($columns, $supportsClaims))
                ->orderByRaw(self::idOrderExpression($ids))
                ->get();
        }, 3);
    }

    public static function clearClaim(int $releaseId, ?string $token = null): void
    {
        if (! self::supportsClaims()) {
            return;
        }

        $query = Release::query()->where('id', $releaseId);
        if ($token !== null && $token !== '') {
            $query->where(self::CLAIM_TOKEN_COLUMN, $token);
        }

        $query->update([
            self::CLAIMED_AT_COLUMN => null,
            self::CLAIM_TOKEN_COLUMN => null,
        ]);
    }

    /**
     * @return array<string, null>
     */
    public static function claimResetValues(): array
    {
        if (! self::supportsClaims()) {
            return [];
        }

        return [
            self::CLAIMED_AT_COLUMN => null,
            self::CLAIM_TOKEN_COLUMN => null,
        ];
    }

    public static function supportsClaims(): bool
    {
        if (self::$supportsClaims !== null) {
            return self::$supportsClaims;
        }

        if (! Schema::hasTable('releases')) {
            return self::$supportsClaims = false;
        }

        return self::$supportsClaims = Schema::hasColumn('releases', self::CLAIMED_AT_COLUMN)
            && Schema::hasColumn('releases', self::CLAIM_TOKEN_COLUMN);
    }

    /**
     * @param  Builder<Release>  $query
     */
    private static function applyClaimWindow(Builder $query): void
    {
        if (! self::supportsClaims()) {
            return;
        }

        $staleBefore = self::claimStaleBefore();

        $query->where(function (Builder $claimQuery) use ($staleBefore): void {
            $claimQuery
                ->whereNull('r.'.self::CLAIMED_AT_COLUMN)
                ->orWhere('r.'.self::CLAIMED_AT_COLUMN, '<', $staleBefore);
        });
    }

    public static function claimTtlSeconds(): int
    {
        $timeout = (int) (Settings::settingValue('releaseprocessingtimeout') ?: 120);

        return max(300, $timeout * 2);
    }

    public static function claimStaleBefore(): Carbon
    {
        return now()->subSeconds(self::claimTtlSeconds());
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private static function selectableColumns(array $columns, bool $supportsClaims): array
    {
        if ($supportsClaims || $columns === ['*']) {
            return $columns;
        }

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => ! in_array($column, [self::CLAIMED_AT_COLUMN, self::CLAIM_TOKEN_COLUMN], true),
        ));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function idOrderExpression(array $ids): string
    {
        if (DB::getDriverName() !== 'sqlite') {
            return 'FIELD(id, '.implode(',', $ids).')';
        }

        $cases = [];
        foreach ($ids as $position => $id) {
            $cases[] = 'WHEN '.(int) $id.' THEN '.(int) $position;
        }

        return 'CASE id '.implode(' ', $cases).' ELSE '.count($ids).' END';
    }
}
