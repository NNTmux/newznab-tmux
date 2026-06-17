<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Models\GdprRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GdprRetentionService
{
    public function __construct(private readonly GdprDataInventory $inventory = new GdprDataInventory) {}

    /**
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        return [
            'export_files' => '7 days after generation, then safe to purge.',
            'gdpr_requests' => 'Retained as an accountability record.',
            'payments' => 'Retained for accounting, tax, fraud prevention, dispute handling, and legal obligations with direct account identifiers anonymized during erasure where practical.',
            'audit' => 'Retained in anonymized or minimized form for security and administrative accountability.',
            'cookies' => 'Essential cookies/storage only; no marketing or analytics cookies are set by GDPR functionality.',
            'retained_records' => $this->inventory->retainedRecords(),
        ];
    }

    public function purgeExpiredExports(): int
    {
        if (! Schema::hasTable('gdpr_requests')) {
            return 0;
        }

        $count = 0;

        GdprRequest::query()
            ->where('type', GdprRequest::TYPE_EXPORT)
            ->whereNotNull('export_path')
            ->whereNotNull('export_expires_at')
            ->where('export_expires_at', '<', now())
            ->chunkById(100, function ($requests) use (&$count): void {
                foreach ($requests as $request) {
                    Storage::disk($request->export_disk ?: 'local')->delete($request->export_path);
                    $request->update([
                        'export_path' => null,
                        'export_disk' => null,
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
