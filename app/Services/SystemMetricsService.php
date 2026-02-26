<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemMetric;
use Illuminate\Support\Facades\Log;

class SystemMetricsService
{
    /**
     * Collect and store current system metrics
     */
    public function collectMetrics(): void
    {
        $cpuUsage = $this->getCpuUsage();
        $ramUsage = $this->getRamUsage();

        $now = now();

        // Store CPU metric
        SystemMetric::create([
            'metric_type' => 'cpu',
            'value' => $cpuUsage,
            'recorded_at' => $now,
        ]);

        // Store RAM metric (percentage)
        SystemMetric::create([
            'metric_type' => 'ram',
            'value' => $ramUsage['percentage'],
            'recorded_at' => $now,
        ]);
    }

    /**
     * Get historical metrics grouped by hour for the last N hours
     *
     * @return array<string, mixed>
     */
    public function getHourlyMetrics(string $type, int $hours = 24): array
    {
        $startDate = now()->subHours($hours);

        $metrics = SystemMetric::where('metric_type', $type)
            ->where('recorded_at', '>=', $startDate)
            ->orderBy('recorded_at')
            ->get();

        // Group by hour and calculate average
        $hourlyData = [];
        foreach ($metrics as $metric) {
            $hourKey = $metric->recorded_at->format('Y-m-d H:00');
            $hourLabel = $metric->recorded_at->format('H:i');

            if (! isset($hourlyData[$hourKey])) {
                $hourlyData[$hourKey] = [
                    'time' => $hourLabel,
                    'values' => [],
                ];
            }
            $hourlyData[$hourKey]['values'][] = $metric->value;
        }

        // Calculate averages
        $result = [];
        foreach ($hourlyData as $data) {
            $result[] = [
                'time' => $data['time'],
                'value' => round(array_sum($data['values']) / count($data['values']), 2),
            ];
        }

        // Fill missing hours with null or last known value
        return $this->fillMissingHours($result, $hours); // @phpstan-ignore argument.type
    }

    /**
     * Get historical metrics grouped by day for the last N days
     *
     * @return list<array<string, float|string>>
     */
    public function getDailyMetrics(string $type, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $metrics = SystemMetric::where('metric_type', $type)
            ->where('recorded_at', '>=', $startDate)
            ->orderBy('recorded_at')
            ->get();

        // Group by day and calculate average
        $dailyData = [];
        foreach ($metrics as $metric) {
            $dayKey = $metric->recorded_at->format('Y-m-d');
            $dayLabel = $metric->recorded_at->format('M d');

            if (! isset($dailyData[$dayKey])) {
                $dailyData[$dayKey] = [
                    'time' => $dayLabel,
                    'values' => [],
                ];
            }
            $dailyData[$dayKey]['values'][] = $metric->value;
        }

        // Calculate averages
        $result = [];
        foreach ($dailyData as $data) {
            $result[] = [
                'time' => $data['time'],
                'value' => round(array_sum($data['values']) / count($data['values']), 2),
            ];
        }

        return $result;
    }

    /**
     * Fill missing hours with previous value or 0
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, float|string>>
     */
    protected function fillMissingHours(array $data, int $hours): array
    {
        if (empty($data)) {
            // Generate empty data points for all hours
            $result = [];
            for ($i = $hours - 1; $i >= 0; $i--) {
                $time = now()->subHours($i);
                $result[] = [
                    'time' => $time->format('H:i'),
                    'value' => 0,
                ];
            }

            return $result;
        }

        return $data;
    }

    /**
     * Get current CPU usage percentage
     *
     * @return non-empty-array<string, mixed>
     */
    protected function getCpuUsage(): float
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $output = shell_exec('wmic cpu get loadpercentage');
                if ($output) {
                    preg_match('/\d+/', $output, $matches);

                    return (float) ($matches[0] ?? 0);
                }
            } else {
                // Linux - use load average
                $load = sys_getloadavg();
                if ($load !== false) {
                    $cpuCount = $this->getCpuCount();

                    return round(($load[0] / $cpuCount) * 100, 2);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not get CPU usage: '.$e->getMessage());
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
     * Get RAM usage information
     *
     * @return array<string, mixed>
     */
    protected function getRamUsage(): array
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
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
            Log::warning('Could not get RAM usage: '.$e->getMessage());
        }

        return [
            'used' => 0,
            'total' => 0,
            'percentage' => 0,
        ];
    }

    /**
     * Clean up old metrics (keep last 60 days)
     */
    public function cleanupOldMetrics(int $daysToKeep = 60): int
    {
        return SystemMetric::cleanupOldMetrics($daysToKeep);
    }
}
