<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

use App\Models\Settings;
use App\Services\Runners\PostProcessRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdditionalProcessingDiagnostics
{
    private const int OVERSIZED_IN_FLIGHT_THRESHOLD = 500;

    private const int OVERSIZED_THREAD_THRESHOLD = 32;

    /**
     * @var list<string>
     */
    private const array REQUIRED_CLAIM_INDEX_COLUMNS = [
        'passwordstatus',
        'haspreview',
        'nzbstatus',
        'leftguid',
        'postdate',
        'id',
        'additional_pp_claimed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $warnings = [];
        $releaseTimeout = $this->setting('releaseprocessingtimeout', 120);
        $threads = max(1, $this->setting('postthreads', 1));
        $batchSize = max(1, $this->setting('maxaddprocessed', 25));
        $childTimeout = (int) (config('nntmux.concurrency_timeout')
            ?? config('nntmux.multiprocessing_max_child_time', 1800));
        $claimTtl = AdditionalCandidateQuery::claimTtlSeconds();
        $backlog = $this->backlog($warnings);
        $indexes = $this->indexes($warnings);
        $tempPath = $this->tempPath($warnings);
        $maximumClaimLifetime = min(
            max(1, $childTimeout),
            max(1, $releaseTimeout) * $batchSize,
        );

        if ($backlog['stale_claims'] > 0) {
            $warnings[] = $this->warning(
                'stale-claims',
                $backlog['stale_claims'].' additional-processing claim(s) are stale and eligible for recovery.',
            );
        }

        if ($claimTtl <= $maximumClaimLifetime) {
            $warnings[] = $this->warning(
                'claim-ttl',
                'Claim TTL ('.$claimTtl.'s) does not exceed the estimated claimed-batch lifetime ('
                .$maximumClaimLifetime.'s); another worker may reclaim active work.',
            );
        }

        $maxInFlight = $threads * $batchSize;
        if ($threads > self::OVERSIZED_THREAD_THRESHOLD || $maxInFlight > self::OVERSIZED_IN_FLIGHT_THRESHOLD) {
            $warnings[] = $this->warning(
                'oversized-capacity',
                'postthreads='.$threads.' and maxaddprocessed='.$batchSize.' allow up to '.$maxInFlight
                .' claimed releases at once; reduce threads or batch size and benchmark end-to-end throughput.',
            );
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'database_driver' => DB::getDriverName(),
            'backlog' => $backlog,
            'settings' => [
                'pipeline' => 'v2',
                'postthreads' => $threads,
                'maxaddprocessed' => $batchSize,
                'releaseprocessingtimeout' => $releaseTimeout,
                'maxpptimeoutcount' => $this->setting('maxpptimeoutcount', 3),
                'worker_max_batches' => PostProcessRunner::ADDITIONAL_WORKER_MAX_BATCHES,
                'multiprocessing_child_timeout' => $childTimeout,
            ],
            'claims' => [
                'ttl_seconds' => $claimTtl,
                'stale_before' => AdditionalCandidateQuery::claimStaleBefore()->toIso8601String(),
                'estimated_claimed_batch_lifetime_seconds' => $maximumClaimLifetime,
            ],
            'capacity' => [
                'threads' => $threads,
                'releases_per_batch' => $batchSize,
                'max_in_flight' => $maxInFlight,
                'max_per_worker_lifetime' => $batchSize * PostProcessRunner::ADDITIONAL_WORKER_MAX_BATCHES,
            ],
            'indexes' => $indexes,
            'temp_path' => $tempPath,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array{code: string, message: string}>  $warnings
     * @return array{total: int, available: int, active_claims: int, stale_claims: int, buckets: list<array{bucket: string, total: int, available: int}>}
     */
    private function backlog(array &$warnings): array
    {
        $empty = [
            'total' => 0,
            'available' => 0,
            'active_claims' => 0,
            'stale_claims' => 0,
            'buckets' => [],
        ];

        if (! Schema::hasTable('releases') || ! Schema::hasTable('categories')) {
            $warnings[] = $this->warning(
                'missing-processing-tables',
                'The releases or categories table is missing; backlog health cannot be inspected.',
            );

            return $empty;
        }

        $counts = AdditionalCandidateQuery::backlogCounts();
        $activeClaims = 0;
        $staleClaims = 0;

        if (AdditionalCandidateQuery::supportsClaims()) {
            $claimQuery = AdditionalCandidateQuery::baseBuilder(includeClaimed: true)
                ->whereNotNull('r.'.AdditionalCandidateQuery::CLAIMED_AT_COLUMN);

            $activeClaims = (clone $claimQuery)
                ->where('r.'.AdditionalCandidateQuery::CLAIMED_AT_COLUMN, '>=', AdditionalCandidateQuery::claimStaleBefore())
                ->count('r.id');
            $staleClaims = (clone $claimQuery)
                ->where('r.'.AdditionalCandidateQuery::CLAIMED_AT_COLUMN, '<', AdditionalCandidateQuery::claimStaleBefore())
                ->count('r.id');
        }

        return [
            ...$counts,
            'active_claims' => $activeClaims,
            'stale_claims' => $staleClaims,
            'buckets' => AdditionalCandidateQuery::bucketBacklog(),
        ];
    }

    /**
     * @param  list<array{code: string, message: string}>  $warnings
     * @return array{required_columns: list<string>, present: list<string>, claim_queue_index: string|null, missing: bool}
     */
    private function indexes(array &$warnings): array
    {
        $present = [];
        $claimQueueIndex = null;

        if (Schema::hasTable('releases')) {
            foreach (Schema::getIndexes('releases') as $index) {
                $name = (string) ($index['name'] ?? '');
                $columns = array_map(
                    static fn (mixed $column): string => strtolower((string) $column),
                    is_array($index['columns'] ?? null) ? $index['columns'] : [],
                );

                if ($name !== '') {
                    $present[] = $name;
                }
                if ($columns === self::REQUIRED_CLAIM_INDEX_COLUMNS) {
                    $claimQueueIndex = $name;
                }
            }
        }

        sort($present);
        $missing = $claimQueueIndex === null;
        if ($missing) {
            $warnings[] = $this->warning(
                'missing-index',
                'The releases claim-queue index is missing or has the wrong column order; run pending migrations before processing.',
            );
        }

        return [
            'required_columns' => self::REQUIRED_CLAIM_INDEX_COLUMNS,
            'present' => $present,
            'claim_queue_index' => $claimQueueIndex,
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<array{code: string, message: string}>  $warnings
     * @return array{path: string, exists: bool, writable: bool}
     */
    private function tempPath(array &$warnings): array
    {
        $path = rtrim((string) config('nntmux.tmp_unrar_path'), '/\\');
        $exists = $path !== '' && is_dir($path);
        $writable = $exists ? is_writable($path) : $this->nearestExistingDirectoryIsWritable($path);

        if (! $writable) {
            $warnings[] = $this->warning(
                'unwritable-temp-path',
                'Additional-processing temp path '.$path.' is not writable and cannot be created by the current user.',
            );
        }

        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $writable,
        ];
    }

    private function nearestExistingDirectoryIsWritable(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $candidate = $path;
        while (! is_dir($candidate)) {
            $parent = dirname($candidate);
            if ($parent === $candidate) {
                return false;
            }
            $candidate = $parent;
        }

        return is_writable($candidate);
    }

    private function setting(string $name, int $default): int
    {
        $value = Settings::settingValue($name);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    /**
     * @return array{code: string, message: string}
     */
    private function warning(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
