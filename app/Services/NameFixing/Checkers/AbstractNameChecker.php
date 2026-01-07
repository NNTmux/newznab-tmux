<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Checkers;

use App\Services\NameFixing\Contracts\NameCheckerInterface;
use App\Services\NameFixing\DTO\NameFixResult;

/**
 * Abstract base class for name checkers.
 *
 * Provides common functionality for pattern-based name checking.
 */
abstract class AbstractNameChecker implements NameCheckerInterface
{
    protected int $priority = 50;

    protected string $name = 'Abstract';

    /**
     * Get the patterns to check against.
     *
     * @return array<string, string> Pattern name => regex
     */
    abstract protected function getPatterns(): array;

    /**
     * Format the method name for a matched pattern.
     */
    abstract protected function formatMethod(string $patternName): string;

    /**
     * {@inheritdoc}
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        foreach ($this->getPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                );
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Convert a pattern name constant to a readable method description.
     */
    protected function patternNameToMethod(string $patternName): string
    {
        return str_replace('_', ' ', strtolower($patternName));
    }
}
