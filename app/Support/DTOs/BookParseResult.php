<?php

declare(strict_types=1);

namespace App\Support\DTOs;

final readonly class BookParseResult
{
    public function __construct(
        public string $rawName,
        public string $title,
        public ?string $author = null,
        public ?string $isbn = null,
        public ?int $year = null,
        public ?string $format = null,
        public bool $isJunk = false,
        public bool $isMagazine = false,
    ) {}

    public function hasAuthor(): bool
    {
        return $this->author !== null && $this->author !== '';
    }

    public function searchQuery(): string
    {
        return trim(($this->author ?? '').' '.$this->title);
    }
}
