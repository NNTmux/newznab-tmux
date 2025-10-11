<?php

namespace App\Services;

use App\Models\User;
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
     * Get downloads per day for the last 7 days
     */
    public function getDownloadsPerDay(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        $downloads = UserDownload::query()
            ->select(DB::raw('DATE(timestamp) as date'), DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $startDate)
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy('date', 'asc')
            ->get();

        // Fill in missing days with zero counts
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $found = $downloads->firstWhere('date', $date);
            $result[] = [
                'date' => Carbon::parse($date)->format('M d'),
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get API hits per day for the last 7 days
     * Note: This tracks actual API requests from user_requests table
     */
    public function getApiHitsPerDay(int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        // Track actual API requests from user_requests table
        $apiHits = UserRequest::query()
            ->select(DB::raw('DATE(timestamp) as date'), DB::raw('COUNT(*) as count'))
            ->where('timestamp', '>=', $startDate)
            ->groupBy(DB::raw('DATE(timestamp)'))
            ->orderBy('date', 'asc')
            ->get();

        // Fill in missing days with zero counts
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $found = $apiHits->firstWhere('date', $date);
            $result[] = [
                'date' => Carbon::parse($date)->format('M d'),
                'count' => $found ? $found->count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get summary statistics
     */
    public function getSummaryStats(): array
    {
        $today = Carbon::now()->startOfDay();

        return [
            'total_users' => User::whereNull('deleted_at')->count(),
            'downloads_today' => UserDownload::where('timestamp', '>=', $today)->count(),
            'downloads_week' => UserDownload::where('timestamp', '>=', Carbon::now()->subDays(7))->count(),
            'api_hits_today' => UserRequest::query()->where('timestamp', '>=', $today)->count(),
            'api_hits_week' => UserRequest::query()->where('timestamp', '>=', Carbon::now()->subDays(7))->count(),
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
