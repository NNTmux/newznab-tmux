<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Services\StatusProbes\Contracts\ServiceProbeInterface;
use App\Services\StatusProbes\DatabaseProbe;
use App\Services\StatusProbes\DiskProbe;
use App\Services\StatusProbes\NntpProbe;
use App\Services\StatusProbes\QueueProbe;
use App\Services\StatusProbes\RedisProbe;
use App\Services\StatusProbes\SearchProbe;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class RuntimeProbeRunner
{
    /**
     * @var array<string, class-string<ServiceProbeInterface>>
     */
    private const array PROBES = [
        'database' => DatabaseProbe::class,
        'disk' => DiskProbe::class,
        'nntp' => NntpProbe::class,
        'queue' => QueueProbe::class,
        'redis' => RedisProbe::class,
        'search' => SearchProbe::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly SensitiveValueRedactor $redactor,
    ) {}

    /**
     * @param  list<string>  $identifiers
     * @return list<array<string, mixed>>
     */
    public function run(array $identifiers): array
    {
        $startedAt = hrtime(true);
        $results = [];

        foreach (array_values(array_unique($identifiers)) as $identifier) {
            $probe = $this->container->make(self::PROBES[$identifier]);
            $result = $probe->probe();
            $results[] = [
                'id' => $identifier,
                'ok' => $result->ok,
                'latency_ms' => $result->responseTimeMs,
                'impact' => $result->impact?->value,
                'reason' => $this->redactor->redact($result->reason),
                'metadata' => $this->redactor->redactValue($result->metadata),
            ];
        }

        Log::channel('nntmux_mcp')->info('MCP tool completed', [
            'tool' => 'probe-runtime',
            'safe_paths' => [],
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'status' => array_all($results, fn (array $result): bool => $result['ok']) ? 'passed' : 'failed',
            'truncated' => false,
        ]);

        return $results;
    }
}
