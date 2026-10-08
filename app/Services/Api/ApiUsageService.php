<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Jobs\UpdateUserApiAccess;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ApiUsageService
{
    /**
     * Only these filter parameters are written to `user_requests.request`;
     * credentials and unknown fields never are (the column ends up in GDPR exports).
     */
    private const AUDITED_PARAMETERS = [
        't', 'q', 'id', 'cat', 'imdbid', 'tmdbid', 'traktid', 'vid', 'tvdbid', 'rid', 'tvmazeid',
        'anidbid', 'anilistid', 'season', 'ep', 'group', 'limit', 'offset', 'maxage', 'minsize',
        'sort', 'o', 'extended', 'num', 'dl', 'del',
    ];

    private const AUDIT_VALUE_MAX_LENGTH = 64;

    /** Matches the `user_requests.request` varchar(255) column. */
    private const AUDIT_MAX_LENGTH = 255;

    private ApiInputCanonicalizer $inputCanonicalizer;

    public function __construct(?ApiInputCanonicalizer $inputCanonicalizer = null)
    {
        $this->inputCanonicalizer = $inputCanonicalizer ?? new ApiInputCanonicalizer;
    }

    public function statistics(int $userId): object
    {
        return Cache::remember('api_user_stats:'.$userId, 60, function () use ($userId): object {
            $oneDayAgo = now()->subDay()->toDateTimeString();

            return DB::selectOne('SELECT
                (SELECT COUNT(*) FROM user_requests WHERE users_id = ? AND timestamp > ?) as api_count,
                (SELECT COUNT(*) FROM user_downloads WHERE users_id = ? AND timestamp > ?) as grab_count,
                (SELECT MIN(timestamp) FROM user_requests WHERE users_id = ? AND timestamp > ?) as api_time,
                (SELECT MIN(timestamp) FROM user_downloads WHERE users_id = ? AND timestamp > ?) as grab_time',
                [$userId, $oneDayAgo, $userId, $oneDayAgo, $userId, $oneDayAgo, $userId, $oneDayAgo]
            );
        });
    }

    public function record(User $user, Request $request): void
    {
        $occurredAt = now()->toDateTimeString();

        // Request accounting drives quotas and admin statistics, so it must be
        // durable before the response returns even when no queue worker runs.
        UserRequest::recordApiRequest(
            $user->id,
            $this->auditEntry($request),
            $occurredAt,
        );

        $job = new UpdateUserApiAccess(
            $user->id,
            $request->ip(),
            $occurredAt,
        );

        if ((bool) config('nntmux.api.async_audit', true)) {
            try {
                dispatch($job);
            } catch (Throwable) {
                // Keep access metadata current when the queue backend is unavailable.
                $job->handle();
            }

            return;
        }

        $job->handle();
    }

    /**
     * Build the redacted audit entry from the path and allowlisted filters of
     * the effective input (URL and body), e.g. `/api/v2/search?cat=5030&id=test`.
     */
    public function auditEntry(Request $request): string
    {
        $parameters = $this->inputCanonicalizer->canonicalize(Arr::only($request->input(), self::AUDITED_PARAMETERS));

        $audited = [];
        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                $audited[$key] = mb_substr($value, 0, self::AUDIT_VALUE_MAX_LENGTH);
            } elseif ($value === null) {
                // Empty parameters (`?id=`) stay visible; http_build_query would drop null.
                $audited[$key] = '';
            }
            // Legacy array values (e.g. `o[]=json`) are skipped.
        }

        $entry = '/'.ltrim($request->path(), '/');
        if ($audited !== []) {
            $entry .= '?'.http_build_query($audited, '', '&', PHP_QUERY_RFC3986);
        }

        return mb_substr($entry, 0, self::AUDIT_MAX_LENGTH);
    }
}
