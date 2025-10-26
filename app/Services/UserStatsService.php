<?php

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
     * Get downloads per day for the last N days
     * Uses aggregated stats from user_activity_stats table for dates older than 2 days
     * Uses live data from user_downloads table for recent days
     */
    public function getDownloadsPerDay(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $twoDaysAgo = Carbon::now()->subDays(2)->startOfDay();

        $result = [];

        // For historical data (older than 2 days), use aggregated stats
        if ($days > 2) {
            $historicalStartDate = $startDate->format('Y-m-d');
            $historicalEndDate = $twoDaysAgo->copy()->subDay()->format('Y-m-d');

            $historicalStats = UserActivityStat::query()
                ->select('stat_date', 'downloads_count')
                ->where('stat_date', '>=', $historicalStartDate)
                ->where('stat_date', '<=', $historicalEndDate)
                ->orderBy('stat_date', 'asc')
                ->get()
                ->keyBy('stat_date');

            // Add historical data
            $currentDate = $startDate->copy();
            while ($currentDate->lt($twoDaysAgo)) {
                $dateStr = $currentDate->format('Y-m-d');
                $stat = $historicalStats->get($dateStr);
                $result[] = [
                    'date' => $currentDate->format('M d'),
                    'count' => $stat ? $stat->downloads_count : 0,
                ];
                $currentDate->addDay();
            }
        }

        // For recent data (last 2 days), use live data from user_downloads
        $downloads = UserDownload::query()
            ->select(DB::raw('DATE(timestamp) as date'), DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $twoDaysAgo)
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Add recent data
        $currentDate = $twoDaysAgo->copy();
        $now = Carbon::now();
        while ($currentDate->lte($now)) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $downloads->get($dateStr);
            $result[] = [
                'date' => $currentDate->format('M d'),
                'count' => $found ? $found->count : 0,
            ];
            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get downloads per hour for the last N hours
     * Uses live data from user_downloads table
     */
    public function getDownloadsPerHour(int $hours = 168): array
    {
        $startTime = Carbon::now()->subHours($hours - 1)->startOfHour();

        $downloads = UserDownload::query()
            ->select(
                DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->where('timestamp', '>=', $startTime)
            ->groupBy(DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00")'))
            ->orderBy('hour', 'asc')
            ->get()
            ->keyBy('hour');

        // Fill in missing hours with zero counts
        $result = [];
        for ($i = $hours - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subHours($i)->startOfHour();
            $hourKey = $time->format('Y-m-d H:00:00');
            $found = $downloads->get($hourKey);

            // Format label based on how recent the hour is
            $now = Carbon::now();
            if ($time->isToday()) {
                $label = $time->format('H:i');
            } elseif ($time->isYesterday()) {
                $label = 'Yesterday '.$time->format('H:i');
            } elseif ($time->diffInDays($now) < 7) {
                $label = $time->format('D H:i');
            } else {
                $label = $time->format('M d H:i');
            }

            $result[] = [
                'time' => $label,
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get downloads per minute for the last N minutes
     */
    public function getDownloadsPerMinute(int $minutes = 60): array
    {
        $startTime = Carbon::now()->subMinutes($minutes);

        $downloads = UserDownload::query()
            ->select(
                DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00") as minute'),
                DB::raw('COUNT(*) as count')
            )
            ->where('timestamp', '>=', $startTime)
            ->groupBy(DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00")'))
            ->orderBy('minute', 'asc')
            ->get();

        // Fill in missing minutes with zero counts
        $result = [];
        for ($i = $minutes - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subMinutes($i);
            $minuteKey = $time->format('Y-m-d H:i:00');
            $found = $downloads->firstWhere('minute', $minuteKey);
            $result[] = [
                'time' => $time->format('H:i'),
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get API hits per day for the last N days
     * Uses aggregated stats from user_activity_stats table for dates older than 2 days
     * Uses live data from user_requests table for recent days
     */
    public function getApiHitsPerDay(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        $twoDaysAgo = Carbon::now()->subDays(2)->startOfDay();

        $result = [];

        // For historical data (older than 2 days), use aggregated stats
        if ($days > 2) {
            $historicalStartDate = $startDate->format('Y-m-d');
            $historicalEndDate = $twoDaysAgo->copy()->subDay()->format('Y-m-d');

            $historicalStats = UserActivityStat::query()
                ->select('stat_date', 'api_hits_count')
                ->where('stat_date', '>=', $historicalStartDate)
                ->where('stat_date', '<=', $historicalEndDate)
                ->orderBy('stat_date', 'asc')
                ->get()
                ->keyBy('stat_date');

            // Add historical data
            $currentDate = $startDate->copy();
            while ($currentDate->lt($twoDaysAgo)) {
                $dateStr = $currentDate->format('Y-m-d');
                $stat = $historicalStats->get($dateStr);
                $result[] = [
                    'date' => $currentDate->format('M d'),
                    'count' => $stat ? $stat->api_hits_count : 0,
                ];
                $currentDate->addDay();
            }
        }

        // For recent data (last 2 days), use live data from user_requests
        $apiHits = UserRequest::query()
            ->select(DB::raw('DATE(timestamp) as date'), DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $twoDaysAgo)
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Add recent data
        $currentDate = $twoDaysAgo->copy();
        $now = Carbon::now();
        while ($currentDate->lte($now)) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = $apiHits->get($dateStr);
            $result[] = [
                'date' => $currentDate->format('M d'),
                'count' => $found ? $found->count : 0,
            ];
            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Get API hits per hour for the last N hours
     * Uses live data from user_requests table
     */
    public function getApiHitsPerHour(int $hours = 168): array
    {
        $startTime = Carbon::now()->subHours($hours - 1)->startOfHour();

        $apiHits = UserRequest::query()
            ->select(
                DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->where('timestamp', '>=', $startTime)
            ->groupBy(DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:00:00")'))
            ->orderBy('hour', 'asc')
            ->get()
            ->keyBy('hour');

        // Fill in missing hours with zero counts
        $result = [];
        for ($i = $hours - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subHours($i)->startOfHour();
            $hourKey = $time->format('Y-m-d H:00:00');
            $found = $apiHits->get($hourKey);

            // Format label based on how recent the hour is
            $now = Carbon::now();
            if ($time->isToday()) {
                $label = $time->format('H:i');
            } elseif ($time->isYesterday()) {
                $label = 'Yesterday '.$time->format('H:i');
            } elseif ($time->diffInDays($now) < 7) {
                $label = $time->format('D H:i');
            } else {
                $label = $time->format('M d H:i');
            }

            $result[] = [
                'time' => $label,
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get API hits per minute for the last N minutes
     */
    public function getApiHitsPerMinute(int $minutes = 60): array
    {
        $startTime = Carbon::now()->subMinutes($minutes);

        // Track actual API requests from user_requests table
        $apiHits = UserRequest::query()
            ->select(
                DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00") as minute'),
                DB::raw('COUNT(*) as count')
            )
            ->where('timestamp', '>=', $startTime)
            ->groupBy(DB::raw('DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:00")'))
            ->orderBy('minute', 'asc')
            ->get();

        // Fill in missing minutes with zero counts
        $result = [];
        for ($i = $minutes - 1; $i >= 0; $i--) {
            $time = Carbon::now()->subMinutes($i);
            $minuteKey = $time->format('Y-m-d H:i:00');
            $found = $apiHits->firstWhere('minute', $minuteKey);
            $result[] = [
                'time' => $time->format('H:i'),
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get summary statistics
     * Uses aggregated stats for weekly totals where possible
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::now()->startOfDay();
        $twoDaysAgo = Carbon::now()->subDays(2)->startOfDay();
        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay();

        // For weekly stats, combine aggregated historical data + live recent data
        $historicalDownloads = UserActivityStat::query()
            ->where('stat_date', '>=', $sevenDaysAgo->format('Y-m-d'))
            ->where('stat_date', '<', $twoDaysAgo->format('Y-m-d'))
            ->sum('downloads_count');

        $recentDownloads = UserDownload::query()
            ->where('timestamp', '>=', $twoDaysAgo)
            ->count();

        $historicalApiHits = UserActivityStat::query()
            ->where('stat_date', '>=', $sevenDaysAgo->format('Y-m-d'))
            ->where('stat_date', '<', $twoDaysAgo->format('Y-m-d'))
            ->sum('api_hits_count');

        $recentApiHits = UserRequest::query()
            ->where('timestamp', '>=', $twoDaysAgo)
            ->count();

        return [
            'total_users' => User::whereNull('deleted_at')->count(),
            'downloads_today' => UserDownload::where('timestamp', '>=', $today)->count(),
            'downloads_week' => $historicalDownloads + $recentDownloads,
            'api_hits_today' => UserRequest::query()->where('timestamp', '>=', $today)->count(),
            'api_hits_week' => $historicalApiHits + $recentApiHits,
        ];
    }

    /**
     * Get top downloaders
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
}
