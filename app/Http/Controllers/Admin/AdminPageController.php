<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\SystemMetricsService;
use App\Services\UserStatsService;

class AdminPageController extends BasePageController
{
    protected UserStatsService $userStatsService;

    protected SystemMetricsService $systemMetricsService;

    public function __construct(UserStatsService $userStatsService, SystemMetricsService $systemMetricsService)
    {
        parent::__construct();
        $this->userStatsService = $userStatsService;
        $this->systemMetricsService = $systemMetricsService;
    }

    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        // Get user statistics
        $userStats = [
            'users_by_role' => $this->userStatsService->getUsersByRole(),
            'downloads_per_hour' => $this->userStatsService->getDownloadsPerHour(168), // Last 7 days in hours
            'downloads_per_minute' => $this->userStatsService->getDownloadsPerMinute(60),
            'api_hits_per_hour' => $this->userStatsService->getApiHitsPerHour(168), // Last 7 days in hours
            'api_hits_per_minute' => $this->userStatsService->getApiHitsPerMinute(60),
            'summary' => $this->userStatsService->getSummaryStats(),
            'top_downloaders' => $this->userStatsService->getTopDownloaders(5),
        ];

        return view('admin.dashboard', array_merge($this->viewData, [
            'meta_title' => 'Admin Home',
            'meta_description' => 'Admin home page',
            'userStats' => $userStats,
            'stats' => $this->getDefaultStats(),
            'systemMetrics' => $this->getSystemMetrics(),
            'recent_activity' => $this->getRecentUserActivity(),
        ]));
    }

    /**
     * Get recent user activity from the user_activities table
     */
    protected function getRecentUserActivity(): array
    {
        $activities = \App\Models\UserActivity::orderBy('created_at', 'desc')
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
            ];
        })->toArray();
    }

    /**
     * API endpoint to get recent user activity (for auto-refresh)
     */
    public function getRecentActivity()
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
                ];
            }, $activities),
        ]);
    }

    /**
     * Get default dashboard statistics
     */
    protected function getDefaultStats(): array
    {
        $today = now()->format('Y-m-d');

        return [
            'releases' => \App\Models\Release::count(),
            'releases_today' => \App\Models\Release::whereRaw('DATE(adddate) = ?', [$today])->count(),
            'users' => \App\Models\User::whereNull('deleted_at')->count(),
            'users_today' => \App\Models\User::whereRaw('DATE(created_at) = ?', [$today])->count(),
            'groups' => \App\Models\UsenetGroup::count(),
            'active_groups' => \App\Models\UsenetGroup::where('active', 1)->count(),
            'failed' => \App\Models\DnzbFailure::count(),
            'disk_free' => $this->getDiskSpace(),
        ];
    }

    /**
     * Get disk space information
     */
    protected function getDiskSpace(): string
    {
        try {
            $bytes = disk_free_space('/');
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];

            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }

            return round($bytes, 2).' '.$units[$i];
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Get system metrics (CPU and RAM usage)
     */
    protected function getSystemMetrics(): array
    {
        $cpuUsage = $this->getCpuUsage();
        $ramUsage = $this->getRamUsage();
        $cpuInfo = $this->getCpuInfo();
        $loadAverage = $this->getLoadAverage();

        // Get historical data from database - both hourly (24h) and daily (30d)
        $cpuHistory24h = $this->systemMetricsService->getHourlyMetrics('cpu', 24);
        $cpuHistory30d = $this->systemMetricsService->getDailyMetrics('cpu', 30);
        $ramHistory24h = $this->systemMetricsService->getHourlyMetrics('ram', 24);
        $ramHistory30d = $this->systemMetricsService->getDailyMetrics('ram', 30);

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

    /**
     * Get current CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                $output = shell_exec('wmic cpu get loadpercentage');
                if ($output) {
                    preg_match('/\d+/', $output, $matches);

                    return $matches[0] ?? 0;
                }
            } else {
                // Linux command - get load average and convert to percentage
                $load = sys_getloadavg();
                if ($load !== false) {
                    $cpuCount = $this->getCpuCount();

                    return round(($load[0] / $cpuCount) * 100, 2);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not get CPU usage: '.$e->getMessage());
        }

        return 0;
    }

    /**
     * Get number of CPU cores
     */
    protected function getCpuCount(): int
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $output = shell_exec('wmic cpu get NumberOfLogicalProcessors');
                if ($output) {
                    preg_match('/\d+/', $output, $matches);

                    return (int) ($matches[0] ?? 1);
                }
            } else {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                preg_match_all('/^processor/m', $cpuinfo, $matches);

                return count($matches[0]) ?: 1;
            }
        } catch (\Exception $e) {
            return 1;
        }

        return 1;
    }

    /**
     * Get detailed CPU information (cores, threads, model)
     */
    protected function getCpuInfo(): array
    {
        $info = [
            'cores' => 0,
            'threads' => 0,
            'model' => 'Unknown',
        ];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Get number of cores
                $coresOutput = shell_exec('wmic cpu get NumberOfCores');
                if ($coresOutput) {
                    preg_match('/\d+/', $coresOutput, $matches);
                    $info['cores'] = (int) ($matches[0] ?? 0);
                }

                // Get number of logical processors (threads)
                $threadsOutput = shell_exec('wmic cpu get NumberOfLogicalProcessors');
                if ($threadsOutput) {
                    preg_match('/\d+/', $threadsOutput, $matches);
                    $info['threads'] = (int) ($matches[0] ?? 0);
                }

                // Get CPU model
                $modelOutput = shell_exec('wmic cpu get Name');
                if ($modelOutput) {
                    $lines = explode("\n", trim($modelOutput));
                    if (isset($lines[1])) {
                        $info['model'] = trim($lines[1]);
                    }
                }
            } else {
                // Linux
                $cpuinfo = file_get_contents('/proc/cpuinfo');

                // Get number of physical cores
                preg_match_all('/^cpu cores\s*:\s*(\d+)/m', $cpuinfo, $coresMatches);
                if (! empty($coresMatches[1])) {
                    $info['cores'] = (int) $coresMatches[1][0];
                }

                // Get number of logical processors (threads)
                preg_match_all('/^processor/m', $cpuinfo, $processorMatches);
                $info['threads'] = count($processorMatches[0]) ?: 0;

                // Get CPU model
                preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $modelMatches);
                if (! empty($modelMatches[1])) {
                    $info['model'] = trim($modelMatches[1]);
                }

                // If cores is 0, try to get from physical id count
                if ($info['cores'] === 0) {
                    preg_match_all('/^physical id\s*:\s*(\d+)/m', $cpuinfo, $physicalMatches);
                    $uniquePhysical = ! empty($physicalMatches[1]) ? count(array_unique($physicalMatches[1])) : 1;
                    $info['cores'] = (int) ($info['threads'] / $uniquePhysical);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not get CPU info: '.$e->getMessage());
        }

        return $info;
    }

    /**
     * Get system load average
     */
    protected function getLoadAverage(): array
    {
        $loadAvg = [
            '1min' => 0,
            '5min' => 0,
            '15min' => 0,
        ];

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows doesn't have load average, use CPU queue length instead
                $output = shell_exec('wmic path Win32_PerfFormattedData_PerfOS_System get ProcessorQueueLength');
                if ($output) {
                    preg_match('/\d+/', $output, $matches);
                    $queueLength = (int) ($matches[0] ?? 0);
                    // Approximate load average
                    $loadAvg['1min'] = round($queueLength / 2, 2);
                    $loadAvg['5min'] = round($queueLength / 2, 2);
                    $loadAvg['15min'] = round($queueLength / 2, 2);
                }
            } else {
                // Linux has native load average
                $load = sys_getloadavg();
                if ($load !== false) {
                    $loadAvg['1min'] = round($load[0], 2);
                    $loadAvg['5min'] = round($load[1], 2);
                    $loadAvg['15min'] = round($load[2], 2);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not get load average: '.$e->getMessage());
        }

        return $loadAvg;
    }

    /**
     * Get RAM usage information
     */
    protected function getRamUsage(): array
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows command
                $output = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
                if ($output) {
                    preg_match('/FreePhysicalMemory=(\d+)/', $output, $free);
                    preg_match('/TotalVisibleMemorySize=(\d+)/', $output, $total);

                    if (isset($free[1]) && isset($total[1])) {
                        $freeKb = (float) $free[1];
                        $totalKb = (float) $total[1];
                        $usedKb = $totalKb - $freeKb;

                        return [
                            'used' => round($usedKb / 1024 / 1024, 2),
                            'total' => round($totalKb / 1024 / 1024, 2),
                            'percentage' => round(($usedKb / $totalKb) * 100, 2),
                        ];
                    }
                }
            } else {
                // Linux command
                $meminfo = file_get_contents('/proc/meminfo');
                preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
                preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

                if (isset($total[1]) && isset($available[1])) {
                    $totalKb = (float) $total[1];
                    $availableKb = (float) $available[1];
                    $usedKb = $totalKb - $availableKb;

                    return [
                        'used' => round($usedKb / 1024 / 1024, 2),
                        'total' => round($totalKb / 1024 / 1024, 2),
                        'percentage' => round(($usedKb / $totalKb) * 100, 2),
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not get RAM usage: '.$e->getMessage());
        }

        return [
            'used' => 0,
            'total' => 0,
            'percentage' => 0,
        ];
    }

    /**
     * Get minute-to-minute user activity data (API endpoint)
     */
    public function getUserActivityMinutes()
    {
        $downloadsPerMinute = $this->userStatsService->getDownloadsPerMinute(60);
        $apiHitsPerMinute = $this->userStatsService->getApiHitsPerMinute(60);

        return response()->json([
            'downloads' => $downloadsPerMinute,
            'api_hits' => $apiHitsPerMinute,
        ]);
    }
}
