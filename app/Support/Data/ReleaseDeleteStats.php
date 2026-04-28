<?php

declare(strict_types=1);

namespace App\Support\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Data transfer object for release deletion statistics.
 *
 * Tracks the number of releases deleted by category during cleanup operations.
 */
#[TypeScript]
final class ReleaseDeleteStats extends Data
{
    public function __construct(
        public int $retention = 0,
        public int $password = 0,
        public int $duplicate = 0,
        public int $completion = 0,
        public int $disabledCategory = 0,
        public int $categoryMinSize = 0,
        public int $disabledGenre = 0,
        public int $miscOther = 0,
        public int $miscHashed = 0,
    ) {}

    public function increment(string $field): self
    {
        $values = [
            'retention' => $this->retention,
            'password' => $this->password,
            'duplicate' => $this->duplicate,
            'completion' => $this->completion,
            'disabledCategory' => $this->disabledCategory,
            'categoryMinSize' => $this->categoryMinSize,
            'disabledGenre' => $this->disabledGenre,
            'miscOther' => $this->miscOther,
            'miscHashed' => $this->miscHashed,
        ];
        if (array_key_exists($field, $values)) {
            $values[$field]++;
        }

        return new self(...$values);
    }

    public function total(): int
    {
        return $this->retention
            + $this->password
            + $this->duplicate
            + $this->completion
            + $this->disabledCategory
            + $this->categoryMinSize
            + $this->disabledGenre
            + $this->miscOther
            + $this->miscHashed;
    }
}
