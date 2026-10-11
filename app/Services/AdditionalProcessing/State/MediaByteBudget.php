<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\State;

/**
 * Network bytes one release may still spend looking for preview sources.
 */
final class MediaByteBudget
{
    private int $spent = 0;

    public function __construct(public readonly int $limit) {}

    public function allows(int $bytes): bool
    {
        return $this->spent + max(0, $bytes) <= $this->limit;
    }

    public function spend(int $bytes): void
    {
        $this->spent += max(0, $bytes);
    }

    public function remaining(): int
    {
        return max(0, $this->limit - $this->spent);
    }

    public function exhausted(): bool
    {
        return $this->spent >= $this->limit;
    }
}
