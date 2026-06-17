<?php

declare(strict_types=1);

namespace App\Services\Gdpr;

use App\Models\GdprAuditLog;
use App\Models\GdprRequest;
use App\Models\User;

class GdprAuditService
{
    /**
     * Record a GDPR accountability/audit event.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $event,
        string $description,
        ?User $subject = null,
        ?User $actor = null,
        ?GdprRequest $request = null,
        array $metadata = [],
    ): GdprAuditLog {
        return GdprAuditLog::create([
            'gdpr_request_id' => $request?->id,
            'user_id' => $subject?->id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
