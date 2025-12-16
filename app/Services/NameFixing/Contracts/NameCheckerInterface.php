<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Contracts;

use App\Services\NameFixing\DTO\NameFixResult;

/**
 * Interface for name checker strategies.
 *
 * Implementations check releases against specific patterns (TV, Movies, Games, etc.)
 * and return potential name fixes when matches are found.
 */
interface NameCheckerInterface
{
    /**
     * Check a release for name patterns.
     *
     * @param object $release The release object to check
     * @param string $textstring The text content to analyze
     * @return NameFixResult|null Returns result if a match is found, null otherwise
     */
    public function check(object $release, string $textstring): ?NameFixResult;

    /**
     * Get the priority of this checker (lower = higher priority).
     */
    public function getPriority(): int;

    /**
     * Get the name/identifier of this checker.
     */
    public function getName(): string;
}

