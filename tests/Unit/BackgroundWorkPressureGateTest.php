<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BackgroundWorkPressureGate;
use App\Services\StatusProbes\DatabaseProbe;
use App\Services\StatusProbes\RedisProbe;
use App\Services\StatusProbes\SearchProbe;
use App\Services\SystemMetricsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BackgroundWorkPressureGateTest extends TestCase
{
    #[Test]
    public function it_pauses_at_the_high_threshold_and_requires_two_healthy_samples_to_resume(): void
    {
        $gate = $this->gate();

        $paused = $gate->nextState($this->snapshot(loadRatio: 0.75), []);
        self::assertTrue($paused['paused']);
        self::assertStringContainsString('load', strtolower($paused['reason']));

        $stillPaused = $gate->nextState($this->snapshot(loadRatio: 0.65), $paused);
        self::assertTrue($stillPaused['paused']);
        self::assertSame(0, $stillPaused['healthy_samples']);

        $recovering = $gate->nextState($this->snapshot(), $stillPaused);
        self::assertTrue($recovering['paused']);
        self::assertSame(1, $recovering['healthy_samples']);

        $resumed = $gate->nextState($this->snapshot(), $recovering);
        self::assertFalse($resumed['paused']);
        self::assertSame(0, $resumed['healthy_samples']);

        $outboxAtResumeBoundary = $gate->nextState($this->snapshot(outboxAgeSeconds: 2), $paused);
        self::assertTrue($outboxAtResumeBoundary['paused']);
        self::assertSame(0, $outboxAtResumeBoundary['healthy_samples']);
    }

    #[Test]
    public function it_pauses_for_dependency_failures_and_stale_search_outbox_rows(): void
    {
        $gate = $this->gate();
        $dependencyFailure = $this->snapshot();
        $dependencyFailure['dependencies']['search']['ok'] = false;
        $dependencyLatency = $this->snapshot();
        $dependencyLatency['dependencies']['database']['latency_ms'] = 100;
        $lowMemory = $this->snapshot();
        $lowMemory['available_memory_percent'] = 15.0;

        self::assertTrue($gate->nextState($dependencyFailure, [])['paused']);
        self::assertTrue($gate->nextState($dependencyLatency, [])['paused']);
        self::assertTrue($gate->nextState($lowMemory, [])['paused']);
        self::assertTrue($gate->nextState($this->snapshot(outboxAgeSeconds: 6), [])['paused']);
    }

    private function gate(): BackgroundWorkPressureGate
    {
        return new BackgroundWorkPressureGate(
            $this->createStub(SystemMetricsService::class),
            $this->createStub(DatabaseProbe::class),
            $this->createStub(SearchProbe::class),
            $this->createStub(RedisProbe::class),
            [
                'pause_load_ratio' => 0.75,
                'resume_load_ratio' => 0.60,
                'pause_available_memory_percent' => 15,
                'resume_available_memory_percent' => 20,
                'pause_dependency_latency_ms' => 100,
                'resume_dependency_latency_ms' => 50,
                'pause_outbox_age_seconds' => 5,
                'resume_outbox_age_seconds' => 2,
                'healthy_samples' => 2,
            ],
        );
    }

    /**
     * @return array{load_ratio: float, available_memory_percent: float, dependencies: array<string, array{ok: bool, latency_ms: int}>, outbox_age_seconds: int}
     */
    private function snapshot(float $loadRatio = 0.50, int $outboxAgeSeconds = 0): array
    {
        return [
            'load_ratio' => $loadRatio,
            'available_memory_percent' => 50.0,
            'dependencies' => [
                'database' => ['ok' => true, 'latency_ms' => 10],
                'search' => ['ok' => true, 'latency_ms' => 10],
                'redis' => ['ok' => true, 'latency_ms' => 10],
            ],
            'outbox_age_seconds' => $outboxAgeSeconds,
        ];
    }
}
