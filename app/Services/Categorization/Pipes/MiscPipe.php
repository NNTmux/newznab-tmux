<?php

declare(strict_types=1);

namespace App\Services\Categorization\Pipes;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\Categorizers\MiscCategorizer;
use App\Services\Categorization\ReleaseContext;
use Closure;

/**
 * Pipe for miscellaneous content and hash detection.
 * This runs FIRST with high priority to detect hashes early and prevent
 * them from being incorrectly categorized by group-based or content-based rules.
 *
 * When a hashed/obfuscated/gibberish release is detected, the passable is
 * locked to misc categories so no downstream pipe can override the result.
 */
class MiscPipe extends AbstractCategorizationPipe
{
    protected int $priority = 1; // Run first to catch hashes early

    private MiscCategorizer $categorizer;

    /**
     * matchedBy prefixes that indicate a hashed/obfuscated/gibberish result
     * and should trigger the misc lock.
     */
    private const LOCK_PREFIXES = [
        'hash_',
        'obfuscated_',
        'gibberish_',
    ];

    public function __construct()
    {
        $this->categorizer = new MiscCategorizer;
    }

    public function getName(): string
    {
        return 'Misc';
    }

    /**
     * Override handle() to lock the passable when a hash/gibberish release is detected.
     */
    public function handle(CategorizationPassable $passable, Closure $next): CategorizationPassable
    {
        // MiscPipe always runs regardless of shouldStopProcessing — it IS the first pipe.
        $result = $this->categorize($passable->context);

        // Record the result for debug
        $passable->updateBestResult($result, $this->getName());

        // Lock to misc if this is a hashed/obfuscated/gibberish detection
        if ($result->isSuccessful() && $this->shouldLock($result)) {
            $passable->bestResult = $result;
            $passable->lockToMisc();
        }

        return $next($passable);
    }

    protected function categorize(ReleaseContext $context): CategorizationResult
    {
        return $this->categorizer->categorize($context);
    }

    /**
     * Determine if a result should trigger the misc lock.
     */
    private function shouldLock(CategorizationResult $result): bool
    {
        // Always lock OTHER_HASHED results
        if ($result->categoryId === Category::OTHER_HASHED) {
            return true;
        }

        // Lock OTHER_MISC results that were matched by hash/obfuscated/gibberish checks
        foreach (self::LOCK_PREFIXES as $prefix) {
            if (str_starts_with($result->matchedBy, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
