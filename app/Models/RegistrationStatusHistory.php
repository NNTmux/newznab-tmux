<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationStatusHistory extends Model
{
    public const ACTION_MANUAL_STATUS_CHANGED = 'manual_status_changed';

    public const ACTION_PERIOD_CREATED = 'period_created';

    public const ACTION_PERIOD_UPDATED = 'period_updated';

    public const ACTION_PERIOD_TOGGLED = 'period_toggled';

    public const ACTION_PERIOD_DELETED = 'period_deleted';

    protected $table = 'registration_status_history';

    protected $fillable = [
        'action',
        'old_status',
        'new_status',
        'registration_period_id',
        'changed_by',
        'description',
        'metadata',
    ];

    protected $casts = [
        'old_status' => 'integer',
        'new_status' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * @return BelongsTo<RegistrationPeriod, $this>
     */
    public function registrationPeriod(): BelongsTo
    {
        return $this->belongsTo(RegistrationPeriod::class, 'registration_period_id');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $action,
        string $description,
        ?int $changedBy = null,
        ?int $oldStatus = null,
        ?int $newStatus = null,
        ?int $registrationPeriodId = null,
        array $metadata = []
    ): self {
        return self::create([
            'action' => $action,
            'description' => $description,
            'changed_by' => $changedBy,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'registration_period_id' => $registrationPeriodId,
            'metadata' => $metadata,
        ]);
    }
}
