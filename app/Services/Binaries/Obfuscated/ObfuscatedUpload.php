<?php

declare(strict_types=1);

namespace App\Services\Binaries\Obfuscated;

/**
 * A set of rebuilt files that belong to one upload. Files are ordered largest first,
 * so the first file names the release and holds the target binary.
 */
final readonly class ObfuscatedUpload
{
    /** @var list<ObfuscatedFile> */
    public array $files;

    /**
     * @param  list<ObfuscatedFile>  $files
     */
    public function __construct(public string $poster, public int $totalFiles, array $files)
    {
        usort($files, static fn (ObfuscatedFile $a, ObfuscatedFile $b): int => [$b->totalParts, $a->fileNumber, $a->minNumber]
            <=> [$a->totalParts, $b->fileNumber, $b->minNumber]);
        $this->files = $files;
    }

    public function articles(): int
    {
        return array_sum(array_map(static fn (ObfuscatedFile $file): int => $file->articles, $this->files));
    }

    public function completionPercent(): float
    {
        $expected = array_sum(array_map(static fn (ObfuscatedFile $file): int => $file->totalParts, $this->files));
        $present = array_sum(array_map(static fn (ObfuscatedFile $file): int => min($file->distinctParts, $file->totalParts), $this->files));

        return $expected > 0 ? $present / $expected * 100 : 0.0;
    }

    public function bytes(): int
    {
        return array_sum(array_map(static fn (ObfuscatedFile $file): int => $file->bytes, $this->files));
    }

    public function lastSeen(): int
    {
        return max(array_map(static fn (ObfuscatedFile $file): int => $file->lastSeen, $this->files));
    }

    public function minNumber(): int
    {
        return min(array_map(static fn (ObfuscatedFile $file): int => $file->minNumber, $this->files));
    }

    public function ambiguousFiles(): int
    {
        return \count(array_filter($this->files, static fn (ObfuscatedFile $file): bool => $file->ambiguous));
    }
}
