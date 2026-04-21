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

        $analysis = $this->inspectSignals($name);
        if ($this->isZeroVowelLongToken($analysis['coreName'])) {
            return $this->matched(Category::OTHER_HASHED, 0.78, 'gibberish_zero_vowels');
        }

        // Check for obfuscated/encoded patterns
        if ($result = $this->checkObfuscated($name)) {
            return $result;
        }

        // Check low-signal names that only contain random-looking tokens
        if ($result = $this->checkLowSignal($name)) {
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

    /**
     * Inspect a release name for media signal markers used by the safety-net pipe.
     *
     * @return array{coreName: string, coreLength: int, signalScore: int, markers: list<string>, lowSignal: bool}
     */
    public function inspectSignals(ReleaseContext|string $context): array
    {
        $name = $context instanceof ReleaseContext ? $context->releaseName : $context;
        $cleaned = $this->stripExtensionsForAnalysis($name);
        $coreName = $this->getCoreNameWithoutSeparators($cleaned);

        $patterns = [
            'season_episode' => '/\bS\d{1,3}[._ -]?E\d{1,4}\b/i',
            'season_pack' => '/\bS\d{1,3}\b/i',
            'resolution' => '/\b(480p|576p|720p|1080[pi]?|2160p|4k|uhd)\b/i',
            'codec' => '/\b(x264|x265|h\.?264|h\.?265|hevc|xvid|av1)\b/i',
            'source' => '/\b(bluray|bdrip|brrip|hdtv|web[._ -]?dl|web[._ -]?rip|dvdrip|remux)\b/i',
            'audio' => '/\b(aac|ac3|ddp|dts|flac|mp3)\b/i',
            'scene_tag' => '/\b(proper|repack|internal|limited|complete|dubbed|subbed|readnfo)\b/i',
            'year' => '/\b(19|20)\d{2}\b/',
            'release_group' => '/-[A-Za-z0-9][A-Za-z0-9._-]{1,20}$/',
            'known_extension' => '/\.(mkv|avi|mp4|mp3|flac|iso|epub|pdf|exe|nzb|rar|7z)$/i',
        ];

        $markers = [];
        foreach ($patterns as $marker => $pattern) {
            if (preg_match($pattern, $name)) {
                $markers[] = $marker;
            }
        }

        $signalScore = count($markers);
        $isCoreToken = preg_match('/^[A-Za-z0-9+\/_=-]+$/', $coreName) === 1;
        $lowSignal = $signalScore === 0 && $isCoreToken && strlen($coreName) >= 12;

        return [
            'coreName' => $coreName,
            'coreLength' => strlen($coreName),
            'signalScore' => $signalScore,
            'markers' => $markers,
            'lowSignal' => $lowSignal,
        ];
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

        if ($this->isBase64LikeToken($name)) {
            return $this->matched(Category::OTHER_HASHED, 0.9, 'hash_base64_like');
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
            $analysis = $this->inspectSignals($name);
            $hashLike = $this->isBase64LikeToken($name)
                || $this->isBoundedGenericHash($name)
                || $this->isZeroVowelLongToken($analysis['coreName'], 12)
                || $analysis['lowSignal'];

            return $this->matched(
                $hashLike ? Category::OTHER_HASHED : Category::OTHER_MISC,
                $hashLike ? 0.75 : 0.5,
                'obfuscated_pattern',
                ['signal_score' => $analysis['signalScore'], 'markers' => $analysis['markers']]
            );
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

        if ($this->isZeroVowelLongToken($coreName)) {
            return $this->matched(Category::OTHER_HASHED, 0.78, 'gibberish_zero_vowels');
        }

        return null;
    }

    protected function checkLowSignal(string $name): ?CategorizationResult
    {
        $analysis = $this->inspectSignals($name);

        if ($this->isZeroVowelLongToken($analysis['coreName'])) {
            return null;
        }

        if ($analysis['lowSignal'] && $analysis['coreLength'] >= 20) {
            return $this->matched(
                Category::OTHER_HASHED,
                0.8,
                'gibberish_no_signal',
                [
                    'signal_score' => $analysis['signalScore'],
                    'markers' => $analysis['markers'],
                    'core_length' => $analysis['coreLength'],
                ]
            );
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
