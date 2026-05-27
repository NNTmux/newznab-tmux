<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\Release;
use App\Models\Settings;
use App\Services\Runners\PostProcessRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
    ): Builder {
        $query = Release::query()
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id');

        return self::applyPredicates($query, $groupID, $guidChar, $minSizeMB, $maxSizeGB);
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
        $bucketExpr = DB::getDriverName() === 'sqlite'
            ? 'substr(r.leftguid, 1, 1)'
            : 'LEFT(r.leftguid, 1)';
        $rows = self::baseBuilder()
            ->select(DB::raw('DISTINCT '.$bucketExpr.' AS id'))
            ->limit($effectiveLimit)
            ->get();
        $chars = [];
        foreach ($rows as $row) {
            $id = (string) ($row->id ?? '');
            if ($id !== '') {
                $chars[] = substr($id, 0, 1);
            }
        }

        return $chars;
    }

    /**
     * True when there is at least one candidate release anywhere (any char).
     * Used by the drain command to know when to stop looping.
     */
    public static function hasAnyCandidate(): bool
    {
        return self::baseBuilder()->limit(1)->exists();
    }
}
