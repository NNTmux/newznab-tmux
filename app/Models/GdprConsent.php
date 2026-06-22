<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprConsent extends Model
{
    public const TYPE_ESSENTIAL_COOKIES = 'essential_cookies';

    public const STATUS_GRANTED = 'granted';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'consent_type',
        'status',
        'policy_version',
        'consented_at',
        'withdrawn_at',
        'ip_address',
        'user_agent_hash',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
