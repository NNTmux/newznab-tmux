<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Services\NameFixing\Data\NzbSplitSubject;
use App\Traits\DetectsHashedNames;

/**
 * Utility class for cleaning and normalizing filenames.
 *
 * Extracts filename cleaning logic from NameFixer for better testability
 * and reusability.
 */
class FileNameCleaner
{
    use DetectsHashedNames;

    protected NzbSplitUnwrapper $nzbSplitUnwrapper;

    public function __construct(?NzbSplitUnwrapper $nzbSplitUnwrapper = null)
    {
        $this->nzbSplitUnwrapper = $nzbSplitUnwrapper ?? new NzbSplitUnwrapper;
    }

    /**
     * Generic DVD structure filenames and similarly low-information names.
     *
     * @var list<string>
     */
    private const IGNORABLE_NAME_PATTERNS = [
        '/^(?:audio|video)[._-]?ts$/i',
        '/^vts[._-]?\d{1,2}[._-]?\d{1,2}$/i',
        '/^\d{1,3}$/',
    ];

    /** `.001` split volumes, but not the codec in a name ending in `H.264` / `x.265`. */
    private const string SPLIT_VOLUME_EXTENSION = '/(?<![.\-_ ][hx])\.\d{3}$/i';

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
        self::SPLIT_VOLUME_EXTENSION,
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
     * @param  string  $fileName  The filename to clean
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

        $split = $this->parseNzbSplitName($fileName);
        if ($split !== null) {
            // A sample upload must never drive a PreDB match for the full release.
            if ($split->isSample || $this->isIgnorableForMatching($split->title) || $this->looksLikeStructuralJunk($split->title)) {
                return false;
            }

            return $split->title;
        }

        if ($this->isIgnorableForMatching($fileName)) {
            return false;
        }

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

        if ($fileName === '' || $this->looksLikeStructuralJunk($fileName)) {
            return false;
        }

        return $fileName;
    }

    /**
     * Extract filename from a path.
     *
     * @param  string  $path  Full path or filename
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
     * @param  string  $title  The title to normalize
     * @return string The normalized title
     */
    public function normalizeCandidateTitle(string $title): string
    {
        $t = trim($title);

        $split = $this->extractNzbSplitName($t);
        if ($split !== null) {
            return $split;
        }

        // Remove common video file extensions
        $t = preg_replace('/\.(mkv|avi|mp4|m4v|mpg|mpeg|wmv|flv|mov|ts|vob|iso|divx)$/i', '', $t) ?? $t;

        // Remove archive, metadata, and common document/file extensions
        $t = preg_replace(
            '/\.(par2?|nfo|sfv|nzb|rar|zip|7z|gz|tar|bz2|xz|r\d{2,3}|pkg|exe|msi|jpe?g|png|gif|bmp|pdf|epub|mobi|azw3?|djvu|cbr|cbz|fb2|lit|prc|opf|txt|log)$/i',
            '',
            $t
        ) ?? $t;
        $t = preg_replace(self::SPLIT_VOLUME_EXTENSION, '', $t) ?? $t;

        // Remove common trailing segment markers
        $t = preg_replace('/[.\-_ ](?:part|vol|r)\d+(?:\+\d+)?$/i', '', $t) ?? $t;

        // Collapse multiple spaces/underscores
        $t = preg_replace('/[\s_]+/', ' ', $t) ?? $t;

        // Trim stray punctuation
        return trim($t, " .-_\t\r\n");
    }

    /**
     * Format a candidate for storage in searchname.
     */
    public function formatSearchName(string $title, ?string $normalizedFallback = null): string
    {
        $formatted = trim($title);

        $split = $this->extractNzbSplitName($formatted);
        if ($split !== null) {
            return $split;
        }

        // Remove common media/archive extensions while keeping scene separators.
        $formatted = preg_replace(self::VIDEO_EXTENSIONS, '', $formatted) ?? $formatted;
        $formatted = preg_replace(self::AUDIO_EXTENSIONS, '', $formatted) ?? $formatted;
        $formatted = preg_replace(self::IMAGE_EXTENSIONS, '', $formatted) ?? $formatted;
        $formatted = preg_replace(self::EBOOK_EXTENSIONS, '', $formatted) ?? $formatted;
        $formatted = preg_replace(self::GAMEAPP_EXTENSIONS, '', $formatted) ?? $formatted;
        $formatted = preg_replace(self::SUBTITLE_EXTENSIONS, '', $formatted) ?? $formatted;

        foreach (self::ARCHIVE_PATTERNS as $pattern) {
            $formatted = preg_replace($pattern, '', $formatted) ?? $formatted;
        }

        $formatted = preg_replace('/[.\-_ ](?:part|vol|r)\d+(?:\+\d+)?$/i', '', $formatted) ?? $formatted;
        $formatted = trim($formatted, " .-_\t\r\n");

        if ($formatted === '') {
            return $normalizedFallback ?? $this->normalizeCandidateTitle($title);
        }

        if (! $this->looksLikeSceneRelease($formatted)) {
            return $normalizedFallback ?? $this->normalizeCandidateTitle($title);
        }

        $formatted = preg_replace('/[ _]+/', '.', $formatted) ?? $formatted;
        $formatted = preg_replace('/\.{2,}/', '.', $formatted) ?? $formatted;

        return trim($formatted, " .-_\t\r\n");
    }

    /**
     * Check if a title is plausible for a release.
     *
     * @param  string  $title  The title to check
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

        if ($this->isGenericFileName($t)) {
            return false;
        }

        // Group suffix must be preceded by dash and not be a file extension.
        $hasGroupSuffix = (bool) preg_match('/-[A-Za-z0-9]{2,15}$/', $t)
            && ! preg_match('/\.(exe|mp4|avi|mkv|rar|zip|nfo|sfv|par2?|jpg|png|gif|iso|mp3|flac|wav)$/i', $t);
        // Accept compact YYYYMMDD dates (e.g. sports releases like NBA.20260419....)
        $hasYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $t)
            || (bool) preg_match('/\b(19|20)\d{6}\b/', $t);
        // Accept broadcast-style resolutions such as 1080p60, 720pEN60fps or 1080iGRE25fps
        $hasQuality = (bool) preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|web[ .-]?dl|bluray|bdrip|dvdrip|hdtv|hdrip|xvid|x264|x265|hevc|h\.?264|ts|cam|r5|proper|repack)\b/i', $t)
            || (bool) preg_match('/\b(?:480|576|720|1080|2160)[pi](?:\d{2,3})?(?:[A-Z]{2,3})?(?:\d{2,3}fps)?\b/i', $t);
        $hasTV = (bool) preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);
        $hasSeason = SeasonPackDetector::isSeasonPack($t);
        $hasXXX = (bool) preg_match('/\bXXX\b/i', $t);

        return $hasGroupSuffix || (($hasTV || $hasSeason) && $hasQuality) || ($hasYear && ($hasQuality || $hasTV || $hasSeason)) || $hasXXX;
    }

    /**
     * Detect if a string looks like a generic/fake file name commonly used by spam uploaders.
     */
    private function isGenericFileName(string $title): bool
    {
        $t = strtolower(trim($title));

        $t = preg_replace('/\.(exe|mp4|avi|mkv|rar|zip|nfo|sfv|par2?|jpg|png|gif|iso|mp3|flac|wav)$/i', '', $t);
        $t = trim($t, ' .-_');

        $genericNames = [
            'porn games',
            'videolan mp4 video player',
            'duke nukem 3d',
            'citor 3',
            'wallpaper viewer',
            'media player',
            'video player',
            'image viewer',
            'photo viewer',
            'game launcher',
            'installer',
            'setup',
            'readme',
            'sample',
            'cover',
            'proof',
            'erotic porn',
        ];

        foreach ($genericNames as $genericName) {
            if ($t === $genericName) {
                return true;
            }
        }

        if (preg_match('/^[a-z0-9 ]{3,30}$/i', $t)) {
            $words = explode(' ', $t);
            $wordCount = count($words);

            if ($wordCount >= 2 && $wordCount <= 5) {
                $hasReleaseIndicator = preg_match('/\b(19|20)\d{2}\b/', $t)
                    || preg_match('/\b(480p|720p|1080p|2160p|4k|webrip|bluray|dvdrip|hdtv|xvid|x264|x265|hevc)\b/i', $t)
                    || preg_match('/\bS\d{1,2}[Eex]\d{1,3}\b/i', $t);

                if (! $hasReleaseIndicator) {
                    $genericWords = ['the', 'a', 'an', 'and', 'or', 'for', 'to', 'in', 'on', 'at', 'by', 'with',
                        'player', 'viewer', 'game', 'games', 'porn', 'video', 'mp4', 'media', 'photo', 'image',
                        'wallpaper', 'launcher', 'app', 'application', 'tool', 'software', 'program', 'erotic',
                        'hot', 'sexy', 'free', 'new', 'best', 'full', 'pro', 'premium', 'ultimate', 'super',
                        '3d', 'hd', 'pro', 'lite', 'plus', 'max', 'ultra', 'standard', 'advanced', 'basic'];

                    $genericWordCount = 0;
                    foreach ($words as $word) {
                        if (in_array(strtolower($word), $genericWords, true) || strlen($word) <= 2) {
                            $genericWordCount++;
                        }
                    }

                    if ($genericWordCount >= $wordCount - 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if a string looks like a hashed/obfuscated name.
     *
     * @param  string  $title  The title to check
     * @return bool True if the title appears to be hashed/obfuscated
     */
    public function looksLikeHashedName(string $title): bool
    {
        return $this->isHashedOrGibberish($title);
    }

    /**
     * Clean filename for title matching against PreDB.
     *
     * @param  string  $filename  The filename to clean
     * @return string The cleaned filename
     */
    public function cleanForTitleMatch(string $filename): string
    {
        $split = $this->extractNzbSplitName($filename);
        if ($split !== null) {
            return $split;
        }

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
     * @param  string  $filename  The filename to check
     * @return bool True if it appears to be a scene release
     */
    public function looksLikeSceneRelease(string $filename): bool
    {
        $filename = $this->extractNzbSplitName($filename) ?? $filename;

        $baseName = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $filename);

        // Check for group suffix
        if (! preg_match('/\-[A-Za-z0-9]{2,15}$/', $baseName)) {
            return false;
        }

        // Check for word separation
        if (! preg_match('/[._-]/', $baseName)) {
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

    public function extractNzbSplitName(string $value): ?string
    {
        return $this->nzbSplitUnwrapper->unwrap($value);
    }

    public function parseNzbSplitName(string $value): ?NzbSplitSubject
    {
        return $this->nzbSplitUnwrapper->parse($value);
    }

    /**
     * Ignore junk inputs that should never drive PreDB matches.
     */
    protected function isIgnorableForMatching(string $fileName): bool
    {
        if (preg_match('/\.url$/i', $fileName)) {
            return true;
        }

        return $this->looksLikeStructuralJunk((string) pathinfo($fileName, PATHINFO_FILENAME));
    }

    /**
     * Detect low-information DVD structure names and numeric image files.
     */
    protected function looksLikeStructuralJunk(string $fileName): bool
    {
        $normalized = strtolower(trim($fileName, " \t\n\r\0\x0B.-_"));

        if ($normalized === '') {
            return true;
        }

        foreach (self::IGNORABLE_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }
}
