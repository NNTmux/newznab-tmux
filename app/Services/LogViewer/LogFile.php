<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

use Carbon\CarbonImmutable;

final readonly class LogFile
{
    public function __construct(
        public string $path,
        public string $name,
        public ?string $directory,
        public string $absolutePath,
        public int $size,
        public CarbonImmutable $modifiedAt,
        public bool $structured,
    ) {}

    /**
     * Whether something wrote to the file recently enough that it is probably still held open.
     */
    public function isActive(int $guardMinutes): bool
    {
        return $this->modifiedAt->greaterThan(CarbonImmutable::now()->subMinutes($guardMinutes));
    }

    /**
     * @return array{
     *     path: string,
     *     name: string,
     *     directory: string|null,
     *     size: int,
     *     human_size: string,
     *     modified_at: string,
     *     structured: bool,
     *     active: bool
     * }
     */
    public function toArray(int $guardMinutes = 10): array
    {
        return [
            'path' => $this->path,
            'name' => $this->name,
            'directory' => $this->directory,
            'size' => $this->size,
            'human_size' => self::formatBytes($this->size),
            'modified_at' => $this->modifiedAt->toIso8601String(),
            'structured' => $this->structured,
            'active' => $this->isActive($guardMinutes),
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 1).' '.$units[$unitIndex];
    }
}
