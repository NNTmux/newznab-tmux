<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\NNTP\NNTPService;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;

class NntpProbe implements ServiceProbeInterface
{
    public function __construct(
        private readonly NNTPService $nntp,
    ) {}

    public function identifier(): string
    {
        return 'nntp';
    }

    public function probe(): ProbeResult
    {
        $checkAlternate = (bool) config('status-probes.nntp.check_alternate', false);

        try {
            $primary = $this->probeConnection(false);
            if (! $primary['ok']) {
                return new ProbeResult(
                    ok: false,
                    responseTimeMs: 0,
                    impact: IncidentImpactEnum::Critical,
                    reason: 'Primary NNTP failed: '.$primary['reason'],
                );
            }

            if ($checkAlternate) {
                $alternate = $this->probeConnection(true);
                if (! $alternate['ok']) {
                    return new ProbeResult(
                        ok: false,
                        responseTimeMs: 0,
                        impact: IncidentImpactEnum::Major,
                        reason: 'Alternate NNTP failed: '.$alternate['reason'],
                    );
                }
            }

            return new ProbeResult(
                ok: true,
                responseTimeMs: (int) $primary['responseTimeMs'],
                impact: null,
                reason: $checkAlternate ? 'Primary and alternate NNTP connected' : 'Primary NNTP connected',
            );
        } catch (\Throwable $e) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: 'NNTP probe failed: '.\Str::limit($e->getMessage(), 120),
            );
        } finally {
            $this->nntp->doQuit();
        }
    }

    /**
     * @return array{ok: bool, reason: string, responseTimeMs: int}
     */
    private function probeConnection(bool $alternate): array
    {
        $start = hrtime(true);
        $result = $this->nntp->doConnect(compression: false, alternate: $alternate);
        $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

        if ($result === true) {
            return ['ok' => true, 'reason' => 'Connected', 'responseTimeMs' => $elapsed];
        }

        if (NNTPService::isError($result)) {
            return ['ok' => false, 'reason' => (string) $result->getMessage(), 'responseTimeMs' => $elapsed];
        }

        return ['ok' => false, 'reason' => 'Unknown connection result', 'responseTimeMs' => $elapsed];
    }
}
