<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;
use Illuminate\Contracts\Queue\Factory as QueueFactory;

class QueueProbe implements ServiceProbeInterface
{
    public function __construct(
        private readonly QueueFactory $queue,
    ) {}

    public function identifier(): string
    {
        return 'queue';
    }

    public function probe(): ProbeResult
    {
        try {
            $defaultConnection = (string) config('queue.default', 'sync');
            $start = hrtime(true);
            $queueConnection = $this->queue->connection($defaultConnection);
            $size = method_exists($queueConnection, 'size') ? (int) $queueConnection->size() : null;
            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($defaultConnection === 'sync') {
                return new ProbeResult(
                    ok: true,
                    responseTimeMs: $elapsed,
                    impact: null,
                    reason: 'Queue uses sync driver',
                );
            }

            return new ProbeResult(
                ok: true,
                responseTimeMs: $elapsed,
                impact: null,
                reason: sprintf('Queue "%s" reachable', $defaultConnection),
                metadata: ['queue_size' => $size],
            );
        } catch (\Throwable $e) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: 'Queue probe failed: '.\Str::limit($e->getMessage(), 120),
            );
        }
    }
}
