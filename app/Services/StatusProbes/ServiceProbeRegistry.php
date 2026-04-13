<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;

class ServiceProbeRegistry
{
    /**
     * @var array<string, ServiceProbeInterface>
     */
    private array $probes;

    public function __construct(
        DatabaseProbe $databaseProbe,
        RedisProbe $redisProbe,
        SearchProbe $searchProbe,
        NntpProbe $nntpProbe,
        QueueProbe $queueProbe,
        DiskProbe $diskProbe,
    ) {
        $this->probes = [
            $databaseProbe->identifier() => $databaseProbe,
            $redisProbe->identifier() => $redisProbe,
            $searchProbe->identifier() => $searchProbe,
            $nntpProbe->identifier() => $nntpProbe,
            $queueProbe->identifier() => $queueProbe,
            $diskProbe->identifier() => $diskProbe,
        ];
    }

    public function run(string $identifier): ProbeResult
    {
        $probe = $this->probes[$identifier] ?? null;
        if ($probe === null) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Major,
                reason: sprintf('Unknown probe identifier "%s"', $identifier),
            );
        }

        return $probe->probe();
    }
}
