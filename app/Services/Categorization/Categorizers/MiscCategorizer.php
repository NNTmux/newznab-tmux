<?php

namespace App\Services\Categorization\Categorizers;

use App\Models\Category;
use App\Services\Categorization\CategorizationResult;
use App\Services\Categorization\ReleaseContext;

/**
 * Categorizer for miscellaneous content and hash detection.
 * This runs FIRST with high priority to detect hashes early and prevent
 * them from being incorrectly categorized by group-based or content-based rules.
 */
class MiscCategorizer extends AbstractCategorizer
{
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

        // Check for archive formats
        if ($result = $this->checkArchive($name)) {
            return $result;
        }

        // Check for dataset/dump patterns
        if ($result = $this->checkDataset($name)) {
            return $result;
        }

        // Check for obfuscated/encoded patterns
        if ($result = $this->checkObfuscated($name)) {
            return $result;
        }

        return $this->noMatch();
    }

    protected function checkHash(string $name): ?CategorizationResult
    {
        // MD5 hash (32 hex characters) - match with word boundaries or quotes/punctuation
        if (preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{32})(?:["\'\s\[\]\/\-\.]|$)/i', $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_md5');
        }

        // SHA-1 hash (40 hex characters) - match with word boundaries or quotes/punctuation
        if (preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{40})(?:["\'\s\[\]\/\-\.]|$)/i', $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_sha1');
        }

        // SHA-256 hash (64 hex characters) - match with word boundaries or quotes/punctuation
        if (preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{64})(?:["\'\s\[\]\/\-\.]|$)/i', $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_sha256');
        }

        // Generic long hex hash (32-128 chars) - match with word boundaries or quotes/punctuation
        if (preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{32,128})(?:["\'\s\[\]\/\-\.]|$)/i', $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.95, 'hash_generic');
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
            !preg_match('/\b(movie|tv|show|audio|video|book|game)\b/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.6, 'dataset');
        }

        // Data leaks/dumps (be careful with these)
        if (preg_match('/\b(leak|breach|data|database)\b/i', $name) &&
            preg_match('/\b(dump|export|backup)\b/i', $name) &&
            !preg_match('/\b(movie|tv|show|audio|video|book|game)\b/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.6, 'data_dump');
        }

        return null;
    }

    protected function checkObfuscated(string $name): ?CategorizationResult
    {
        // Release names consisting only of uppercase letters and numbers
        if (preg_match('/^[A-Z0-9]{15,}$/', $name)) {
            return $this->matched(Category::OTHER_HASHED, 0.7, 'obfuscated_uppercase');
        }

        // Mixed-case alphanumeric strings without separators (common obfuscation pattern)
        // These look like random strings: e.g., "AA7Jl2toE8Q53yNZmQ5R6G"
        if (preg_match('/^[a-zA-Z0-9]{15,}$/', $name) &&
            !preg_match('/\b(19|20)\d{2}\b/', $name) &&
            !preg_match('/^[A-Z][a-z]+([A-Z][a-z]+)+$/', $name)) { // Exclude CamelCase words
            return $this->matched(Category::OTHER_HASHED, 0.7, 'obfuscated_mixed_alphanumeric');
        }

        // Only punctuation and numbers with no clear structure
        if (preg_match('/^[^a-zA-Z]*[A-Z0-9\._\-]{5,}[^a-zA-Z]*$/', $name) &&
            !preg_match('/\.(mkv|avi|mp4|mp3|flac|pdf|epub|exe|iso)$/i', $name)) {
            return $this->matched(Category::OTHER_MISC, 0.5, 'obfuscated_pattern');
        }

        return null;
    }
}

