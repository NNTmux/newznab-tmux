<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Collectors;

use App\Services\Monitoring\Prometheus\MetricFamily;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReleaseVerificationCollector implements MetricsCollector
{
    public function collect(): iterable
    {
        if (! Schema::hasTable('release_verification_candidates')) {
            return;
        }
        $family = MetricFamily::gauge('nntmux_release_verification_candidates', 'Candidates by verification outcome.');
        foreach (DB::table('release_verification_candidates')->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get() as $row) {
            $family->add((int) $row->total, ['status' => $row->status]);
        }
        yield $family;
        yield MetricFamily::gauge('nntmux_release_verification_pending_bytes', 'Compressed pending manifest bytes.')
            ->add((int) DB::table('release_verification_state')->where('id', 1)->value('pending_bytes'));
        $snapshotPath = (string) config('release_verification.storage_path').'/status.json';
        [$snapshot, $measuredAt] = $this->snapshot($snapshotPath);
        $scratchBytes = $snapshot['scratch_bytes'] ?? null;
        $scratchKnown = $measuredAt !== null && ($snapshot['scratch_bytes_status'] ?? null) !== 'unknown'
            && is_numeric($scratchBytes) && (float) $scratchBytes >= 0;
        yield MetricFamily::gauge('nntmux_release_verification_scratch_bytes_measurement_available', 'Whether a cached scratch measurement is available; not a live disk-usage reading.')
            ->add($scratchKnown ? 1 : 0);
        if ($scratchKnown) {
            yield MetricFamily::gauge('nntmux_release_verification_scratch_bytes', 'Last cached scratch measurement; consult its measurement age.')
                ->add((float) $scratchBytes);
            yield MetricFamily::gauge('nntmux_release_verification_scratch_bytes_measurement_age_seconds', 'Age of the cached scratch measurement.')
                ->add(max(0, now()->timestamp - $measuredAt));
        }
        yield MetricFamily::gauge('nntmux_release_verification_verified_bytes_per_second', 'Latest bounded verifier measurement.')
            ->add(is_numeric($snapshot['verified_bytes_per_second'] ?? null) ? (float) $snapshot['verified_bytes_per_second'] : 0);
        $oldest = DB::table('release_verification_candidates')->whereNull('release_id')->whereIn('status', ['pending', 'unknown', 'verifying', 'dirty', 'unrepairable'])->min('last_unique_at');
        yield MetricFamily::gauge('nntmux_release_verification_backlog_age_seconds', 'Age of the oldest unverified candidate.')
            ->add($oldest === null ? 0 : max(0, now()->timestamp - strtotime($oldest)));
    }

    /** @return array{array<string, mixed>, int|null} */
    private function snapshot(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [[], null];
        }
        try {
            $body = stream_get_contents($handle, 16385);
            $stat = fstat($handle);
            if ($body === false || strlen($body) > 16384 || $stat === false) {
                return [[], null];
            }
            $snapshot = json_decode($body, true);

            return [is_array($snapshot) ? $snapshot : [], (int) $stat['mtime']];
        } finally {
            fclose($handle);
        }
    }
}
