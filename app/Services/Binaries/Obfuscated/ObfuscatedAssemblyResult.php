<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

/**
 * Outcome of one assembler pass over a group.
 */
final class ObfuscatedAssemblyResult
{
    /** @var list<ObfuscatedUpload> */
    public array $planned = [];

    public int $merged = 0;

    public int $mergedArticles = 0;

    public int $failed = 0;

    public int $held = 0;

    public int $released = 0;

    public function add(self $other): void
    {
        array_push($this->planned, ...$other->planned);
        $this->merged += $other->merged;
        $this->mergedArticles += $other->mergedArticles;
        $this->failed += $other->failed;
        $this->held += $other->held;
        $this->released += $other->released;
    }
}
