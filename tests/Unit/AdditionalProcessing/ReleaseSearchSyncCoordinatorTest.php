<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReleaseSearchSyncCoordinatorTest extends TestCase
{
    #[Test]
    public function it_coalesces_multiple_requests_for_one_active_release(): void
    {
        $synchronizedReleaseIds = [];
        $metrics = new PersistenceMetricsCollector;
        $coordinator = new ReleaseSearchSyncCoordinator(
            $metrics,
            static function (int $releaseId) use (&$synchronizedReleaseIds): void {
                $synchronizedReleaseIds[] = $releaseId;
            },
        );

        $metrics->beginReleaseScope(42);
        $coordinator->beginReleaseScope(42);
        $coordinator->request(42);
        $coordinator->request(42);
        $coordinator->request(42);

        $this->assertSame([], $synchronizedReleaseIds);

        $coordinator->finishReleaseScope();
        $persistenceMetrics = $metrics->finishReleaseScope();

        $this->assertSame([42], $synchronizedReleaseIds);
        $this->assertSame(3, $persistenceMetrics->searchSyncRequests);
        $this->assertSame(1, $persistenceMetrics->searchSyncExecutions);
    }

    #[Test]
    public function it_discards_a_pending_sync_when_the_release_is_deleted(): void
    {
        $synchronizedReleaseIds = [];
        $metrics = new PersistenceMetricsCollector;
        $coordinator = new ReleaseSearchSyncCoordinator(
            $metrics,
            static function (int $releaseId) use (&$synchronizedReleaseIds): void {
                $synchronizedReleaseIds[] = $releaseId;
            },
        );

        $metrics->beginReleaseScope(42);
        $coordinator->beginReleaseScope(42);
        $coordinator->request(42);
        $coordinator->discard(42);
        $coordinator->finishReleaseScope();
        $persistenceMetrics = $metrics->finishReleaseScope();

        $this->assertSame([], $synchronizedReleaseIds);
        $this->assertSame(1, $persistenceMetrics->searchSyncRequests);
        $this->assertSame(0, $persistenceMetrics->searchSyncExecutions);
    }

    #[Test]
    public function it_synchronizes_immediately_outside_an_active_release_scope(): void
    {
        $synchronizedReleaseIds = [];
        $coordinator = new ReleaseSearchSyncCoordinator(
            new PersistenceMetricsCollector,
            static function (int $releaseId) use (&$synchronizedReleaseIds): void {
                $synchronizedReleaseIds[] = $releaseId;
            },
        );

        $coordinator->request(7);

        $this->assertSame([7], $synchronizedReleaseIds);
    }

    #[Test]
    public function it_executes_every_request_immediately_when_coalescing_is_disabled(): void
    {
        $synchronizedReleaseIds = [];
        $metrics = new PersistenceMetricsCollector;
        $coordinator = new ReleaseSearchSyncCoordinator(
            $metrics,
            static function (int $releaseId) use (&$synchronizedReleaseIds): void {
                $synchronizedReleaseIds[] = $releaseId;
            },
            coalesce: false,
        );

        $metrics->beginReleaseScope(42);
        $coordinator->beginReleaseScope(42);
        $coordinator->request(42);
        $coordinator->request(42);
        $coordinator->finishReleaseScope();
        $persistenceMetrics = $metrics->finishReleaseScope();

        $this->assertSame([42, 42], $synchronizedReleaseIds);
        $this->assertSame(2, $persistenceMetrics->searchSyncRequests);
        $this->assertSame(2, $persistenceMetrics->searchSyncExecutions);
    }
}
