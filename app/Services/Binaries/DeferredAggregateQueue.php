<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use Illuminate\Support\Facades\Redis;

/**
 * Binary ids whose part counts changed, waiting for the single aggregator
 * (binaries:flush-aggregates). A Redis set, so a binary touched by many
 * chunks is recounted once.
 */
class DeferredAggregateQueue
{
    public const KEY = 'nntmux:binaries:dirty';

    public static function enabled(): bool
    {
        return (bool) config('nntmux.defer_header_aggregates', false);
    }

    /**
     * @param  list<int>  $binaryIds
     */
    public function push(array $binaryIds): void
    {
        if ($binaryIds !== []) {
            Redis::sadd(self::KEY, ...$binaryIds);
        }
    }

    /**
     * @return list<int>
     */
    public function pop(int $count): array
    {
        $ids = Redis::spop(self::KEY, $count);

        return array_map('intval', array_values((array) ($ids ?: [])));
    }

    public function size(): int
    {
        return (int) Redis::scard(self::KEY);
    }
}
