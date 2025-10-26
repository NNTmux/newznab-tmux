<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivityStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'stat_date' => 'date',
    ];

    /**
     * Collect and store user activity stats for a specific date
     * This aggregates data from user_downloads and user_requests tables
     */
    public static function collectDailyStats(?string $date = null): void
    {
        $statDate = $date ? Carbon::parse($date)->format('Y-m-d') : Carbon::yesterday()->format('Y-m-d');

        // Count downloads for the date
        $downloadsCount = UserDownload::query()
            ->whereRaw('DATE(timestamp) = ?', [$statDate])
            ->count();

        // Count API hits for the date
        $apiHitsCount = UserRequest::query()
            ->whereRaw('DATE(timestamp) = ?', [$statDate])
            ->count();

        // Store or update the stats
        self::updateOrCreate(
            ['stat_date' => $statDate],
            [
                'downloads_count' => $downloadsCount,
                'api_hits_count' => $apiHitsCount,
            ]
        );
    }

    /**
     * Get download stats for the last N days
     */
    public static function getDownloadsPerDay(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->format('Y-m-d');

        $stats = self::query()
            ->select('stat_date', 'downloads_count')
            ->where('stat_date', '>=', $startDate)
            ->orderBy('stat_date', 'asc')
            ->get()
            ->keyBy('stat_date');

        // Fill in missing days with zero counts
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $stat = $stats->get($date);
            $result[] = [
                'date' => Carbon::parse($date)->format('M d'),
                'count' => $stat ? $stat->downloads_count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get API hits stats for the last N days
     */
    public static function getApiHitsPerDay(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days - 1)->format('Y-m-d');

        $stats = self::query()
            ->select('stat_date', 'api_hits_count')
            ->where('stat_date', '>=', $startDate)
            ->orderBy('stat_date', 'asc')
            ->get()
            ->keyBy('stat_date');

        // Fill in missing days with zero counts
        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $stat = $stats->get($date);
            $result[] = [
                'date' => Carbon::parse($date)->format('M d'),
                'count' => $stat ? $stat->api_hits_count : 0,
            ];
        }

        return $result;
    }

    /**
     * Get total downloads for the last N days
     */
    public static function getTotalDownloads(int $days = 7): int
    {
        return self::query()
            ->where('stat_date', '>=', Carbon::now()->subDays($days)->format('Y-m-d'))
            ->sum('downloads_count');
    }

    /**
     * Get total API hits for the last N days
     */
    public static function getTotalApiHits(int $days = 7): int
    {
        return self::query()
            ->where('stat_date', '>=', Carbon::now()->subDays($days)->format('Y-m-d'))
            ->sum('api_hits_count');
    }

    /**
     * Cleanup old stats (keep last N days)
     */
    public static function cleanupOldStats(int $keepDays = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($keepDays)->format('Y-m-d');

        return self::query()
            ->where('stat_date', '<', $cutoffDate)
            ->delete();
    }
}
