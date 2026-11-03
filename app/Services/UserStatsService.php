<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityStat;
use App\Models\UserDownload;
use App\Models\UserRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserStatsService
{
    /**
     * Get user statistics by role
     *
     * @return array<int, array{role: string, count: int}>
     */
    public function getUsersByRole(): array
    {
        $usersByRole = User::query()
            ->join('roles', 'users.roles_id', '=', 'roles.id')
            ->select('roles.name as role_name', DB::raw('COUNT(users.id) as count'))
            ->whereNull('users.deleted_at')
            ->groupBy('roles.id', 'roles.name')
            ->get();

        return $usersByRole->map(function ($item) {
            return [
                'role' => $item->role_name,
                'count' => $item->count,
            ];
        })->toArray();
    }

    /**
     * Get downloads per day for the last N days (inclusive of today).
     *
     * Closed hours come from `user_activity_stats_hourly`; the current hour
     * comes from the live `user_downloads` table.
     *
     * @return list<array<string, int|string>>
     */
    public function getDownloadsPerDay(int $days = 7): array
    {
        return $this->buildPerDaySeries(
            $days,
            'downloads_count',
            static fn (Carbon $from): int => UserDownload::query()
                ->where('timestamp', '>=', $from)
                ->count()
        );
    }

    /**
     * Get downloads per hour for the last N hours
     * Uses aggregated hourly stats from user_activity_stats_hourly table
     *
     * @return list<array<string, int|string|null>>
     */
    public function getDownloadsPerHour(int $hours = 168): array
    {
        $result = UserActivityStat::getDownloadsPerHour($hours);

        if ($result !== []) {
            $result[count($result) - 1]['count'] = UserDownload::query()
                ->where('timestamp', '>=', Carbon::now()->startOfHour())
                ->count();
        }

        return $result;
    }

    /**
     * Get downloads per minute for the last N minutes
     *
     * @return array<string, mixed>
     */
    public function getDownloadsPerMinute(int $minutes = 60): array
    {
        $startTime = Carbon::now()->subMinutes($minutes);
        $minuteExpression = $this->minuteBucketExpression();

        $downloads = UserDownload::query()
            ->selectRaw($minuteExpression.' as minute, COUNT(*) as count')
            ->where('timestamp', '>=', $startTime)
            ->groupByRaw($minuteExpression)
            ->orderBy('minute', 'asc')
            ->get()
            ->keyBy('minute');

        // Fill in missing minutes with zero counts (O(n) instead of firstWhere O(n^2))
        $result = [];
        for ($i = $minutes - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subMinutes($i);
            $minuteKey = $time->format('Y-m-d H:i:00');
            $found = $downloads->get($minuteKey);
            $result[] = [
                'time' => $time->format('H:i'),
                'count' => $found ? (int) $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get API hits per day for the last N days (inclusive of today).
     *
     * Mirrors {@see self::getDownloadsPerDay()} using combined authenticated
     * API v1/v2 and RSS requests from `user_requests`.
     *
     * @return list<array<string, int|string>>
     */
    public function getApiHitsPerDay(int $days = 7): array
    {
        return $this->buildPerDaySeries(
            $days,
            'api_hits_count',
            static fn (Carbon $from): int => UserRequest::query()
                ->where('timestamp', '>=', $from)
                ->count()
        );
    }

    /**
     * Get API hits per hour for the last N hours
     * Uses aggregated hourly stats from user_activity_stats_hourly table
     *
     * @return list<array<string, int|string|null>>
     */
    public function getApiHitsPerHour(int $hours = 168): array
    {
        $result = UserActivityStat::getApiHitsPerHour($hours);

        if ($result !== []) {
            $result[count($result) - 1]['count'] = UserRequest::query()
                ->where('timestamp', '>=', Carbon::now()->startOfHour())
                ->count();
        }

        return $result;
    }

    /**
     * Get API hits per minute for the last N minutes
     *
     * @return array<string, mixed>
     */
    public function getApiHitsPerMinute(int $minutes = 60): array
    {
        $startTime = Carbon::now()->subMinutes($minutes);
        $minuteExpression = $this->minuteBucketExpression();

        // Track actual API requests from user_requests table
        $apiHits = UserRequest::query()
            ->selectRaw($minuteExpression.' as minute, COUNT(*) as count')
            ->where('timestamp', '>=', $startTime)
            ->groupByRaw($minuteExpression)
            ->orderBy('minute', 'asc')
            ->get()
            ->keyBy('minute');

        // Fill in missing minutes with zero counts (O(n) instead of firstWhere O(n^2))
        $result = [];
        for ($i = $minutes - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subMinutes($i);
            $minuteKey = $time->format('Y-m-d H:i:00');
            $found = $apiHits->get($minuteKey);
            $result[] = [
                'time' => $time->format('H:i'),
                'count' => $found ? (int) $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get summary statistics for the headline tiles.
     *
     * Window: "today" and "(7d) = last 7 calendar days inclusive of today".
     *
     * Closed hours come from `user_activity_stats_hourly`; the current
     * in-progress hour comes from live `user_downloads` / `user_requests`.
     *
     * @return array{total_users: int, downloads_today: int, downloads_week: int, api_hits_today: int, api_hits_week: int}
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::now()->startOfDay();
        $weekStart = Carbon::now()->subDays(6)->startOfDay();
        $currentHourStart = Carbon::now()->startOfHour();

        $weekClosed = DB::table('user_activity_stats_hourly')
            ->where('stat_hour', '>=', $weekStart->format('Y-m-d H:00:00'))
            ->where('stat_hour', '<', $currentHourStart->format('Y-m-d H:00:00'))
            ->selectRaw('COALESCE(SUM(downloads_count), 0) as downloads, COALESCE(SUM(api_hits_count), 0) as api_hits')
            ->first();

        // Today: closed hours from hourly aggregate.
        $todayClosed = DB::table('user_activity_stats_hourly')
            ->where('stat_hour', '>=', $today->format('Y-m-d H:00:00'))
            ->where('stat_hour', '<', $currentHourStart->format('Y-m-d H:00:00'))
            ->selectRaw('COALESCE(SUM(downloads_count), 0) as downloads, COALESCE(SUM(api_hits_count), 0) as api_hits')
            ->first();

        // Current in-progress hour from live tables.
        $downloadsCurrentHour = UserDownload::query()
            ->where('timestamp', '>=', $currentHourStart)
            ->count();
        $apiHitsCurrentHour = UserRequest::query()
            ->where('timestamp', '>=', $currentHourStart)
            ->count();

        $downloadsToday = (int) ($todayClosed->downloads ?? 0) + $downloadsCurrentHour;
        $apiHitsToday = (int) ($todayClosed->api_hits ?? 0) + $apiHitsCurrentHour;

        return [
            'total_users' => User::whereNull('deleted_at')->count(),
            'downloads_today' => $downloadsToday,
            'downloads_week' => (int) ($weekClosed->downloads ?? 0) + $downloadsCurrentHour,
            'api_hits_today' => $apiHitsToday,
            'api_hits_week' => (int) ($weekClosed->api_hits ?? 0) + $apiHitsCurrentHour,
        ];
    }

    /**
     * Get top downloaders
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopDownloaders(int $limit = 5): array
    {
        $weekAgo = Carbon::now()->subDays(7);

        return UserDownload::query()
            ->join('users', 'user_downloads.users_id', '=', 'users.id')
            ->select('users.username', DB::raw('COUNT(*) as download_count'))
            ->where('user_downloads.timestamp', '>=', $weekAgo)
            ->groupBy('users.id', 'users.username')
            ->orderByDesc('download_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Build a "last N days inclusive of today" series from hourly aggregates
     * plus the current in-progress hour from a caller-supplied live counter.
     *
     * @param  callable(Carbon): int  $liveCurrentHourCounter
     * @return list<array{date: string, count: int}>
     */
    private function buildPerDaySeries(int $days, string $statColumn, callable $liveCurrentHourCounter): array
    {
        $today = Carbon::now()->startOfDay();
        $startDate = $today->copy()->subDays($days - 1);

        $currentHourStart = Carbon::now()->startOfHour();
        $hourly = DB::table('user_activity_stats_hourly')
            ->select('stat_hour', $statColumn)
            ->where('stat_hour', '>=', $startDate->format('Y-m-d H:00:00'))
            ->where('stat_hour', '<', $currentHourStart->format('Y-m-d H:00:00'))
            ->orderBy('stat_hour')
            ->get();

        $dailyCounts = [];
        foreach ($hourly as $stat) {
            $date = Carbon::parse((string) $stat->stat_hour)->format('Y-m-d');
            $dailyCounts[$date] = ($dailyCounts[$date] ?? 0) + (int) $stat->{$statColumn};
        }

        $result = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($today)) {
            $date = $cursor->format('Y-m-d');
            $result[] = [
                'date' => $cursor->format('M d'),
                'count' => $dailyCounts[$date] ?? 0,
            ];
            $cursor->addDay();
        }

        if ($result !== []) {
            $result[count($result) - 1]['count'] += (int) $liveCurrentHourCounter($currentHourStart);
        }

        return $result;
    }

    private function minuteBucketExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:%M:00', timestamp)"
            : 'DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00")';
    }
}
