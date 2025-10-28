<?php

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
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
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
     * Get color class based on activity type
     */
    public function getColorClassAttribute(): string
    {
        return match ($this->activity_type) {
            'registered' => 'green',
            'deleted' => 'red',
            'role_updated' => 'blue',
            default => 'gray',
        };
    }
}
