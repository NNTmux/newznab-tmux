<?php

declare(strict_types=1);

namespace App\Support\Configuration;

final readonly class ConfigurationField
{
    /**
     * @param  list<string>  $rules
     * @param  array<int|string, string>  $options
     */
    public function __construct(
        public string $column,
        public string $label,
        public string $help,
        public string $control,
        public array $rules,
        public array $options = [],
        public ?string $unit = null,
        public bool $sensitive = false,
        public ?string $guidance = null,
    ) {}
}
