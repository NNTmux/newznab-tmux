<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\State;

use App\Services\AdditionalProcessing\Enums\ProcessingStage;
use Closure;

final class ProcessingMetrics
{
    private readonly int $startedAtNanoseconds;

    /**
     * @var array<string, int>
     */
    private array $stageNanoseconds = [];

    /** @param (Closure(): bool)|null $heartbeat */
    public function __construct(private readonly ?Closure $heartbeat = null)
    {
        $this->startedAtNanoseconds = hrtime(true);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $operation
     * @return TValue
     */
    public function measure(ProcessingStage $stage, Closure $operation): mixed
    {
        if ($this->heartbeat !== null && ! ($this->heartbeat)()) {
            throw new \RuntimeException('Additional processing ownership lost.');
        }
        $startedAt = hrtime(true);

        try {
            return $operation();
        } finally {
            $elapsed = hrtime(true) - $startedAt;
            $this->stageNanoseconds[$stage->value] = ($this->stageNanoseconds[$stage->value] ?? 0) + $elapsed;
        }
    }

    public function elapsedSeconds(): float
    {
        return $this->nanosecondsToSeconds(hrtime(true) - $this->startedAtNanoseconds);
    }

    /**
     * @return array<string, float>
     */
    public function stageDurations(): array
    {
        return array_map($this->nanosecondsToSeconds(...), $this->stageNanoseconds);
    }

    private function nanosecondsToSeconds(int $nanoseconds): float
    {
        return $nanoseconds / 1_000_000_000;
    }
}
