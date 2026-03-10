<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationPeriod extends Model
{
    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'is_enabled',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_enabled' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @return Builder<self>
     */
    public function scopeActiveAt(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->enabled()
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }

    /**
     * @return Builder<self>
     */
    public function scopeUpcoming(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('starts_at', '>', $at);
    }

    public function isActiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->is_enabled
            && $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lte($at)
            && $this->ends_at->gte($at);
    }
}
