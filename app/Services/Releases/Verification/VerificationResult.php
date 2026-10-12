<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

final readonly class VerificationResult
{
    /** @param array<string, mixed> $manifest */
    public function __construct(public string $verdict, public string $reason, public array $manifest = []) {}

    public function passed(): bool
    {
        return in_array($this->verdict, ['complete', 'repaired'], true);
    }
}
