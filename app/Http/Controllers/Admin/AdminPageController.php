<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\DnzbFailure;
use App\Models\Release;
use App\Models\ReleaseReport;
use App\Models\UsenetGroup;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\RegistrationStatusService;
use App\Services\SiteStatusService;
use App\Services\SystemMetricsService;
use App\Services\UserStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AdminPageController extends BasePageController
{
    protected UserStatsService $userStatsService;

    protected SystemMetricsService $systemMetricsService;

    protected RegistrationStatusService $registrationStatusService;

    protected SiteStatusService $siteStatusService;

    public function __construct(
        UserStatsService $userStatsService,
        SystemMetricsService $systemMetricsService,
        RegistrationStatusService $registrationStatusService,
        SiteStatusService $siteStatusService
    ) {
        parent::__construct();
        $this->userStatsService = $userStatsService;
        $this->systemMetricsService = $systemMetricsService;
        $this->registrationStatusService = $registrationStatusService;
        $this->siteStatusService = $siteStatusService;
    }

    /**
     * @throws \Exception
     */
    public function index(): mixed
    {
        $this->setAdminPrefs();
        $now = now();

        $userStats = Cache::remember('admin_user_stats', 120, function () {
            return [
                'users_by_role' => $this->userStatsService->getUsersByRole(),
                'downloads_per_hour' => $this->userStatsService->getDownloadsPerHour(168),
                'downloads_per_minute' => $this->userStatsService->getDownloadsPerMinute(60),
                'api_hits_per_hour' => $this->userStatsService->getApiHitsPerHour(168),
                'api_hits_per_minute' => $this->userStatsService->getApiHitsPerMinute(60),
                'summary' => $this->userStatsService->getSummaryStats(),
                'top_downloaders' => $this->userStatsService->getTopDownloaders(5),
            ];
        });

        $registrationStatus = $this->registrationStatusService->resolve($now);
        $nextRegistrationPeriod = $registrationStatus['active_period'] === null
            ? $this->registrationStatusService->getNextUpcomingPeriod($now)
            : null;

        return view('admin.dashboard', array_merge($this->viewData, [
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
            'userStats' => $userStats,
            'stats' => $this->getDefaultStats(),
            'systemMetrics' => $this->getSystemMetrics(),
            'registrationStatus' => $registrationStatus,
            'nextRegistrationPeriod' => $nextRegistrationPeriod,
            'recent_activity' => $this->getRecentUserActivity(),
            'serviceStatuses' => $this->siteStatusService->getEnabledServices(),
            'activeIncidents' => $this->siteStatusService->getActiveIncidents(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRecentUserActivity(): array
    {
        return Cache::remember('admin_recent_activity', 60, function () {
            $activities = UserActivity::orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return $activities->map(function ($activity) {
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
            })->toArray();
        });
    }

    public function getRecentActivity(): JsonResponse
    {
        $activities = $this->getRecentUserActivity();

        return response()->json([
            'success' => true,
            'activities' => array_map(function ($activity) {
                return [
                    'type' => $activity->type,
                    'message' => $activity->message,
                    'icon' => $activity->icon,
                    'icon_bg' => $activity->icon_bg,
                    'icon_color' => $activity->icon_color,
                    'created_at' => $activity->created_at->diffForHumans(),
                    'username' => $activity->username,
                    'metadata' => $activity->metadata,
                ];
            }, $activities),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultStats(): array
    {
        $today = now()->format('Y-m-d');

        $releasesCount = Cache::remember('admin_stats_releases_count', 300, fn () => Release::count());

        $usersCount = Cache::remember('admin_stats_users_count', 300, fn () => User::whereNull('deleted_at')->count());

        $groupsCount = Cache::remember('admin_stats_groups_count', 600, fn () => UsenetGroup::count());

        $activeGroupsCount = Cache::remember('admin_stats_active_groups_count', 600, fn () => UsenetGroup::where('active', 1)->count());

        $failedCount = Cache::remember('admin_stats_failed_count', 300, fn () => DnzbFailure::count());

        $reportedCount = Cache::remember('admin_stats_reported_count', 300, fn () => ReleaseReport::where('status', 'pending')->count());

        $softDeletedCount = Cache::remember('admin_stats_soft_deleted_users_count', 300, fn () => User::onlyTrashed()->count());

        $permanentlyDeletedCount = Cache::remember('admin_stats_permanently_deleted_users_count', 300, fn () => UserActivity::where('activity_type', 'deleted')
            ->whereJsonContains('metadata->permanent', true)
            ->count());

        $releasesToday = Cache::remember('admin_stats_releases_today_'.$today, 60, fn () => Release::whereDate('adddate', $today)->count());

        $usersToday = Cache::remember('admin_stats_users_today_'.$today, 60, fn () => User::whereNull('deleted_at')->whereDate('created_at', $today)->count());

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
    protected function getSystemMetrics(): array
    {
        $cpuInfo = Cache::remember('admin_cpu_info', 3600, fn () => $this->systemMetricsService->getCpuInfo());

        $cpuUsage = Cache::remember('admin_cpu_usage', 30, fn () => $this->systemMetricsService->getCpuUsage());

        $ramUsage = Cache::remember('admin_ram_usage', 30, fn () => $this->systemMetricsService->getRamUsage());

        $loadAverage = Cache::remember('admin_load_average', 30, fn () => $this->systemMetricsService->getLoadAverage());

        $cpuHistory24h = Cache::remember('admin_cpu_history_24h', 300, fn () => $this->systemMetricsService->getHourlyMetrics('cpu', 24));

        $cpuHistory30d = Cache::remember('admin_cpu_history_30d', 300, fn () => $this->systemMetricsService->getDailyMetrics('cpu', 30));

        $ramHistory24h = Cache::remember('admin_ram_history_24h', 300, fn () => $this->systemMetricsService->getHourlyMetrics('ram', 24));

        $ramHistory30d = Cache::remember('admin_ram_history_30d', 300, fn () => $this->systemMetricsService->getDailyMetrics('ram', 30));

        return [
            'cpu' => [
                'current' => $cpuUsage,
                'label' => 'CPU Usage',
                'history_24h' => $cpuHistory24h,
                'history_30d' => $cpuHistory30d,
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
                'history_24h' => $ramHistory24h,
                'history_30d' => $ramHistory30d,
            ],
        ];
    }

    public function getUserActivityMinutes(): JsonResponse
    {
        $downloadsPerMinute = $this->userStatsService->getDownloadsPerMinute(60);
        $apiHitsPerMinute = $this->userStatsService->getApiHitsPerMinute(60);

        return response()->json([
            'downloads' => $downloadsPerMinute,
            'api_hits' => $apiHitsPerMinute,
        ]);
    }

    public function getCurrentMetrics(): JsonResponse
    {
        $cpuUsage = $this->systemMetricsService->getCpuUsage();
        $ramUsage = $this->systemMetricsService->getRamUsage();
        $cpuInfo = $this->systemMetricsService->getCpuInfo();
        $loadAverage = $this->systemMetricsService->getLoadAverage();

        return response()->json([
            'success' => true,
            'cpu' => [
                'current' => $cpuUsage,
                'cores' => $cpuInfo['cores'],
                'threads' => $cpuInfo['threads'],
                'model' => $cpuInfo['model'],
                'load_average' => $loadAverage,
            ],
            'ram' => [
                'used' => $ramUsage['used'],
                'total' => $ramUsage['total'],
                'percentage' => $ramUsage['percentage'],
            ],
        ]);
    }

    public function getHistoricalMetrics(): JsonResponse
    {
        $timeRange = request('range', '24h');

        if ($timeRange === '30d') {
            $cpuHistory = $this->systemMetricsService->getDailyMetrics('cpu', 30);
            $ramHistory = $this->systemMetricsService->getDailyMetrics('ram', 30);
        } else {
            $cpuHistory = $this->systemMetricsService->getHourlyMetrics('cpu', 24);
            $ramHistory = $this->systemMetricsService->getHourlyMetrics('ram', 24);
        }

        return response()->json([
            'success' => true,
            'cpu_history' => $cpuHistory,
            'ram_history' => $ramHistory,
            'range' => $timeRange,
        ]);
    }
}
