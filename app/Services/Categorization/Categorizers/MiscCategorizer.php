<?php

declare(strict_types=1);

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;
use App\Traits\DetectsHashedNames;

/**
 * Categorizer for miscellaneous content and hash detection.
 * This runs FIRST with high priority to detect hashes early and prevent
 * them from being incorrectly categorized by group-based or content-based rules.
 */
class MiscCategorizer extends AbstractCategorizer
{
    use DetectsHashedNames;

    protected int $priority = 1; // Highest priority - run first to catch hashes

    public function getName(): string
    {
        return 'Misc';
    }

    public function categorize(ReleaseContext $context): CategorizationResult
    {
        $name = $context->releaseName;

        // Check for hash patterns first
        if ($result = $this->checkHash($name)) {
            return $result;
        }

        // Check for obfuscated/encoded patterns
        if ($result = $this->checkObfuscated($name)) {
            return $result;
        }

        // Check for gibberish patterns (character-analysis heuristics)
        if ($result = $this->checkGibberish($name)) {
            return $result;
        }

        // Check for archive formats
        if ($result = $this->checkArchive($name)) {
            return $result;
        }

        // Check for dataset/dump patterns
        if ($result = $this->checkDataset($name)) {
            return $result;
        }

        return $this->noMatch();
    }

    protected function checkHash(string $name): ?CategorizationResult
    {
        // MD5 hash (32 hex characters)
        if ($this->isBoundedMd5Hash($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_md5');
        }

        // SHA-1 hash (40 hex characters)
        if ($this->isBoundedSha1Hash($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_sha1');
        }

        // SHA-256 hash (64 hex characters)
        if ($this->isBoundedSha256Hash($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_sha256');
        }

        // Generic long hex hash (32-128 chars)
        if ($this->isBoundedGenericHash($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_generic');
        }

        // Strip extensions and separators for core-name checks
        $cleaned = $this->stripExtensionsForAnalysis($name);
        $coreName = $this->getCoreNameWithoutSeparators($cleaned);

        // UUID pattern
        if ($this->isUuidPattern($coreName)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_uuid');
        }

        // Pure hex string (≥16 chars)
        if ($this->isPureHexString($coreName)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_hex');
        }

        return null;
    }

    protected function checkObfuscated(string $name): ?CategorizationResult
    {
        // Release names consisting only of uppercase letters and numbers
        if ($this->isObfuscatedUppercaseString($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.7, 'obfuscated_uppercase');
        }

        // Mixed-case alphanumeric strings without separators
        if ($this->isObfuscatedMixedAlphanumeric($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.7, 'obfuscated_mixed_alphanumeric');
        }

        // Obfuscated filename embedded in usenet subject line format
        if ($this->isObfuscatedUsenetFilename($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.85, 'obfuscated_usenet_filename');
        }

        // Only punctuation and numbers with no clear structure
        if ($this->isObfuscatedPunctuation($name)) {
            return $this->matched(Category::OTHER_MISC, 0.5, 'obfuscated_pattern');
        }

        return null;
    }

    /**
     * Check for gibberish patterns using character-analysis heuristics.
     * These are patterns ported from FileNameCleaner that were previously
     * missing from the categorization pipeline.
     */
    protected function checkGibberish(string $name): ?CategorizationResult
    {
        $cleaned = $this->stripExtensionsForAnalysis($name);
        $coreName = $this->getCoreNameWithoutSeparators($cleaned);

        // High character-transition rate suggests randomness
        if ($this->isRandomByCharacterAnalysis($coreName, $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.75, 'gibberish_random_transitions');
        }

        // Long alphanumeric but lacks word-like letter sequences
        if ($this->hasInsufficientWordStructure($coreName)) {
            return $this->matched(Category::OTHER_HASHED, 0.75, 'gibberish_no_word_structure');
        }

        // Random-looking patterns dominated by digits
        if ($this->isRandomDigitPattern($coreName)) {
            return $this->matched(Category::OTHER_HASHED, 0.7, 'gibberish_random_digits');
        }

        return null;
    }

    protected function checkArchive(string $name): ?CategorizationResult
    {
        if (preg_match('/\.(zip|rar|7z|tar|gz|bz2|xz|tgz|tbz2|cab|iso|img|dmg|pkg|archive)$/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.5, 'archive');
        }

        return null;
    }

    protected function checkDataset(string $name): ?CategorizationResult
    {
        // Dataset/dump patterns that aren't media
        if (preg_match('/\b(sql|csv|dump|backup|dataset|collection)\b/i', $name) &&
            ! preg_match('/\b(movie|tv|show|audio|video|book|game)\b/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.6, 'dataset');
        }

        // Data leaks/dumps (be careful with these)
        if (preg_match('/\b(leak|breach|data|database)\b/i', $name) &&
            preg_match('/\b(dump|export|backup)\b/i', $name) &&
            ! preg_match('/\b(movie|tv|show|audio|video|book|game)\b/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.6, 'data_dump');
        }

        return null;
    }
}
