<?php

declare(strict_types=1);

namespace App\Services\Categorization\Pipes;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;
use Closure;

/**
 * Final guardrail for low-signal names.
 *
 * If a release has no meaningful media markers and only matched because of the
 * group name, prefer OTHER_MISC over trusting the group alone.
 */
class MiscSafetyNetPipe extends AbstractCategorizationPipe
{
    protected int $priority = 100;

    public function getName(): string
    {
        return 'MiscSafetyNet';
    }

    public function handle(CategorizationPassable $passable, Closure $next): CategorizationPassable
    {
        if ($this->shouldDowngradeToMisc($passable)) {
            $result = new CategorizationResult(
                Category::OTHER_MISC,
                0.65,
                'group_only_low_signal',
                [
                    'group_match' => $passable->bestResult->matchedBy,
                    'misc_analysis' => $passable->miscAnalysis,
                ]
            );

            if ($passable->debug) {
                $passable->allResults[$this->getName()] = [
                    'category_id' => $result->categoryId,
                    'confidence' => $result->confidence,
                    'matched_by' => $result->matchedBy,
                ];
            }

            $passable->bestResult = $result;
            $passable->lockToMisc();
        }

        return $next($passable);
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->noMatch();
    }

    private function shouldDowngradeToMisc(CategorizationPassable $passable): bool
    {
        return ! $passable->lockedToMisc
            && ($passable->miscAnalysis['lowSignal'] ?? false) === true
            && str_starts_with($passable->bestResult->matchedBy, 'group_name_');
    }
}
