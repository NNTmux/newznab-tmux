<?php

declare(strict_types=1);

namespace App\Support\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Data transfer object for release creation results.
 */
#[TypeScript]
final class ReleaseCreationResult extends Data
{
    public function __construct(
        public int $added = 0,
        public int $dupes = 0,
    ) {}

    public function total(): int
    {
        return $this->added + $this->dupes;
    }

    public function hasAddedReleases(): bool
    {
        return $this->added > 0;
    }
}
