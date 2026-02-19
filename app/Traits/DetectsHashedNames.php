<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for detecting hashed, obfuscated, and gibberish release names.
 *
 * Provides granular detection methods that can be used independently
 * (for distinct matchedBy tags) or combined via isHashedOrGibberish().
 *
 * Used by both MiscCategorizer (categorization pipeline) and
 * FileNameCleaner (name-fixing) to keep detection logic in sync.
 */
trait DetectsHashedNames
{
    /**
     * Combined check: returns true if the name appears hashed or gibberish.
     */
    public function isHashedOrGibberish(string $name): bool
    {
        $cleaned = $this->stripExtensionsForAnalysis($name);
        $coreName = $this->getCoreNameWithoutSeparators($cleaned);

        return $this->isUuidPattern($coreName)
            || $this->isPureHexString($coreName)
            || $this->isBoundedMd5Hash($name)
            || $this->isBoundedSha1Hash($name)
            || $this->isBoundedSha256Hash($name)
            || $this->isBoundedGenericHash($name)
            || $this->isObfuscatedUppercaseString($name)
            || $this->isObfuscatedMixedAlphanumeric($name)
            || $this->isObfuscatedUsenetFilename($name)
            || $this->isRandomByCharacterAnalysis($coreName, $name)
            || $this->hasInsufficientWordStructure($coreName)
            || $this->isRandomDigitPattern($coreName);
    }

    // ---------------------------------------------------------------
    // Hex / hash boundary checks (operate on the raw release name)
    // ---------------------------------------------------------------

    /**
     * Detect MD5 hash (32 hex characters) with word boundaries.
     */
    protected function isBoundedMd5Hash(string $name): bool
    {
        return (bool) preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{32})(?:["\'\s\[\]\/.\-]|$)/i', $name);
    }

    /**
     * Detect SHA-1 hash (40 hex characters) with word boundaries.
     */
    protected function isBoundedSha1Hash(string $name): bool
    {
        return (bool) preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{40})(?:["\'\s\[\]\/.\-]|$)/i', $name);
    }

    /**
     * Detect SHA-256 hash (64 hex characters) with word boundaries.
     */
    protected function isBoundedSha256Hash(string $name): bool
    {
        return (bool) preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{64})(?:["\'\s\[\]\/.\-]|$)/i', $name);
    }

    /**
     * Detect generic long hex hash (32-128 chars) with word boundaries.
     */
    protected function isBoundedGenericHash(string $name): bool
    {
        return (bool) preg_match('/(?:^|["\'\s\[\]\/\-])([a-f0-9]{32,128})(?:["\'\s\[\]\/.\-]|$)/i', $name);
    }

    // ---------------------------------------------------------------
    // Core-name checks (operate on stripped / separator-free names)
    // ---------------------------------------------------------------

    /**
     * Detect UUID patterns (with or without dashes stripped).
     */
    protected function isUuidPattern(string $coreName): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{12}$/i', $coreName);
    }

    /**
     * Detect pure hex strings of 16+ characters.
     */
    protected function isPureHexString(string $coreName, int $minLength = 16): bool
    {
        return (bool) preg_match('/^[a-f0-9]{'.$minLength.',}$/i', $coreName);
    }

    /**
     * Detect all-uppercase alphanumeric strings of 15+ characters.
     */
    protected function isObfuscatedUppercaseString(string $name): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{15,}$/', $name);
    }

    /**
     * Detect mixed-case alphanumeric strings of 15+ characters
     * that are not CamelCase words and contain no year pattern.
     */
    protected function isObfuscatedMixedAlphanumeric(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]{15,}$/', $name)
            && ! preg_match('/\b(19|20)\d{2}\b/', $name)
            && ! preg_match('/^[A-Z][a-z]+([A-Z][a-z]+)+$/', $name);
    }

    /**
     * Detect obfuscated filenames embedded in usenet subject lines.
     *
     * Matches patterns like: [XX/XX] - "RANDOMSTRING.partXX.rar"
     */
    protected function isObfuscatedUsenetFilename(string $name): bool
    {
        if (preg_match('/\[\d+\/\d+]\s*-\s*"([a-zA-Z0-9]{12,})\.(part\d+\.rar|7z\.\d{3}|rar|zip|vol\d+\+\d+\.par2|par2)"/i', $name, $matches)) {
            $filename = $matches[1];

            return ! preg_match('/[._ -]/', $filename)
                && ! preg_match('/\b(19|20)\d{2}\b/', $filename)
                && $this->looksLikeRandomString($filename);
        }

        return false;
    }

    /**
     * Detect names consisting of only punctuation and numbers with no clear structure.
     */
    protected function isObfuscatedPunctuation(string $name): bool
    {
        return (bool) preg_match('/^[^a-zA-Z]*[A-Z0-9._\-]{5,}[^a-zA-Z]*$/', $name)
            && ! preg_match('/\.(mkv|avi|mp4|mp3|flac|pdf|epub|exe|iso)$/i', $name);
    }

    // ---------------------------------------------------------------
    // Character-analysis heuristics
    // ---------------------------------------------------------------

    /**
     * Analyse character transitions to detect random strings.
     *
     * A high transition rate (upper↔lower, letter↔digit) with no
     * media keywords in the original name suggests randomness.
     *
     * @param  string  $coreName  Name with extensions and separators stripped.
     * @param  string  $originalName  The unmodified release name (for keyword check).
     */
    protected function isRandomByCharacterAnalysis(string $coreName, string $originalName = ''): bool
    {
        $coreLen = strlen($coreName);

        if ($coreLen < 16 || ! preg_match('/^[a-zA-Z0-9]+$/', $coreName)) {
            return false;
        }

        $transitions = $this->countCharacterTransitions($coreName);
        $transitionRate = $transitions / ($coreLen - 1);

        if ($transitionRate > 0.35) {
            $nameToCheck = $originalName !== '' ? $originalName : $coreName;

            return ! preg_match(
                '/\b(movie|film|series|episode|season|show|video|audio|music|album|dvd|bluray|hdtv|webrip|xvid|x264|x265|hevc|aac|mp3|flac|720p|1080p|2160p|4k|complete|proper|repack|dubbed|subbed|english|french|german|spanish|italian|rip|web|hdr|remux|disc|internal|retail)\b/i',
                $nameToCheck
            );
        }

        return false;
    }

    /**
     * Detect names that are long alphanumeric but lack word-like letter sequences.
     *
     * @param  string  $coreName  Name with extensions and separators stripped.
     */
    protected function hasInsufficientWordStructure(string $coreName): bool
    {
        $coreLen = strlen($coreName);

        if ($coreLen < 20 || ! preg_match('/^[a-zA-Z0-9]+$/', $coreName)) {
            return false;
        }

        $maxConsecutiveLetters = $this->getMaxConsecutiveLetters($coreName);

        return $maxConsecutiveLetters < 5
            && ! preg_match('/^[a-zA-Z]+\d{1,4}$/', $coreName);
    }

    /**
     * Detect random-looking patterns dominated by digits.
     *
     * @param  string  $coreName  Name with extensions and separators stripped.
     */
    protected function isRandomDigitPattern(string $coreName): bool
    {
        return (bool) (
            preg_match('/^[a-zA-Z]{1,3}\d{6,}[a-zA-Z]*$/i', $coreName)
            || preg_match('/^[a-zA-Z0-9]{2,4}\d{8,}$/i', $coreName)
        );
    }

    /**
     * Check if a string looks like a random/obfuscated string rather than a real title.
     *
     * Uses multiple heuristics: character-type transitions, consonant clusters,
     * mixed case with digits, and vowel-consonant patterns.
     */
    protected function looksLikeRandomString(string $str): bool
    {
        // All same-case letters → random if long enough
        if (preg_match('/^[A-Z]+$/', $str) || preg_match('/^[a-z]+$/', $str)) {
            return strlen($str) >= 12;
        }

        $len = strlen($str);
        $transitions = $this->countCharacterTransitions($str);
        $transitionRatio = $transitions / max(1, $len - 1);

        // Check for common English consonant-vowel patterns
        $hasWordPattern = preg_match(
            '/[bcdfghjklmnpqrstvwxyz]{1,2}[aeiou][bcdfghjklmnpqrstvwxyz]{1,2}[aeiou]/i',
            $str
        );

        // High transition ratio + no word patterns → random
        if ($transitionRatio > 0.3 && ! $hasWordPattern) {
            return true;
        }

        // Unlikely consonant clusters (≥5 consecutive consonants)
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/i', $str)) {
            return true;
        }

        // Mixed case AND digits with no clear structure
        if (preg_match('/[A-Z]/', $str) && preg_match('/[a-z]/', $str) && preg_match('/\d/', $str)) {
            return true;
        }

        return false;
    }

    // ---------------------------------------------------------------
    // Helper utilities
    // ---------------------------------------------------------------

    /**
     * Strip common file extensions for analysis.
     */
    protected function stripExtensionsForAnalysis(string $name): string
    {
        return preg_replace(
            '/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx|par2?|nfo|sfv|nzb|rar|r\d{2,3}|zip|7z|gz|tar|001)$/i',
            '',
            trim($name)
        );
    }

    /**
     * Get core name by removing all separators.
     */
    protected function getCoreNameWithoutSeparators(string $cleaned): string
    {
        return preg_replace('/[.\-_\s]+/', '', $cleaned);
    }

    /**
     * Count character-type transitions (upper↔lower, letter↔digit).
     */
    protected function countCharacterTransitions(string $str): int
    {
        $transitions = 0;
        $len = strlen($str);

        for ($i = 1; $i < $len; $i++) {
            $prev = $str[$i - 1];
            $curr = $str[$i];

            $prevIsDigit = ctype_digit($prev);
            $currIsDigit = ctype_digit($curr);
            $prevIsUpper = ctype_upper($prev);
            $currIsUpper = ctype_upper($curr);

            if ($prevIsDigit !== $currIsDigit) {
                $transitions++;
            } elseif (! $prevIsDigit && ! $currIsDigit && $prevIsUpper !== $currIsUpper) {
                $transitions++;
            }
        }

        return $transitions;
    }

    /**
     * Get the maximum consecutive letter count in a string.
     */
    protected function getMaxConsecutiveLetters(string $str): int
    {
        $maxConsecutive = 0;
        $currentConsecutive = 0;
        $lastWasLetter = false;

        for ($i = 0, $len = strlen($str); $i < $len; $i++) {
            $isLetter = ctype_alpha($str[$i]);
            if ($isLetter) {
                $currentConsecutive = $lastWasLetter ? $currentConsecutive + 1 : 1;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            }
            $lastWasLetter = $isLetter;
        }

        return $maxConsecutive;
    }
}
