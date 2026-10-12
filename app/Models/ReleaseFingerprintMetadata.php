<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $releases_id
 *  @property string $status
 *  @property array<string, mixed>|null $metadata
 *  @property string|null $last_error
 *  @property \Illuminate\Support\Carbon|null $collected_at
 *  @property-read Release $release
 */
final class ReleaseFingerprintMetadata extends Model
{
    protected $primaryKey = 'releases_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array', 'collected_at' => 'datetime'];
    }

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
