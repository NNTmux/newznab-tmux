<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class UpdatePerformanceHelper
{
    /**
     * Check if a file has changed since last update
     */
    public static function hasFileChanged(string $filePath, ?string $cacheKey = null): bool
    {
        if (! File::exists($filePath)) {
            return false;
        }

        $cacheKey = $cacheKey ?? 'file_hash_'.md5($filePath);
        $currentHash = md5_file($filePath);
        $lastHash = Cache::get($cacheKey);

        if ($currentHash !== $lastHash) {
            Cache::put($cacheKey, $currentHash, now()->addDays(7));

            return true;
        }

        return false;
    }

    /**
     * Execute commands in parallel where possible
     */
    public static function executeParallel(array $commands, int $timeout = 300): array
    {
        $processes = [];
        $results = [];

        // Start all processes
        foreach ($commands as $key => $command) {
            $processes[$key] = Process::timeout($timeout)->start($command);
        }

        // Wait for all processes to complete
        foreach ($processes as $key => $process) {
            $process->wait();
            $results[$key] = [
                'successful' => $process->successful(),
                'output' => $process->output(),
                'errorOutput' => $process->errorOutput(),
                'exitCode' => $process->exitCode(),
            ];
        }

        return $results;
    }

    /**
     * Clear various application caches efficiently
     */
    public static function clearAllCaches(): array
    {
        $cacheOperations = [
            'config' => fn () => \Artisan::call('config:clear'),
            'route' => fn () => \Artisan::call('route:clear'),
            'view' => fn () => \Artisan::call('view:clear'),
            'cache' => fn () => \Artisan::call('cache:clear'),
            'opcache' => fn () => function_exists('opcache_reset') ? opcache_reset() : true,
        ];

        $results = [];
        foreach ($cacheOperations as $type => $operation) {
            try {
                $operation();
                $results[$type] = 'success';
            } catch (\Exception $e) {
                $results[$type] = 'failed: '.$e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Optimize file permissions for better performance
     */
    public static function optimizePermissions(): void
    {
        $paths = [
            base_path('bootstrap/cache'),
            base_path('storage'),
            base_path('storage/logs'),
            base_path('storage/framework/cache'),
            base_path('storage/framework/sessions'),
            base_path('storage/framework/views'),
        ];

        foreach ($paths as $path) {
            if (File::exists($path)) {
                chmod($path, 0755);
            }
        }
    }

    /**
     * Check system resources and recommend optimizations
     */
    public static function checkSystemResources(): array
    {
        $recommendations = [];

        // Check system memory usage
        $memInfo = self::getSystemMemoryInfo();
        if ($memInfo && $memInfo['usage_percent'] > 80) {
            $recommendations[] = sprintf(
                'High system memory usage: %.1f%% (%.1fGB of %.1fGB used)',
                $memInfo['usage_percent'],
                $memInfo['used'] / 1024 / 1024 / 1024,
                $memInfo['total'] / 1024 / 1024 / 1024
            );
        }

        // Still check PHP memory separately
        $phpMemoryUsage = memory_get_usage(true);
        $phpMemoryLimit = self::parseMemoryLimit(ini_get('memory_limit'));
        if ($phpMemoryLimit > 0 && $phpMemoryUsage > ($phpMemoryLimit * 0.8)) {
            $recommendations[] = sprintf(
                'PHP memory usage high: %.1fMB of %s',
                $phpMemoryUsage / 1024 / 1024,
                ini_get('memory_limit')
            );
        }

        // Check disk space
        $freeSpace = disk_free_space(base_path());
        $totalSpace = disk_total_space(base_path());

        if ($freeSpace < ($totalSpace * 0.1)) {
            $recommendations[] = 'Low disk space detected';
        }

        // Check PHP extensions
        $requiredExtensions = ['pdo', 'mbstring', 'openssl', 'json', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (! extension_loaded($ext)) {
                $recommendations[] = "Missing PHP extension: $ext";
            }
        }

        return $recommendations;
    }

    /**
     * Get system memory information
     */
    private static function getSystemMemoryInfo(): ?array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null; // Windows memory check would require different approach
        }

        $memInfo = @file_get_contents('/proc/meminfo');
        if (! $memInfo) {
            return null;
        }

        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $availableMatch);

        if (! isset($totalMatch[1]) || ! isset($availableMatch[1])) {
            return null;
        }

        $total = (int) $totalMatch[1] * 1024; // Convert from KB to bytes
        $available = (int) $availableMatch[1] * 1024;
        $used = $total - $available;

        return [
            'total' => $total,
            'available' => $available,
            'used' => $used,
            'usage_percent' => ($used / $total) * 100,
        ];
    }

    /**
     * Parse memory limit string to bytes
     */
    private static function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1') {
            return -1;
        }

        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Create a system performance snapshot
     */
    public static function createPerformanceSnapshot(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'disk_free' => disk_free_space(base_path()),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        ];
    }
}
