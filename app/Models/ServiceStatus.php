<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $endpoint_url
 * @property ServiceStatusEnum $status
 * @property Carbon|null $last_checked_at
 * @property string $uptime_percentage
 * @property int|null $response_time_ms
 * @property bool $is_enabled
 * @property int $sort_order
 */
class ServiceStatus extends Model
{
    protected $table = 'service_statuses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'endpoint_url',
        'status',
        'last_checked_at',
        'uptime_percentage',
        'response_time_ms',
        'is_enabled',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceStatusEnum::class,
            'last_checked_at' => 'datetime',
            'is_enabled' => 'boolean',
            'uptime_percentage' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsToMany<ServiceIncident, $this>
     */
    public function incidents(): BelongsToMany
    {
        return $this->belongsToMany(ServiceIncident::class, 'service_incident_service_status')
            ->withTimestamps();
    }
}
