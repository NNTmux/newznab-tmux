<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Checkers;

use App\Services\NameFixing\DTO\NameFixResult;
use App\Services\NameFixing\Patterns\GamePatterns;

/**
 * Name checker for Game releases.
 *
 * Checks release names against game-specific patterns including modern consoles,
 * PC games with scene groups, DLC/updates, and repacks.
 */
class GameNameChecker extends AbstractNameChecker
{
    protected int $priority = 30;

    protected string $name = 'Game';

    /**
     * {@inheritdoc}
     */
    protected function getPatterns(): array
    {
        return GamePatterns::getAllPatterns();
    }

    /**
     * {@inheritdoc}
     */
    protected function formatMethod(string $patternName): string
    {
        $methodMap = [
            'MODERN_CONSOLE' => 'Modern console release',
            'REGION_CONSOLE' => 'Videogames with region',
            'CONSOLE_SCENE_GROUP' => 'Console with scene group',
            'PC_SCENE_GROUP' => 'PC game with scene group',
            'DLC_UPDATE' => 'DLC/Update',
            'OUTLAWS' => 'PC Games -OUTLAWS',
            'ALIAS' => 'PC Games -ALiAS',
            'GOG' => 'GOG release',
            'REPACK' => 'Game REPACK',
        ];

        return $methodMap[$patternName] ?? $this->patternNameToMethod($patternName);
    }

    /**
     * {@inheritdoc}
     *
     * Override to handle special cases like OUTLAWS group naming.
     */
    public function check(object $release, string $textstring): ?NameFixResult
    {
        // Try console patterns first
        foreach (GamePatterns::getConsolePatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                return NameFixResult::fromMatch(
                    newName: $matches[0],
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.90,
                );
            }
        }

        // Then try PC patterns with special handling
        foreach (GamePatterns::getPCPatterns() as $patternName => $pattern) {
            if (preg_match($pattern, $textstring, $matches)) {
                $newName = $matches[0];

                // Handle OUTLAWS group (add PC GAME tag)
                if ($patternName === 'OUTLAWS') {
                    $newName = str_replace('OUTLAWS', 'PC GAME OUTLAWS', $newName);
                }

                // Handle ALiAS group (add PC GAME tag)
                if ($patternName === 'ALIAS') {
                    $newName = str_replace('-ALiAS', ' PC GAME ALiAS', $newName);
                }

                return NameFixResult::fromMatch(
                    newName: $newName,
                    method: $this->formatMethod($patternName),
                    checkerName: $this->getName(),
                    confidence: 0.85,
                );
            }
        }

        return null;
    }
}
