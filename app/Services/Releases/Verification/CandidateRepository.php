<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\CollectionCleanupService;
use App\Services\Nzb\NzbService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class CandidateRepository
{
    public function __construct(private readonly ManifestStore $store, private readonly CollectionCleanupService $cleanup) {}

    public function archive(int $collectionId): void
    {
        app(VerificationResetService::class)->withAdmission(function () use ($collectionId): void {
            $currentReads = DB::transactionLevel() > 0;
            $hash = DB::table('collections')->where('id', $collectionId)->value('collectionhash');
            if (! is_string($hash)) {
                return;
            }
            DB::transaction(function () use ($collectionId, $hash, $currentReads): void {
                $collection = $this->lockArchiveCollection($collectionId, $hash);
                if ($collection === null) {
                    return;
                }
                if ((int) $collection->filecheck === 4
                    && ! $this->cleanup->verificationEligibleCollections()->where('collections.id', $collectionId)->exists()) {
                    return;
                }
                $state = DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
                if ($state === null) {
                    throw new RuntimeException('Verification migration has not initialized capacity state.');
                }
                $sourceIdentity = bin2hex($collection->collectionhash);
                $existing = DB::table('release_verification_candidates')->where('source_identity', $sourceIdentity)
                    ->orderByDesc('id')->lockForUpdate()->first();
                if ($existing !== null && $existing->status === 'filtered') {
                    if ($existing->release_id !== null || (int) $existing->groups_id !== (int) $collection->groups_id
                        || $existing->manifest_path !== '' || (int) $existing->manifest_bytes !== 0) {
                        return;
                    }
                    $usablePart = DB::table('binaries as b')->join('parts as p', 'p.binaries_id', '=', 'b.id')
                        ->where('b.collections_id', $collectionId)->where('p.size', '>', 0)->where('p.messageid', '<>', '')
                        ->lockForUpdate()->first(['p.binaries_id']);
                    if ($usablePart !== null) {
                        $this->cleanup->deleteCollectionsAndDescendants([$collectionId], 'Policy-filtered replay', false, manifestArchived: true);
                    }

                    return;
                }
                $identity = $existing->identity ?? $sourceIdentity;
                if ($existing !== null && $existing->release_id !== null) {
                    $identity = sha1($sourceIdentity.':generation:'.$existing->id);
                    $existing = null;
                }
                $manifest = $this->snapshot($collection, $currentReads);
                $newArticles = true;
                if ($existing !== null && $existing->manifest_path !== '') {
                    $oldManifest = $this->store->read($existing->manifest_path);
                    $manifest = $this->merge($oldManifest, $manifest);
                    $newArticles = $this->articleIdentity($oldManifest) !== $this->articleIdentity($manifest);
                }
                $path = $this->store->write($manifest);
                $size = filesize($this->store->path($path));
                if ($size === false) {
                    throw new RuntimeException('Cannot reserve manifest storage.');
                }
                $arrivalAt = Carbon::parse($collection->last_seen_at ?? $collection->dateadded ?? $collection->added ?? now())->format('Y-m-d H:i:s');
                $lastUniqueAt = $existing === null ? $arrivalAt : ($newArticles ? max($arrivalAt, $existing->last_unique_at) : $existing->last_unique_at);
                $expired = $lastUniqueAt < now()->subHours((int) config('release_verification.expiry_hours'))->format('Y-m-d H:i:s');
                $pending = (int) $state->pending_bytes - (int) ($existing->manifest_bytes ?? 0) + ($expired ? 0 : $size);
                if ($pending > (int) config('release_verification.pending_bytes')) {
                    if ($existing === null || $existing->manifest_path !== $path) {
                        $this->store->deleteIfUnreferenced($path);
                    }
                    throw new RuntimeException('Pending verification capacity is full; original headers retained.');
                }
                $changed = $existing === null || $existing->manifest_path !== $path;
                $dirty = $existing !== null && $existing->status === 'dirty';
                $values = ['identity' => $identity, 'source_identity' => $sourceIdentity, 'groups_id' => (int) $collection->groups_id, 'manifest_path' => $path,
                    'manifest_bytes' => $size, 'manifest_hash' => substr($path, 0, 64)];
                if ($changed || $dirty) {
                    $values += ['version' => (int) ($existing->version ?? 0) + 1, 'last_unique_at' => $lastUniqueAt,
                        'next_attempt_at' => now(), 'status' => 'pending', 'lease_token' => null, 'lease_until' => null, 'reason' => null];
                }
                if ($expired) {
                    $values = array_replace($values, ['status' => 'expired', 'last_unique_at' => $lastUniqueAt,
                        'manifest_path' => '', 'manifest_bytes' => 0, 'lease_token' => null, 'lease_until' => null]);
                }
                if ($existing === null) {
                    DB::table('release_verification_candidates')->insert($values);
                } else {
                    DB::table('release_verification_candidates')->where('id', $existing->id)->update($values);
                }
                DB::table('release_verification_state')->where('id', 1)->update(['pending_bytes' => $pending]);
                $this->cleanup->deleteCollectionsAndDescendants([$collectionId], 'Archived for verification', false, manifestArchived: true);
                if ($expired) {
                    DB::afterCommit(function () use ($path): void {
                        $this->store->deleteIfUnreferenced($path);
                    });
                }
                if ($existing !== null && $existing->manifest_path !== $path) {
                    DB::afterCommit(function () use ($existing): void {
                        $this->store->deleteIfUnreferenced($existing->manifest_path);
                    });
                }
            }, 5);
        });
    }

    /** Lock through ingestion's unique hash index before taking the shared capacity row. */
    private function lockArchiveCollection(int $collectionId, string $hash): ?object
    {
        $query = DB::table('collections')->where('collectionhash', $hash)->where('id', $collectionId);
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->forceIndex('ix_collection_collectionhash')->lock('for update skip locked');
        } else {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @param array<string, mixed> $manifest */
    public function enqueue(array $manifest): void
    {
        app(VerificationResetService::class)->withAdmission(function () use ($manifest): void {
            DB::transaction(function () use ($manifest): void {
                $state = DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
                $identity = (string) $manifest['identity'];
                $existing = DB::table('release_verification_candidates')->where('identity', $identity)->lockForUpdate()->first();
                if ($existing !== null && ($existing->release_id === null
                    || DB::table('releases')->where('id', $existing->release_id)->lockForUpdate()->first(['id']) !== null)) {
                    return;
                }
                if ($state === null) {
                    throw new RuntimeException('Verification migration has not initialized capacity state.');
                }
                $path = $this->store->write($manifest);
                $bytes = (int) filesize($this->store->path($path));
                $pending = (int) $state->pending_bytes - (int) ($existing->manifest_bytes ?? 0) + $bytes;
                if ($pending > (int) config('release_verification.pending_bytes')) {
                    if ($existing === null || $existing->manifest_path !== $path) {
                        $this->store->deleteIfUnreferenced($path);
                    }
                    throw new RuntimeException('Candidate capacity is full; import retained for retry.');
                }
                $values = ['identity' => $identity, 'source_identity' => $identity,
                    'groups_id' => (int) $manifest['groups_id'], 'manifest_path' => $path, 'manifest_bytes' => $bytes,
                    'manifest_hash' => substr($path, 0, 64), 'last_unique_at' => now(), 'next_attempt_at' => now(),
                    'version' => (int) ($existing->version ?? 0) + 1, 'status' => 'pending', 'reason' => null,
                    'release_id' => null, 'verified_at' => null, 'lease_token' => null, 'lease_until' => null];
                if ($existing === null) {
                    DB::table('release_verification_candidates')->insert($values);
                } else {
                    DB::table('release_verification_candidates')->where('id', $existing->id)->update($values);
                    if ($existing->manifest_path !== '' && $existing->manifest_path !== $path) {
                        DB::afterCommit(function () use ($existing): void {
                            $this->store->deleteIfUnreferenced($existing->manifest_path);
                        });
                    }
                }
                DB::table('release_verification_state')->where('id', 1)->update(['pending_bytes' => $pending]);
            }, 5);
        });
    }

    /** @param list<int> $ids */
    public function invalidateHotCollections(array $ids): void
    {
        foreach (DB::table('collections')->whereIn('id', $ids)->orderBy('id')->get() as $collection) {
            $identity = bin2hex($collection->collectionhash);
            $candidates = DB::table('release_verification_candidates')->where('source_identity', $identity)
                ->whereNull('release_id')->whereNotIn('status', ['expired'])->lockForUpdate()->get();
            foreach ($candidates as $candidate) {
                if ($candidate->manifest_path === '') {
                    continue;
                }
                $old = $this->store->read($candidate->manifest_path);
                $merged = $this->merge($old, $this->snapshot($collection));
                if ($this->articleIdentity($old) === $this->articleIdentity($merged) && ! ($merged['conflict'] ?? false)) {
                    continue;
                }
                DB::table('release_verification_candidates')->where('id', $candidate->id)->update([
                    'status' => 'dirty', 'lease_token' => null, 'lease_until' => null, 'version' => $candidate->version + 1,
                    'last_unique_at' => $this->articleIdentity($old) !== $this->articleIdentity($merged) ? now() : $candidate->last_unique_at]);
                DB::table('collections')->where('id', $collection->id)->where('filecheck', '!=', 4)->update(['filecheck' => 3]);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function articleIdentity(array $manifest): string
    {
        $ids = [];
        foreach ($manifest['files'] as $file) {
            foreach ($file['segments'] as $part) {
                $ids[] = $file['key'].':'.$part['number'].':'.$part['messageid'].':'.$part['bytes'];
            }
        }
        sort($ids);

        return hash('sha256', implode("\n", $ids));
    }

    public function capacityAvailable(): bool
    {
        if (! config('release_verification.required', true) || ! Schema::hasTable('release_verification_state')) {
            return true;
        }

        $capacity = (int) config('release_verification.pending_bytes');
        $reserve = min(33554432, (int) ($capacity / 10));

        return (int) DB::table('release_verification_state')->where('id', 1)->value('pending_bytes') < $capacity - $reserve;
    }

    /** @return array<string, mixed> */
    private function snapshot(object $collection, bool $currentReads = false): array
    {
        $groups = Schema::hasTable('collection_groups')
            ? $this->snapshotQuery('collection_groups', $currentReads)->where('collections_id', $collection->id)->pluck('group_name')->all() : [];
        if ($groups === []) {
            $groups = [(string) $this->snapshotQuery('usenet_groups', $currentReads)->where('id', $collection->groups_id)->value('name')];
        }
        sort($groups);
        $files = [];
        $metadataBytes = 0;
        $binaries = $this->snapshotQuery('binaries', $currentReads)->where('collections_id', $collection->id)->orderBy('binaryhash')
            ->select(['id', 'binaryhash', 'name', 'totalparts'])->cursor();
        foreach ($binaries->chunk(100) as $batch) {
            $segmentsByBinary = [];
            foreach ($this->snapshotQuery('parts', $currentReads)->whereIn('binaries_id', $batch->pluck('id')->all())
                ->orderBy('binaries_id')->orderBy('partnumber')
                ->select(['binaries_id', 'partnumber', 'messageid', 'size'])->cursor() as $part) {
                $metadataBytes += 128 + strlen($part->messageid);
                if ($metadataBytes > 16777216) {
                    throw new RuntimeException('Candidate exceeds metadata budget; headers retained.');
                }
                $segmentsByBinary[$part->binaries_id][] = ['number' => (int) $part->partnumber,
                    'messageid' => $part->messageid, 'bytes' => (int) $part->size];
            }
            foreach ($batch as $binary) {
                $files[] = ['key' => bin2hex($binary->binaryhash), 'subject' => $binary->name,
                    'totalparts' => (int) $binary->totalparts, 'segments' => $segmentsByBinary[$binary->id] ?? []];
            }
        }

        $expected = (int) $collection->totalfiles;
        if ((int) $collection->filecheck === 4) {
            $expected = 0;
            foreach ($files as $file) {
                if (preg_match('/"[^"\r\n]+"|\'[^\'\r\n]+\'/', $file['subject']) === 1) {
                    $expected = max($expected, NzbManifestReader::declaredFileCount($file['subject']));
                }
            }
        }

        $legacyReleaseId = null;
        if ((int) $collection->filecheck === 4 && isset($collection->releases_id)) {
            $legacyReleaseId = DB::table('releases')->where('id', $collection->releases_id)
                ->where('nzbstatus', NzbService::NZB_NONE)->lockForUpdate()->value('id');
        }

        return ['legacy_release_id' => $legacyReleaseId === null ? null : (int) $legacyReleaseId,
            'identity' => bin2hex($collection->collectionhash), 'subject' => $collection->subject,
            'fromname' => $collection->fromname, 'date' => $collection->date, 'groups_id' => (int) $collection->groups_id,
            // Legacy insertion could overwrite the declared count with observed
            // files. Recover explicit counters outside quoted filenames only.
            'regex_id' => (int) $collection->collection_regexes_id,
            'expected_files' => $expected,
            'groups' => $groups, 'files' => $files, 'conflict' => false];
    }

    /** Current reads avoid a caller-owned repeatable-read snapshot omitting committed headers. */
    private function snapshotQuery(string $table, bool $currentReads): Builder
    {
        $query = DB::table($table);

        return $currentReads ? $query->sharedLock() : $query;
    }

    /** @param array<string, mixed> $old
     * @param  array<string, mixed>  $new
     * @return array<string, mixed>
     */
    private function merge(array $old, array $new): array
    {
        $files = array_column($old['files'], null, 'key');
        foreach ($new['files'] as $file) {
            $key = $file['key'];
            if (! isset($files[$key])) {
                $files[$key] = $file;

                continue;
            }
            $previous = $files[$key];
            if ($previous['subject'] !== $file['subject'] || $previous['totalparts'] !== $file['totalparts']) {
                $old['conflict'] = true;
            }
            $parts = array_column($previous['segments'], null, 'number');
            foreach ($file['segments'] as $segment) {
                if (isset($parts[$segment['number']]) && $parts[$segment['number']] !== $segment) {
                    $old['conflict'] = true;
                } else {
                    $parts[$segment['number']] = $segment;
                }
            }
            ksort($parts);
            $files[$key]['segments'] = array_values($parts);
        }
        ksort($files);
        $old['files'] = array_values($files);
        $old['groups'] = array_values(array_unique(array_merge($old['groups'], $new['groups'])));
        sort($old['groups']);
        $oldExpected = (int) $old['expected_files'];
        $newExpected = (int) $new['expected_files'];
        if ($old['fromname'] !== $new['fromname'] || ($oldExpected > 0 && $newExpected > 0 && $oldExpected !== $newExpected)) {
            $old['conflict'] = true;
        }
        $old['expected_files'] = max(0, $oldExpected, $newExpected);
        if (($new['legacy_release_id'] ?? null) !== null) {
            if (($old['legacy_release_id'] ?? null) !== null && $old['legacy_release_id'] !== $new['legacy_release_id']) {
                $old['conflict'] = true;
            } else {
                $old['legacy_release_id'] = $new['legacy_release_id'];
            }
        }

        return $old;
    }

    public function expire(): void
    {
        $ids = DB::table('release_verification_candidates')->whereNull('release_id')
            ->whereIn('status', ['pending', 'unknown', 'unrepairable', 'verifying', 'dirty'])
            ->where('last_unique_at', '<', now()->subHours((int) config('release_verification.expiry_hours')))
            ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))
            ->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
                $row = DB::table('release_verification_candidates')->where('id', $id)->lockForUpdate()->first();
                if ($row === null || $row->release_id !== null
                    || ! in_array($row->status, ['pending', 'unknown', 'unrepairable', 'verifying', 'dirty'], true)
                    || $row->last_unique_at >= now()->subHours((int) config('release_verification.expiry_hours'))->format('Y-m-d H:i:s')
                    || ($row->lease_until !== null && $row->lease_until >= now()->format('Y-m-d H:i:s'))) {
                    return;
                }
                DB::table('release_verification_state')->where('id', 1)->decrement('pending_bytes', (int) $row->manifest_bytes);
                DB::table('release_verification_candidates')->where('id', $id)->update(['status' => 'expired',
                    'manifest_path' => '', 'manifest_bytes' => 0, 'lease_token' => null, 'lease_until' => null]);
                DB::afterCommit(function () use ($row): void {
                    $this->store->deleteIfUnreferenced($row->manifest_path);
                });
            });
        }
    }

    /**
     * @param  (callable(int): bool)|null  $acquireCandidate
     *
     * @phpstan-impure
     */
    public function claim(?callable $acquireCandidate = null): ?object
    {
        return DB::transaction(function () use ($acquireCandidate): ?object {
            $query = DB::table('release_verification_candidates')->whereNull('release_id')
                ->whereIn('status', ['pending', 'unknown', 'verifying'])->where('next_attempt_at', '<=', now())
                ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<', now()))
                ->orderBy('next_attempt_at')->orderBy('id')->lockForUpdate();
            $rows = $acquireCandidate === null ? [$query->first()] : $query->limit(100)->get();
            foreach ($rows as $row) {
                if ($row === null || ($acquireCandidate !== null && ! $acquireCandidate((int) $row->id))) {
                    continue;
                }
                $token = (string) Str::uuid();
                DB::table('release_verification_candidates')->where('id', $row->id)->update([
                    'status' => 'verifying', 'lease_token' => $token,
                    'lease_until' => now()->addSeconds((int) config('release_verification.lease_seconds')),
                ]);
                $row->lease_token = $token;

                return $row;
            }

            return null;
        }, 5);
    }

    public function heartbeat(int $id, string $token): void
    {
        $changed = DB::table('release_verification_candidates')->where('id', $id)->where('lease_token', $token)
            ->where('lease_until', '>=', now())->update(['lease_until' => now()->addSeconds((int) config('release_verification.lease_seconds'))]);
        if ($changed !== 1 && ! DB::table('release_verification_candidates')->where('id', $id)->where('lease_token', $token)->where('lease_until', '>=', now())->exists()) {
            throw new RuntimeException('Verification lease was lost.');
        }
    }

    public function finish(object $claim, VerificationResult $result): void
    {
        DB::table('release_verification_candidates')->where('id', $claim->id)->where('lease_token', $claim->lease_token)
            ->where('version', $claim->version)->update(['status' => $result->verdict === 'unrepairable' ? 'unrepairable' : 'unknown', 'reason' => mb_strcut($result->reason, 0, 2000, 'UTF-8'),
                'lease_token' => null, 'lease_until' => null, 'next_attempt_at' => now()->addSeconds((int) config('release_verification.retry_seconds'))]);
    }

    public function rejectByPolicy(object $claim, string $reason): void
    {
        DB::transaction(function () use ($claim, $reason): void {
            DB::table('release_verification_state')->where('id', 1)->lockForUpdate()->first();
            $candidate = DB::table('release_verification_candidates')->where('id', $claim->id)
                ->where('lease_token', $claim->lease_token)->where('version', $claim->version)->whereNull('release_id')->lockForUpdate()->first();
            if ($candidate === null) {
                throw new RuntimeException('Stale verification result cannot filter a candidate.');
            }
            DB::table('release_verification_state')->where('id', 1)->decrement('pending_bytes', (int) $candidate->manifest_bytes);
            DB::table('release_verification_candidates')->where('id', $claim->id)->update(['status' => 'filtered',
                'reason' => mb_strcut($reason, 0, 2000, 'UTF-8'), 'lease_token' => null, 'lease_until' => null,
                'manifest_path' => '', 'manifest_bytes' => 0]);
            DB::afterCommit(function () use ($candidate): void {
                $this->store->deleteIfUnreferenced($candidate->manifest_path);
            });
        }, 5);
    }
}
