<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxMonitorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class TmuxMonitorServiceTest extends TestCase
{
    public function test_calculate_statistics_backfills_missing_work_count_defaults(): void
    {
        $reflection = new ReflectionClass(TmuxMonitorService::class);
        /** @var TmuxMonitorService $monitor */
        $monitor = $reflection->newInstanceWithoutConstructor();

        $runVar = [
            'counts' => [
                'now' => [
                    'processnfo' => 2,
                    'tv' => 1,
                ],
                'start' => [],
                'diff' => [],
                'percent' => [],
            ],
        ];

        $runVarProperty = new ReflectionProperty(TmuxMonitorService::class, 'runVar');
        $runVarProperty->setValue($monitor, $runVar);

        $iterationsProperty = new ReflectionProperty(TmuxMonitorService::class, 'iterations');
        $iterationsProperty->setValue($monitor, 1);

        $calculate = new ReflectionMethod(TmuxMonitorService::class, 'calculateStatistics');
        $calculate->invoke($monitor);

        $updatedRunVar = $runVarProperty->getValue($monitor);

        $this->assertSame(0, $updatedRunVar['counts']['now']['work']);
        $this->assertSame(0, $updatedRunVar['counts']['now']['work_available']);
        $this->assertSame('0', $updatedRunVar['counts']['diff']['work']);
        $this->assertSame('0', $updatedRunVar['counts']['diff']['work_available']);
        $this->assertSame(2, $updatedRunVar['counts']['now']['total_work']);
    }

    #[DataProvider('refreshScheduleProvider')]
    public function test_refresh_schedule(float $lastRefreshAt, int $interval, float $now, bool $expected): void
    {
        $reflection = new ReflectionClass(TmuxMonitorService::class);
        $monitor = $reflection->newInstanceWithoutConstructor();
        $refreshIsDue = new ReflectionMethod(TmuxMonitorService::class, 'refreshIsDue');

        $this->assertSame($expected, $refreshIsDue->invoke($monitor, $lastRefreshAt, $interval, $now));
    }

    /**
     * @return array<string, array{float, int, float, bool}>
     */
    public static function refreshScheduleProvider(): array
    {
        return [
            'first refresh' => [0.0, 60, 100.0, true],
            'before interval' => [100.0, 60, 159.999, false],
            'at interval' => [100.0, 60, 160.0, true],
        ];
    }

    public function test_collection_table_estimates_do_not_contribute_to_release_total(): void
    {
        $reflection = new ReflectionClass(TmuxMonitorService::class);
        $monitor = $reflection->newInstanceWithoutConstructor();
        $aggregate = new ReflectionMethod(TmuxMonitorService::class, 'aggregateTableRowEstimates');

        $counts = $aggregate->invoke($monitor, [
            (object) ['name' => 'collections', 'row_count' => 242650],
            (object) ['name' => 'binaries', 'row_count' => 120],
            (object) ['name' => 'multigroup_parts_1', 'row_count' => 80],
            (object) ['name' => 'missed_parts', 'row_count' => 5],
        ]);

        $this->assertSame([
            'binaries_table' => 120,
            'parts_table' => 80,
            'missed_parts_table' => 5,
        ], $counts);
        $this->assertArrayNotHasKey('releases', $counts);
    }

    public function test_connection_counts_are_derived_from_one_socket_snapshot(): void
    {
        $reflection = new ReflectionClass(Tmux::class);
        /** @var Tmux $tmux */
        $tmux = $reflection->newInstanceWithoutConstructor();
        $snapshot = implode("\n", [
            'ESTAB 0 0 10.0.0.2:40000 192.0.2.10:119',
            'CLOSE-WAIT 0 0 10.0.0.2:40001 192.0.2.10:119',
            'ESTAB 0 0 10.0.0.2:40002 192.0.2.20:563',
        ]);
        $connections = [
            'ip' => '192.0.2.10',
            'port' => 119,
            'ip_a' => '192.0.2.20',
            'port_a' => 563,
        ];

        $this->assertSame(
            ['primary' => ['active' => 1, 'total' => 2]],
            $tmux->getUSPConnections('primary', $connections, $snapshot),
        );
        $this->assertSame(
            ['alternate' => ['active' => 1, 'total' => 1]],
            $tmux->getUSPConnections('alternate', $connections, $snapshot),
        );
    }
}
