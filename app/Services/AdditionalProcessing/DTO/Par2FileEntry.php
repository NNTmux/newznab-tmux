<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

/**
 * A file as the release's PAR2 index describes it.
 */
final readonly class Par2FileEntry
{
    /**
     * @param  string  $hash16k  MD5 of the file's first 16 KiB, lower-case hex
     */
    public function __construct(
        public string $name,
        public int $size,
        public string $hash16k = '',
    ) {}

    /**
     * @param  array<int|string, mixed>  $files  Par2Info::getFileList()
     * @return list<self>
     */
    public static function listFromPar2Info(array $files): array
    {
        $entries = [];
        foreach ($files as $file) {
            if (! is_array($file) || ! is_string($file['name'] ?? null) || (int) ($file['size'] ?? 0) <= 0) {
                continue;
            }

            $entries[] = new self(
                name: $file['name'],
                size: (int) $file['size'],
                hash16k: strtolower((string) ($file['hash_16K'] ?? '')),
            );
        }

        return $entries;
    }

    /**
     * Whether $data is the start of this file.
     */
    public function matchesStartOf(string $data): bool
    {
        $length = min(16384, $this->size);

        return $this->hash16k !== '' && strlen($data) >= $length && md5(substr($data, 0, $length)) === $this->hash16k;
    }
}
