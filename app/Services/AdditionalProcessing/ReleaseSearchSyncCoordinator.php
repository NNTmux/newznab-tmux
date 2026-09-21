<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Facades\Search;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use Closure;

final class ReleaseSearchSyncCoordinator
{
    private ?int $releaseId = null;

    private bool $pending = false;

    /** @var Closure(int): void */
    private readonly Closure $synchronize;

    /**
     * @param  (Closure(int): void)|null  $synchronize
     */
    public function __construct(
        private readonly PersistenceMetricsCollector $metrics,
        ?Closure $synchronize = null,
        private readonly bool $coalesce = true,
    ) {
        $this->synchronize = $synchronize ?? static function (int $releaseId): void {
            Search::updateRelease($releaseId);
        };
    }

    public function beginReleaseScope(int $releaseId): void
    {
        if (! $this->coalesce) {
            return;
        }

        $this->releaseId = $releaseId;
        $this->pending = false;
    }

    public function request(int $releaseId): void
    {
        $this->metrics->recordSearchSyncRequest($releaseId);

        if ($this->coalesce && $this->releaseId === $releaseId) {
            $this->pending = true;

            return;
        }

        $this->execute($releaseId);
    }

    public function discard(int $releaseId): void
    {
        if ($this->coalesce && $this->releaseId === $releaseId) {
            $this->pending = false;
        }
    }

    public function finishReleaseScope(): void
    {
        if (! $this->coalesce) {
            return;
        }

        $releaseId = $this->releaseId;
        $pending = $this->pending;
        $this->releaseId = null;
        $this->pending = false;

        if ($releaseId !== null && $pending) {
            $this->execute($releaseId);
        }
    }

    private function execute(int $releaseId): void
    {
        $this->metrics->recordSearchSyncExecution($releaseId);
        ($this->synchronize)($releaseId);
    }
}
