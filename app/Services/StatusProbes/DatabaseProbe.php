<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;
use Illuminate\Support\Facades\DB;

class DatabaseProbe implements ServiceProbeInterface
{
    public function identifier(): string
    {
        return 'database';
    }

    public function probe(): ProbeResult
    {
        try {
            $start = hrtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            return new ProbeResult(
                ok: true,
                responseTimeMs: $elapsed,
                impact: null,
                reason: 'Connected',
            );
        } catch (\Throwable $e) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: 'Database unreachable: '.\Str::limit($e->getMessage(), 120),
            );
        }
    }
}
