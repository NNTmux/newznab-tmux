<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IpBlockSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An IP address that is, or was, refused access to the whole site.
 *
 * @property int $id
 * @property string $ip_address
 * @property IpBlockSource $source
 * @property string|null $reason
 * @property string|null $device_fingerprint
 * @property int $block_count
 * @property Carbon|null $blocked_at
 * @property Carbon|null $expires_at Null while the block is permanent.
 * @property int|null $created_by
 */
class BlockedIp extends Model
{
    use MassPrunable;

    /** Ended blocks are kept this long, so a repeat offence within it is blocked for longer. */
    public const int PRUNE_ENDED_AFTER_DAYS = 90;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IpBlockSource::class,
            'block_count' => 'integer',
            'blocked_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /**
     * Blocks that are in force now: permanent ones and those that have not ended yet.
     *
     * @param  Builder<BlockedIp>  $query
     * @return Builder<BlockedIp>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(static function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now()->subDays(self::PRUNE_ENDED_AFTER_DAYS));
    }
}
