<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class GdprRequest extends Model
{
    public const TYPE_EXPORT = 'export';

    public const TYPE_ERASURE = 'erasure';

    public const TYPE_RECTIFICATION = 'rectification';

    public const TYPE_RESTRICTION = 'restriction';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'requester_username',
        'requester_email',
        'type',
        'status',
        'request_payload',
        'response_payload',
        'notes',
        'admin_notes',
        'export_disk',
        'export_path',
        'export_expires_at',
        'processed_by',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'export_expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    /**
     * @return HasMany<GdprAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(GdprAuditLog::class)->latest();
    }

    /**
     * @param  Builder<GdprRequest>  $query
     * @return Builder<GdprRequest>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    public function isDownloadableExport(): bool
    {
        $expiresAt = $this->export_expires_at;

        return $this->type === self::TYPE_EXPORT
            && $this->status === self::STATUS_COMPLETED
            && $this->export_path !== null
            && ($expiresAt === null || Carbon::parse($expiresAt)->isFuture());
    }
}
