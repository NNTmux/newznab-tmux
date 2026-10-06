<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\TmuxMetricsSnapshot;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TmuxMetricsSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_publishes_a_trimmed_numeric_snapshot_that_can_be_read_back(): void
    {
        $snapshot = app(TmuxMetricsSnapshot::class);

        $snapshot->publish([
            'settings' => ['is_running' => 1, 'monitor' => 10],
            'counts' => [
                'now' => ['processnfo' => 12, 'releases' => '345', 'label' => 'not a number'],
                'diff' => ['processnfo' => '1,000'],
            ],
            'conncounts' => ['primary' => ['active' => 4, 'total' => 20]],
            'timers' => ['query' => ['proc1_time' => 0.5], 'newOld' => ['oldestcollection' => 'x']],
        ], 1_700_000_000);

        $this->assertSame([
            'collected_at' => 1_700_000_000,
            'is_running' => 1,
            'counts' => ['processnfo' => 12, 'releases' => 345],
            'connections' => ['primary' => ['active' => 4, 'total' => 20]],
            'query_timers' => ['proc1_time' => 0.5],
        ], $snapshot->read());
    }

    public function test_read_returns_null_when_nothing_was_published(): void
    {
        $this->assertNull(app(TmuxMetricsSnapshot::class)->read());
    }
}
