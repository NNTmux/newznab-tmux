<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\AdminDashboardSnapshotService;
use App\Services\RegistrationStatusService;
use App\Services\SiteStatusService;
use App\Services\SystemMetricsService;
use App\Services\UserStatsService;
use Illuminate\Http\JsonResponse;

class AdminPageController extends BasePageController
{
    protected UserStatsService $userStatsService;

    protected SystemMetricsService $systemMetricsService;

    protected RegistrationStatusService $registrationStatusService;

    protected SiteStatusService $siteStatusService;

    protected AdminDashboardSnapshotService $snapshot;

    public function __construct(
        UserStatsService $userStatsService,
        SystemMetricsService $systemMetricsService,
        RegistrationStatusService $registrationStatusService,
        SiteStatusService $siteStatusService,
        AdminDashboardSnapshotService $snapshot,
    ) {
        parent::__construct();
        $this->userStatsService = $userStatsService;
        $this->systemMetricsService = $systemMetricsService;
        $this->registrationStatusService = $registrationStatusService;
        $this->siteStatusService = $siteStatusService;
        $this->snapshot = $snapshot;
    }

    /**
     * @throws \Exception
     */
    public function index(): mixed
    {
        $this->setAdminPrefs();

        $payload = $this->snapshot->get();

        return view('admin.dashboard', array_merge($this->viewData, [
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
            // Lightweight data is rendered server-side; heavy widgets fetch on mount.
            'stats' => $payload['stats'],
            'registrationStatus' => $payload['registrationStatus'],
            'nextRegistrationPeriod' => $payload['nextRegistrationPeriod'],
        ]));
    }

    /**
     * JSON payload consumed by the dashboard's deferred widgets (User Statistics,
     * System Resources history, Recent Activity, Site Status). Reads from the
     * same snapshot cache the index uses, so the warmer keeps it hot.
     */
    public function getDashboardData(): JsonResponse
    {
        $payload = $this->snapshot->get();

        $serviceStatuses = collect($payload['serviceStatuses'])->map(static fn ($svc) => [
            'name' => $svc->name,
            'slug' => $svc->slug,
            'check_type' => $svc->check_type,
            'status' => $svc->status->value,
            'status_label' => $svc->status->label(),
            'uptime_percentage' => (float) $svc->uptime_percentage,
        ])->values();

        $activeIncidents = collect($payload['activeIncidents'])->map(static fn ($incident) => [
            'id' => $incident->id,
            'title' => $incident->title,
            'impact' => $incident->impact->value,
            'impact_label' => $incident->impact->label(),
            'started_at' => $incident->started_at?->toIso8601String(),
            'started_at_human' => $incident->started_at?->diffForHumans(),
            'is_auto' => (bool) $incident->is_auto,
            'services' => $incident->services->pluck('name')->all(),
        ])->values();

        $recentActivity = array_map(static fn ($activity): array => [
            'type' => $activity->type,
            'message' => $activity->message,
            'icon' => $activity->icon,
            'icon_bg' => $activity->icon_bg,
            'icon_color' => $activity->icon_color,
            'created_at' => $activity->created_at?->toIso8601String(),
            'created_at_human' => $activity->created_at?->diffForHumans(),
            'username' => $activity->username,
            'metadata' => $activity->metadata,
        ], $payload['recent_activity']);

        return response()->json([
            'success' => true,
            'generated_at' => $payload['generated_at'],
            'userStats' => $payload['userStats'],
            'systemMetrics' => $payload['systemMetrics'],
            'recent_activity' => $recentActivity,
            'serviceStatuses' => $serviceStatuses,
            'activeIncidents' => $activeIncidents,
        ]);
    }

    public function getRecentActivity(): JsonResponse
    {
        $payload = $this->snapshot->get();

        $activities = array_map(static fn ($activity): array => [
            'type' => $activity->type,
            'message' => $activity->message,
            'icon' => $activity->icon,
            'icon_bg' => $activity->icon_bg,
            'icon_color' => $activity->icon_color,
            'created_at' => $activity->created_at?->diffForHumans(),
            'username' => $activity->username,
            'metadata' => $activity->metadata,
        ], $payload['recent_activity']);

        return response()->json([
            'success' => true,
            'activities' => $activities,
        ]);
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

