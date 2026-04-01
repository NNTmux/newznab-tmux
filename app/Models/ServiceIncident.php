<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IncidentImpactEnum;
use App\Enums\IncidentStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property IncidentStatusEnum $status
 * @property IncidentImpactEnum $impact
 * @property Carbon $started_at
 * @property Carbon|null $resolved_at
 * @property int|null $created_by
 * @property bool $is_auto
 */
class ServiceIncident extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'status',
        'impact',
        'started_at',
        'resolved_at',
        'created_by',
        'is_auto',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatusEnum::class,
            'impact' => IncidentImpactEnum::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_auto' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<ServiceStatus, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ServiceStatus::class, 'service_incident_service_status')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isResolved(): bool
    {
        return $this->status === IncidentStatusEnum::Resolved;
    }
}
