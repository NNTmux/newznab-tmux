<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;

class DiskProbe implements ServiceProbeInterface
{
    public function identifier(): string
    {
        return 'disk';
    }

    public function probe(): ProbeResult
    {
        $mountPoints = (array) config('status-probes.disk.mount_points', ['/']);
        $warningThresholdGb = (float) config('status-probes.disk.warning_threshold_gb', 5.0);
        $criticalThresholdGb = (float) config('status-probes.disk.critical_threshold_gb', 1.0);

        $lowestFreeGb = null;
        $worstMount = null;

        foreach ($mountPoints as $mountPoint) {
            $freeBytes = @disk_free_space((string) $mountPoint);
            if ($freeBytes === false) {
                return new ProbeResult(
                    ok: false,
                    responseTimeMs: 0,
                    impact: IncidentImpactEnum::Major,
                    reason: sprintf('Unable to read disk usage for mount "%s"', $mountPoint),
                );
            }

            $freeGb = round(((float) $freeBytes) / 1_073_741_824, 2);

            if ($lowestFreeGb === null || $freeGb < $lowestFreeGb) {
                $lowestFreeGb = $freeGb;
                $worstMount = (string) $mountPoint;
            }
        }

        if ($lowestFreeGb === null || $worstMount === null) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Major,
                reason: 'No disk mount points configured',
            );
        }

        if ($lowestFreeGb <= $criticalThresholdGb) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Critical,
                reason: sprintf('Low disk space on %s (%.2f GB free)', $worstMount, $lowestFreeGb),
            );
        }

        if ($lowestFreeGb <= $warningThresholdGb) {
            return new ProbeResult(
                ok: false,
                responseTimeMs: 0,
                impact: IncidentImpactEnum::Minor,
                reason: sprintf('Disk space warning on %s (%.2f GB free)', $worstMount, $lowestFreeGb),
            );
        }

        return new ProbeResult(
            ok: true,
            responseTimeMs: 0,
            impact: null,
            reason: sprintf('Disk healthy (lowest: %s %.2f GB free)', $worstMount, $lowestFreeGb),
        );
    }
}
