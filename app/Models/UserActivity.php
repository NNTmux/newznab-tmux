<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'username',
        'activity_type',
        'description',
        'metadata',
        'is_permanent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'is_permanent' => 'boolean',
    ];

    /**
     * Get icon class based on activity type
     */
    public function getIconAttribute(): string
    {
        return match ($this->activity_type) {
            'registered' => 'user-plus',
            'deleted' => 'user-times',
            'role_updated' => 'user-shield',
            default => 'info-circle',
        };
    }

    /**
     * Tailwind classes for the activity icon badge. Kept as literal strings because
     * Tailwind scans this file (see @source in resources/css/app.css).
     */
    public function iconBadgeClasses(): string
    {
        return match ($this->activity_type) {
            'registered' => 'bg-success-100 text-success-600 dark:bg-success-900 dark:text-success-400',
            'deleted' => 'bg-danger-100 text-danger-600 dark:bg-danger-900 dark:text-danger-400',
            'role_updated' => 'bg-primary-100 text-link dark:bg-primary-900 dark:text-primary-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400',
        };
    }
}
