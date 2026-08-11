<?php

declare(strict_types=1);

namespace Tests\Unit\AdditionalProcessing;

use App\Services\AdditionalProcessing\DTO\AdditionalBatchResult;
use App\Services\AdditionalProcessing\DTO\DownloadMetrics;
use App\Services\AdditionalProcessing\DTO\PersistenceMetrics;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingResult;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProcessingResultTest extends TestCase
{
    #[Test]
    public function it_aggregates_typed_release_outcomes(): void
    {
        $batch = new AdditionalBatchResult(
            claimedIds: [10, 11, 12],
            results: [
                new ReleaseProcessingResult(
                    10,
                    'a-guid-10',
                    ProcessingOutcome::Completed,
                    artifactsCreated: true,
                    releaseFilesAdded: 3,
                    elapsedSeconds: 0.5,
                    stageDurations: ['nzb-parsing' => 0.2, 'finalization' => 0.1],
                    downloadMetrics: new DownloadMetrics(2, 1, 1, 100, 100),
                    persistenceMetrics: new PersistenceMetrics(8, 12.5, 2, 1),
                    duplicateMessageIdCount: 1,
                ),
                new ReleaseProcessingResult(
                    11,
                    'a-guid-11',
                    ProcessingOutcome::Passworded,
                    elapsedSeconds: 0.75,
                    stageDurations: ['nzb-parsing' => 0.3],
                    downloadMetrics: new DownloadMetrics(1, 1, 0, 50, 0),
                    persistenceMetrics: new PersistenceMetrics(5, 7.5, 1, 1),
                    unsupportedReasons: ['book-flood'],
                ),
                new ReleaseProcessingResult(
                    12,
                    'a-guid-12',
                    ProcessingOutcome::TimedOut,
                    elapsedSeconds: 1.25,
                    stageDurations: ['nzb-parsing' => 0.5, 'timeout-handling' => 0.05],
                ),
            ],
            elapsedSeconds: 2.0,
            peakMemoryBytes: 32_000_000,
        );

        $this->assertSame(3, $batch->claimedCount());
        $this->assertSame(3, $batch->attemptedCount());
        $this->assertSame(2, $batch->successfulCount());
        $this->assertSame(1, $batch->unsuccessfulCount());
        $this->assertSame([
            'completed' => 1,
            'passworded' => 1,
            'timed-out' => 1,
        ], $batch->outcomeCounts());
        $this->assertTrue($batch->hasOutcome(ProcessingOutcome::TimedOut));
        $this->assertSame(ProcessingOutcome::Completed, $batch->firstResult()?->outcome);
        $this->assertSame(1.5, $batch->releasesPerSecond());
        $this->assertEqualsWithDelta(0.833333, $batch->averageReleaseSeconds(), 0.000001);
        $this->assertSame(1, $batch->artifactsCreatedCount());
        $this->assertSame(50.0, $batch->artifactYieldPercent());
        $this->assertSame(3, $batch->releaseFilesAdded());
        $this->assertEquals(new DownloadMetrics(3, 2, 1, 150, 100), $batch->downloadMetrics());
        $this->assertEquals(new PersistenceMetrics(13, 20.0, 3, 2), $batch->persistenceMetrics());
        $this->assertSame(1, $batch->duplicateMessageIdCount());
        $this->assertSame(['book-flood' => 1], $batch->unsupportedReasonCounts());
        $this->assertSame([
            'nzb-parsing' => 1.0,
            'finalization' => 0.1,
            'timeout-handling' => 0.05,
        ], $batch->stageDurationTotals());
        $this->assertSame(32_000_000, $batch->peakMemoryBytes);
    }

    #[Test]
    public function it_represents_a_worker_setup_failure_without_claiming_releases(): void
    {
        $batch = AdditionalBatchResult::setupFailed('Temporary path is unavailable.');

        $this->assertSame(0, $batch->claimedCount());
        $this->assertSame(0, $batch->attemptedCount());
        $this->assertSame('Temporary path is unavailable.', $batch->setupFailure);
        $this->assertNull($batch->firstResult());
        $this->assertSame(0.0, $batch->releasesPerSecond());
        $this->assertSame(0.0, $batch->artifactYieldPercent());
    }

    #[Test]
    public function completing_without_useful_artifacts_is_a_truthful_successful_outcome(): void
    {
        $result = new ReleaseProcessingResult(
            10,
            'a-guid-10',
            ProcessingOutcome::NoUsefulArtifacts,
            reason: 'Processing completed without creating useful artifacts.',
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->artifactsCreated);
        $this->assertSame('Processing completed without creating useful artifacts.', $result->reason);
    }

    #[Test]
    public function it_can_attach_performance_data_without_changing_the_processing_outcome(): void
    {
        $result = new ReleaseProcessingResult(
            10,
            'a-guid-10',
            ProcessingOutcome::Completed,
            artifactsCreated: true,
            releaseFilesAdded: 2,
        );

        $downloadMetrics = new DownloadMetrics(2, 1, 1, 100, 100);
        $persistenceMetrics = new PersistenceMetrics(4, 3.5, 2, 1);
        $measured = $result->withPerformance(
            1.25,
            ['nzb-parsing' => 0.4],
            $downloadMetrics,
            $persistenceMetrics,
        );

        $this->assertSame($result->releaseId, $measured->releaseId);
        $this->assertSame($result->outcome, $measured->outcome);
        $this->assertTrue($measured->artifactsCreated);
        $this->assertSame(2, $measured->releaseFilesAdded);
        $this->assertSame(1.25, $measured->elapsedSeconds);
        $this->assertSame(['nzb-parsing' => 0.4], $measured->stageDurations);
        $this->assertSame($downloadMetrics, $measured->downloadMetrics);
        $this->assertSame($persistenceMetrics, $measured->persistenceMetrics);
    }
}
