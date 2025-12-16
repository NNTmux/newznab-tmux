<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

/**
 * Utility class for cleaning and normalizing filenames.
 *
 * Extracts filename cleaning logic from NameFixer for better testability
 * and reusability.
 */
class FileNameCleaner
{
    /**
     * Archive extension patterns to remove.
     */
    private const ARCHIVE_PATTERNS = [
        '/\.part\d{1,4}\.rar$/i',
        '/\.r\d{2,4}$/i',
        '/\.rar$/i',
        '/\.z\d{2}$/i',
        '/\.zip$/i',
        '/\.7z\.\d{3}$/i',
        '/\.7z$/i',
        '/\.vol\d+[\+\-]\d+\.par2?$/i',
        '/\.par2?$/i',
        '/\.(tar|gz|bz2|xz|lz|lzma|cab|arj|ace|arc)$/i',
        '/\.\d{3}$/i',
    ];

    /**
     * Video file extension pattern.
     */
    private const VIDEO_EXTENSIONS = '/\.(mkv|avi|mp4|m4v|wmv|mpg|mpeg|mov|ts|m2ts|vob|divx|flv|webm|ogv|3gp|asf|rm|rmvb|f4v)$/i';

    /**
     * Audio file extension pattern.
     */
    private const AUDIO_EXTENSIONS = '/\.(mp3|flac|m4a|aac|ogg|wav|wma|ape|opus|mka|ac3|dts|eac3|truehd|mpc|shn|tak|tta|wv)$/i';

    /**
     * Image and misc file extension pattern.
     */
    private const IMAGE_EXTENSIONS = '/\.(nfo|sfv|nzb|srr|srs|jpg|jpeg|png|gif|bmp|tiff?|webp|pdf|txt|diz|md5|sha1|cue|log)$/i';

    /**
     * Ebook file extension pattern.
     */
    private const EBOOK_EXTENSIONS = '/\.(epub|mobi|azw3?|pdf|djvu|cbr|cbz|fb2|lit|prc|opf)$/i';

    /**
     * Game/App file extension pattern.
     */
    private const GAMEAPP_EXTENSIONS = '/\.(iso|bin|cue|mdf|mds|nrg|img|ccd|sub|exe|msi|dmg|pkg|apk|xap|appx|deb|rpm)$/i';

    /**
     * Subtitle file extension pattern.
     */
    private const SUBTITLE_EXTENSIONS = '/\.(srt|sub|idx|ass|ssa|vtt|sup)$/i';

    /**
     * Clean a filename for PreDB matching.
     *
     * @param string $fileName The filename to clean
     * @return string|false The cleaned filename or false if invalid
     */
    public function cleanForMatching(string $fileName): string|false
    {
        // Strip non-printing characters
        $fileName = preg_replace('/[[:^print:]]/', '', $fileName);

        if ($fileName === '' || str_starts_with($fileName, '.')) {
            return false;
        }

        // Extract filename from path
        $fileName = $this->extractFilenameFromPath($fileName);

        // Remove sample/proof indicators
        $fileName = preg_replace('/[\.\-_](sample|proof|subs?|thumbs?|cover|screens?)[\.\-_]?$/i', '', $fileName);

        // Remove archive extensions
        foreach (self::ARCHIVE_PATTERNS as $pattern) {
            $fileName = preg_replace($pattern, '', $fileName);
        }

        // Remove media extensions
        $fileName = preg_replace(self::VIDEO_EXTENSIONS, '', $fileName);
        $fileName = preg_replace(self::AUDIO_EXTENSIONS, '', $fileName);
        $fileName = preg_replace(self::IMAGE_EXTENSIONS, '', $fileName);
        $fileName = preg_replace(self::EBOOK_EXTENSIONS, '', $fileName);
        $fileName = preg_replace(self::GAMEAPP_EXTENSIONS, '', $fileName);
        $fileName = preg_replace(self::SUBTITLE_EXTENSIONS, '', $fileName);

        // Remove part/volume indicators
        $fileName = preg_replace('/[\.\-_]?(part|vol|cd|dvd|disc|disk)\d*$/i', '', $fileName);

        // Remove leading track numbers
        $fileName = preg_replace('/^\d{1,3}[\.\-_\s]+(?=[A-Za-z])/', '', $fileName);

        // Trim whitespace and punctuation
        $fileName = trim($fileName, " \t\n\r\0\x0B.-_");

        return $fileName !== '' ? $fileName : false;
    }

    /**
     * Extract filename from a path.
     *
     * @param string $path Full path or filename
     * @return string The filename portion
     */
    public function extractFilenameFromPath(string $path): string
    {
        if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $path, $match)) {
            return $match[1];
        }
        return $path;
    }

    /**
     * Normalize a candidate title.
     *
     * @param string $title The title to normalize
     * @return string The normalized title
     */
    public function normalizeCandidateTitle(string $title): string
    {
        $t = trim($title);

        // Remove common video file extensions
        $t = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx)$/i', '', $t) ?? $t;

        // Remove archive and metadata file extensions
        $t = preg_replace('/\.(par2?|nfo|sfv|nzb|rar|zip|7z|gz|tar|bz2|xz|r\d{2,3}|\d{3}|pkg|exe|msi|jpe?g|png|gif|bmp)$/i', '', $t) ?? $t;

        // Remove common trailing segment markers
        $t = preg_replace('/[.\-_ ](?:part|vol|r)\d+(?:\+\d+)?$/i', '', $t) ?? $t;

        // Collapse multiple spaces/underscores
        $t = preg_replace('/[\s_]+/', ' ', $t) ?? $t;

        // Trim stray punctuation
        return trim($t, " .-_\t\r\n");
    }

    /**
     * Check if a title is plausible for a release.
     *
     * @param string $title The title to check
     * @return bool True if the title looks like a valid release name
     */
    public function isPlausibleReleaseTitle(string $title): bool
    {
        $t = trim($title);

        if ($t === '' || strlen($t) < 12) {
            return false;
        }

        $wordCount = preg_match_all('/[A-Za-z0-9]{3,}/', $t);
        if ($wordCount < 2) {
            return false;
        }

        if ($this->looksLikeHashedName($t)) {
            return false;
        }

        // Reject if still ends with segment marker
        if (preg_match('/(?:^|[.\-_ ])(?:part|vol|r)\d+(?:\+\d+)?$/i', $t)) {
            return false;
        }

        // Reject generic installer filenames
        if (preg_match('/^(setup|install|installer|patch|update|crack|keygen)\d*[\s._-]/i', $t)) {
            return false;
        }

        // Check for valid release indicators
        $hasGroupSuffix = (bool) preg_match('/[-.][A-Za-z0-9]{2,}$/', $t);
        $hasYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $t);
        $hasQuality = (bool) preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|web[ .-]?dl|bluray|bdrip|dvdrip|hdtv|hdrip|xvid|x264|x265|hevc|h\.?264|ts|cam|r5|proper|repack)\b/i', $t);
        $hasTV = (bool) preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);
        $hasXXX = (bool) preg_match('/\bXXX\b/i', $t);

        return $hasGroupSuffix || ($hasTV && $hasQuality) || ($hasYear && ($hasQuality || $hasTV)) || $hasXXX || $hasQuality || $hasTV;
    }

    /**
     * Check if a string looks like a hashed/obfuscated name.
     *
     * @param string $title The title to check
     * @return bool True if the title appears to be hashed/obfuscated
     */
    public function looksLikeHashedName(string $title): bool
    {
        $t = trim($title);

        // Remove common file extensions for analysis
        $cleaned = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx|par2?|nfo|sfv|nzb|rar|r\d{2,3}|zip|7z|gz|tar|001)$/i', '', $t);

        // Get core name without separators
        $coreName = preg_replace('/[.\-_\s]+/', '', $cleaned);

        // Check for UUID patterns
        if (preg_match('/^[a-f0-9]{8}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{4}-?[a-f0-9]{12}$/i', $coreName)) {
            return true;
        }

        // Check for pure hex strings (MD5, SHA1, etc.)
        if (preg_match('/^[a-f0-9]{16,}$/i', $coreName)) {
            return true;
        }

        // Analyze character patterns for randomness
        $coreLen = strlen($coreName);
        if ($coreLen >= 16 && preg_match('/^[a-zA-Z0-9]+$/', $coreName)) {
            $transitions = $this->countCharacterTransitions($coreName);
            $transitionRate = $transitions / ($coreLen - 1);

            // High transition rate suggests randomness
            if ($transitionRate > 0.35) {
                if (!preg_match('/\b(movie|film|series|episode|season|show|video|audio|music|album|dvd|bluray|hdtv|webrip|xvid|x264|x265|hevc|aac|mp3|flac|720p|1080p|2160p|4k|complete|proper|repack|dubbed|subbed|english|french|german|spanish|italian|rip|web|hdr|remux|disc|internal|retail)\b/i', $t)) {
                    return true;
                }
            }

            // Check for lack of word-like sequences
            $maxConsecutiveLetters = $this->getMaxConsecutiveLetters($coreName);
            if ($coreLen >= 20 && $maxConsecutiveLetters < 5) {
                if (!preg_match('/^[a-zA-Z]+\d{1,4}$/', $coreName)) {
                    return true;
                }
            }
        }

        // Check for random-looking patterns
        if (preg_match('/^[a-zA-Z]{1,3}\d{6,}[a-zA-Z]*$/i', $coreName) ||
            preg_match('/^[a-zA-Z0-9]{2,4}\d{8,}$/i', $coreName)) {
            return true;
        }

        return false;
    }

    /**
     * Count character type transitions in a string.
     */
    private function countCharacterTransitions(string $str): int
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
            } elseif (!$prevIsDigit && !$currIsDigit && $prevIsUpper !== $currIsUpper) {
                $transitions++;
            }
        }

        return $transitions;
    }

    /**
     * Get the maximum consecutive letter count in a string.
     */
    private function getMaxConsecutiveLetters(string $str): int
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

    /**
     * Clean filename for title matching against PreDB.
     *
     * @param string $filename The filename to clean
     * @return string The cleaned filename
     */
    public function cleanForTitleMatch(string $filename): string
    {
        // Remove file extension
        $clean = preg_replace('/\.(mkv|avi|mp4|m4v|wmv|mpg|mpeg|mov|ts|m2ts|vob|divx|flv|nfo|sfv|nzb|srr|srs|rar|r\d{2,4}|zip|7z|par2?|vol\d+[\+\-]\d+|\d{3})$/i', '', $filename);

        // Remove part/volume indicators
        $clean = preg_replace('/[\.\-_]?(part|vol|cd|dvd|disc|disk)\d+$/i', '', $clean);

        // Remove sample/proof indicators
        $clean = preg_replace('/[\.\-_](sample|proof|subs?)$/i', '', $clean);

        return trim($clean, " \t\n\r\0\x0B.-_");
    }

    /**
     * Normalize quality indicators in filenames.
     */
    public function normalizeQualityIndicators(string $fileName): string
    {
        $qualityMap = [
            '/\.4k$/i' => '.2160p',
            '/\.fullhd$/i' => '.1080p',
            '/\.hd$/i' => '.720p',
            '/\.int$/i' => '.INTERNAL',
            '/\.internal$/i' => '.INTERNAL',
        ];

        foreach ($qualityMap as $pattern => $replacement) {
            $fileName = preg_replace($pattern, $replacement, $fileName);
        }

        return $fileName;
    }

    /**
     * Check if a filename looks like a scene release.
     *
     * @param string $filename The filename to check
     * @return bool True if it appears to be a scene release
     */
    public function looksLikeSceneRelease(string $filename): bool
    {
        $baseName = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $filename);

        // Check for group suffix
        if (!preg_match('/\-[A-Za-z0-9]{2,15}$/', $baseName)) {
            return false;
        }

        // Check for word separation
        if (!preg_match('/[._-]/', $baseName)) {
            return false;
        }

        // Check for common scene tags
        $sceneTags = [
            '720p', '1080p', '2160p', '4k',
            'x264', 'x265', 'hevc', 'xvid', 'divx',
            'bluray', 'bdrip', 'dvdrip', 'hdtv', 'webrip', 'web-dl', 'webdl',
            'aac', 'ac3', 'dts', 'flac', 'mp3',
            'proper', 'repack', 'internal', 'retail',
            'pal', 'ntsc', 'multi', 'dual',
        ];

        $baseNameLower = strtolower($baseName);
        foreach ($sceneTags as $tag) {
            if (str_contains($baseNameLower, $tag)) {
                return true;
            }
        }

        // Check for TV episode patterns
        if (preg_match('/s\d{1,2}e\d{1,3}/i', $baseName)) {
            return true;
        }

        // Check for year pattern
        if (preg_match('/[._-](19|20)\d{2}[._-]/i', $baseName)) {
            return true;
        }

        return false;
    }
}

