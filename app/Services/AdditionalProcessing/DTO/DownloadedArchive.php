<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class DownloadedArchive
{
    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    public function __construct(
        public string $title,
        public string $data,
        public array $files,
    ) {}

    public function byteSize(): int
    {
        return strlen($this->data);
    }
}
