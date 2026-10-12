<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Release;
use App\Models\ReleaseFingerprintMetadata;
use App\Services\Releases\Fingerprint\ReleaseFingerprintCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class CollectReleaseFingerprintMetadata implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public string $connection = 'redis';

    public function __construct(private readonly int $releaseId) {}

    public function uniqueId(): string
    {
        return (string) $this->releaseId;
    }

    public function handle(ReleaseFingerprintCollector $collector): void
    {
        $release = Release::query()->find($this->releaseId);
        if ($release === null) {
            return;
        }

        $record = ReleaseFingerprintMetadata::query()->firstOrCreate(
            ['releases_id' => $this->releaseId],
            ['status' => 'pending'],
        );
        $metadataSchemaVersion = (int) ($record->metadata['schema_version'] ?? 0);
        if ($record->status === 'complete' && $metadataSchemaVersion >= ReleaseFingerprintCollector::SCHEMA_VERSION) {
            return;
        }
        $record->update(['status' => 'processing', 'last_error' => null]);

        try {
            $result = $collector->collect($release);
            $encodedMetadata = json_encode($result['data'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
            $safeMetadata = json_decode($encodedMetadata, true, 512, JSON_THROW_ON_ERROR);
            $record->update([
                'status' => $result['status'],
                'metadata' => $safeMetadata,
                'last_error' => $result['status'] === 'unavailable' ? ($result['data']['reason'] ?? 'NZB unavailable.') : null,
                'collected_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'last_error' => class_basename($exception),
                'collected_at' => now(),
            ]);
            throw $exception;
        }
    }
}
