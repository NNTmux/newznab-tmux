<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

/**
 * One real file rebuilt from single-article collections that share a poster,
 * a `[fn/tf]` file counter and a `(k/tp)` part counter.
 */
final readonly class ObfuscatedFile
{
    /**
     * @param  list<int>|null  $binaryIds  Explicit members when the key was split; null means "every row of the key"
     * @param  bool  $counterIsPart  `[k/N]` was read as both file and part counter, so the file has no file counter
     */
    public function __construct(
        public int $totalFiles,
        public int $fileNumber,
        public int $totalParts,
        public int $articles,
        public int $distinctParts,
        public int $minNumber,
        public int $maxNumber,
        public int $minTime,
        public int $maxTime,
        public int $lastSeen,
        public int $bytes,
        public bool $ambiguous = false,
        public ?array $binaryIds = null,
        public bool $counterIsPart = false,
    ) {}

    public function isComplete(): bool
    {
        return $this->distinctParts >= $this->totalParts;
    }
}
