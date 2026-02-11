<?php

namespace App\Console\Commands;

use App\Services\RedisMonitorService;
use Illuminate\Console\Command;

class RedisMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:monitor
                            {--connection=default : Redis connection name from config/database.php}
                            {--refresh=30 : Refresh interval in seconds}
                            {--once : Display stats once and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Redis server with a visual dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->option('connection');
        $refresh = (int) $this->option('refresh');
        $once = $this->option('once');

        try {
            $service = new RedisMonitorService($connection, $refresh);

            if ($once) {
                $stats = $service->getStats();

                if (! $stats['connected']) {
                    $this->error("Cannot connect to Redis at {$stats['host']}:{$stats['port']}");

                    return Command::FAILURE;
                }

                $this->displayStats($stats);

                return Command::SUCCESS;
            }

            // Start the continuous monitor
            $service->monitor();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Redis Monitor failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Display stats in table format (for --once mode).
     *
     * @param  array<string, mixed>  $stats
     */
    protected function displayStats(array $stats): void
    {
        $this->newLine();
        $this->line("🔴 Redis Monitor - {$stats['host']}:{$stats['port']} (v{$stats['version']})");
        $this->newLine();

        $this->line('📊 Server Status');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', '● ONLINE'],
                ['Role', $stats['role']],
                ['Uptime', "{$stats['uptime_days']} days"],
            ]
        );

        $this->newLine();
        $this->line('💾 Memory Usage');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Used Memory', $this->formatBytes($stats['used_memory'])],
                ['Peak Memory', $this->formatBytes($stats['used_memory_peak'])],
                ['RSS Memory', $this->formatBytes($stats['used_memory_rss'])],
                ['Fragmentation Ratio', $stats['mem_fragmentation_ratio']],
            ]
        );

        $this->newLine();
        $this->line('👥 Clients & Connections');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Connected Clients', number_format($stats['connected_clients'])],
                ['Blocked Clients', number_format($stats['blocked_clients'])],
                ['Total Connections', number_format($stats['total_connections'])],
            ]
        );

        $this->newLine();
        $this->line('⚡ Performance Metrics');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Commands Processed', number_format($stats['total_commands'])],
                ['Operations/sec', number_format($stats['ops_per_sec']).' ops/s'],
                ['Network Input', $stats['input_kbps'].' KB/s'],
                ['Network Output', $stats['output_kbps'].' KB/s'],
            ]
        );

        $this->newLine();
        $this->line('🔑 Keyspace Statistics');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Keys (db0)', number_format($stats['db_keys'])],
                ['Keyspace Hits', number_format($stats['keyspace_hits'])],
                ['Keyspace Misses', number_format($stats['keyspace_misses'])],
                ['Hit Rate', $stats['hit_rate'].'%'],
                ['Expired Keys', number_format($stats['expired_keys'])],
                ['Evicted Keys', number_format($stats['evicted_keys'])],
            ]
        );
    }

    /**
     * Format bytes into human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return sprintf('%.2f GB', $bytes / 1073741824);
        } elseif ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        } elseif ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        }

        return $bytes.' B';
    }
}
