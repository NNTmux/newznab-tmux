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

        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');

        if ($memoryUsage > (int) $memoryLimit * 0.8) {
            $recommendations[] = 'Consider increasing PHP memory_limit';
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
