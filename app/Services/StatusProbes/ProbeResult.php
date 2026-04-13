<?php

declare(strict_types=1);

namespace App\Services\StatusProbes;

use App\Enums\IncidentImpactEnum;

final readonly class ProbeResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $ok,
        public int $responseTimeMs,
        public ?IncidentImpactEnum $impact,
        public string $reason,
        public array $metadata = [],
    ) {}
}
