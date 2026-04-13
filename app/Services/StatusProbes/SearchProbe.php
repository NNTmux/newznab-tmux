<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\Search\SearchService;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;

class SearchProbe implements ServiceProbeInterface
{
    public function __construct(
        private readonly SearchService $searchService,
    ) {}

    public function identifier(): string
    {
        return 'search';
    }

    public function probe(): ProbeResult
    {
        try {
            $start = hrtime(true);
            $available = $this->searchService->isAvailable();
            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            if (! $available) {
                return new ProbeResult(
                    ok: false,
                    responseTimeMs: $elapsed,
                    impact: IncidentImpactEnum::Critical,
                    reason: sprintf('Search driver "%s" unavailable', $this->searchService->getCurrentDriver()),
                );
            }

            return new ProbeResult(
                ok: true,
                responseTimeMs: $elapsed,
                impact: null,
                reason: sprintf('Search driver "%s" available', $this->searchService->getCurrentDriver()),
            );
        } catch (\Throwable $e) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: 'Search check failed: '.\Str::limit($e->getMessage(), 120),
            );
        }
    }
}
