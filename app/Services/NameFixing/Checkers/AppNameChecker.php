<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Checkers;

use App\Services\NameFixing\DTO\NameFixResult;
use App\Services\NameFixing\Patterns\AppPatterns;

/**
 * Name checker for Application/Software releases.
 *
 * Checks release names against software-specific patterns including
 * platform-specific releases, vendor software, and generic patterns.
 */
class AppNameChecker extends AbstractNameChecker
{
    protected int $priority = 40;
    protected string $name = 'App';

    /**
     * {@inheritdoc}
     */
    protected function getPatterns(): array
    {
        return AppPatterns::getAllPatterns();
    }

    /**
     * {@inheritdoc}
     */
    protected function formatMethod(string $patternName): string
    {
        $methodMap = [
            'WITH_KEYGEN' => 'Apps with keygen/patch',
            'WINDOWS' => 'Windows apps',
            'MACOS' => 'macOS apps',
            'LINUX' => 'Linux apps',
            'ADOBE' => 'Adobe apps',
            'MICROSOFT' => 'Microsoft apps',
            'WITH_VERSION' => 'Software with version',
        ];

        return $methodMap[$patternName] ?? $this->patternNameToMethod($patternName);
    }

    /**
     * {@inheritdoc}
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        // Try keygen/crack patterns first (most definitive for software)
        if (preg_match(AppPatterns::WITH_KEYGEN, $textstring, $matches)) {
            return NameFixResult::fromMatch(
                newName: $matches[0],
                method: $this->formatMethod('WITH_KEYGEN'),
                checkerName: $this->getName(),
                confidence: 0.90,
            );
        }

        // Try vendor-specific patterns
        foreach (AppPatterns::getVendorPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.85,
                );
            }
        }

        // Try platform-specific patterns
        foreach (AppPatterns::getPlatformPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.80,
                );
            }
        }

        // Finally try generic version pattern
        if (preg_match(AppPatterns::WITH_VERSION, $textstring, $matches)) {
            return NameFixResult::fromMatch(
                newName: $matches[0],
                method: $this->formatMethod('WITH_VERSION'),
                checkerName: $this->getName(),
                confidence: 0.70,
            );
        }

        return null;
    }
}

