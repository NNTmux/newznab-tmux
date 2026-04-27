<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DnzbFailure;
use App\Models\Release;
use App\Models\ReleaseReport;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\ApproximateRowCount;
use Illuminate\Support\Facades\Cache;

/**
 * Builds (and caches) the entire admin dashboard payload in a single
 * Cache::flexible() entry so the controller never blocks on cold queries.
 *
 * - Fresh window: 60s
 * - Stale-while-revalidate window: 600s
 * - Warmed every minute by the `admin:warm-dashboard` scheduled command.
 */
class AdminDashboardSnapshotService
{
    public const CACHE_KEY = 'admin:dashboard:snapshot';

    /**
     * @var array{0: int, 1: int}
     */
    private const CACHE_TTL = [60, 600];

    public function __construct(
        private readonly UserStatsService $userStatsService,
        private readonly SystemMetricsService $systemMetricsService,
        private readonly RegistrationStatusService $registrationStatusService,
        private readonly SiteStatusService $siteStatusService,
    ) {}

    /**
     * Returns the cached dashboard snapshot, building it on first miss.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::flexible(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->build());
    }

    /**
     * Forcibly rebuild and re-cache the snapshot. Used by the warmer command
     * and by admin actions that want immediate freshness.
     *
     * @return array<string, mixed>
     */
    public function warm(): array
    {
        $payload = $this->build();
        // Re-prime both the value and the SWR control key used by Cache::flexible().
        Cache::forget(self::CACHE_KEY);

        return Cache::flexible(self::CACHE_KEY, self::CACHE_TTL, static fn (): array => $payload);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $now = now();
        $registrationStatus = $this->registrationStatusService->resolve($now);
        $nextRegistrationPeriod = $registrationStatus['active_period'] === null
            ? $this->registrationStatusService->getNextUpcomingPeriod($now)
            : null;

        return [
            'generated_at' => $now->toIso8601String(),
            'stats' => $this->buildStats(),
            'userStats' => $this->buildUserStats(),
            'systemMetrics' => $this->buildSystemMetrics(),
            'recent_activity' => $this->buildRecentActivity(),
            'serviceStatuses' => $this->siteStatusService->getEnabledServices(),
            'activeIncidents' => $this->siteStatusService->getActiveIncidents(),
            'registrationStatus' => $registrationStatus,
            'nextRegistrationPeriod' => $nextRegistrationPeriod,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        $today = now()->format('Y-m-d');

        // Approximate counts on huge tables — InnoDB metadata estimates are
        // good enough for the admin overview tiles.
        $releasesCount = ApproximateRowCount::for('releases');
        $usersCount = ApproximateRowCount::for('users');
        $groupsCount = ApproximateRowCount::for('usenet_groups');
        $failedCount = ApproximateRowCount::for('dnzb_failures');

        // Small / index-friendly aggregates kept exact.
        $activeGroupsCount = UsenetGroup::where('active', 1)->count();
        $reportedCount = ReleaseReport::where('status', 'pending')->count();
        $softDeletedCount = User::onlyTrashed()->count();

        // Replaces previous whereJsonContains() count which couldn't use an index.
        $permanentlyDeletedCount = UserActivity::query()
            ->where('activity_type', 'deleted')
            ->where('is_permanent', true)
            ->count();

        $releasesToday = Release::whereDate('adddate', $today)->count();
        $usersToday = User::whereNull('deleted_at')->whereDate('created_at', $today)->count();

        return [
            'releases' => $releasesCount,
            'releases_today' => $releasesToday,
            'users' => $usersCount,
            'users_today' => $usersToday,
            'groups' => $groupsCount,
            'active_groups' => $activeGroupsCount,
            'failed' => $failedCount,
            'reported' => $reportedCount,
            'soft_deleted_users' => $softDeletedCount,
            'permanently_deleted_users' => $permanentlyDeletedCount,
            'disk_free' => $this->systemMetricsService->getDiskSpace(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserStats(): array
    {
        return [
            'users_by_role' => $this->userStatsService->getUsersByRole(),
            'downloads_per_hour' => $this->userStatsService->getDownloadsPerHour(168),
            'downloads_per_minute' => $this->userStatsService->getDownloadsPerMinute(60),
            'api_hits_per_hour' => $this->userStatsService->getApiHitsPerHour(168),
            'api_hits_per_minute' => $this->userStatsService->getApiHitsPerMinute(60),
            'summary' => $this->userStatsService->getSummaryStats(),
            'top_downloaders' => $this->userStatsService->getTopDownloaders(5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSystemMetrics(): array
    {
        $cpuInfo = $this->systemMetricsService->getCpuInfo();
        $cpuUsage = $this->systemMetricsService->getCpuUsage();
        $ramUsage = $this->systemMetricsService->getRamUsage();
        $loadAverage = $this->systemMetricsService->getLoadAverage();

        return [
            'cpu' => [
                'current' => $cpuUsage,
                'label' => 'CPU Usage',
                'history_24h' => $this->systemMetricsService->getHourlyMetrics('cpu', 24),
                'history_30d' => $this->systemMetricsService->getDailyMetrics('cpu', 30),
                'cores' => $cpuInfo['cores'],
                'threads' => $cpuInfo['threads'],
                'model' => $cpuInfo['model'],
                'load_average' => $loadAverage,
            ],
            'ram' => [
                'used' => $ramUsage['used'],
                'total' => $ramUsage['total'],
                'percentage' => $ramUsage['percentage'],
                'label' => 'RAM Usage',
                'history_24h' => $this->systemMetricsService->getHourlyMetrics('ram', 24),
                'history_30d' => $this->systemMetricsService->getDailyMetrics('ram', 30),
            ],
        ];
    }

    /**
     * @return array<int, object>
     */
    private function buildRecentActivity(): array
    {
        $activities = UserActivity::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $activities->map(static function ($activity): object {
            return (object) [
                'type' => $activity->activity_type,
                'message' => $activity->description,
                'icon' => $activity->icon,
                'icon_bg' => 'bg-'.$activity->color_class.'-100 dark:bg-'.$activity->color_class.'-900',
                'icon_color' => 'text-'.$activity->color_class.'-600 dark:text-'.$activity->color_class.'-400',
                'created_at' => $activity->created_at,
                'username' => $activity->username,
                'metadata' => $activity->metadata,
            ];
        })->all();
    }
}

