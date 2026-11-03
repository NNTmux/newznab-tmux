<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

/**
 * Selects the most plausible PreDB hit for a filename-derived query.
 *
 * Search backends can return broad fuzzy matches for low-information filenames
 * like "1" or "VTS_01_1". This selector scores the top hits and only accepts
 * a candidate when the overlap with the cleaned filename is strong enough.
 */
class PredbMatchSelector
{
    private const MAX_RESULTS_TO_SCORE = 50;

    private const MIN_ACCEPTABLE_SCORE = 60.0;

    private const MIN_FILENAME_LENGTH = 12;

    /**
     * Release traits compared on normalized (lower-case, space-separated) names.
     *
     * @var array<string, array<string, string>>
     */
    private const TRAIT_PATTERNS = [
        'resolution' => ['/\b(?:480|576|720|1080|2160)p\b/' => '', '/\b(?:4k|uhd)\b/' => '2160p'],
        'codec' => ['/\b(?:x|h) ?264\b|\bavc\b/' => 'avc', '/\b(?:x|h) ?265\b|\bhevc\b/' => 'hevc', '/\bxvid\b/' => 'xvid', '/\bav1\b/' => 'av1'],
        'episode' => ['/\bs\d{1,2}e\d{1,3}\b/' => ''],
        'year' => ['/\b(?:19|20)\d{2}\b/' => ''],
    ];

    /**
     * @var list<string>
     */
    private const NOISE_TOKENS = [
        'a', 'an', 'and', 'audio', 'audio_ts', 'bup', 'cd', 'cover', 'covers',
        'disc', 'disk', 'dvd', 'file', 'files', 'folder', 'ifo', 'image',
        'images', 'img', 'jpg', 'jpeg', 'nfo', 'of', 'par2', 'part', 'pdf',
        'png', 'proof', 'rar', 'sample', 'screen', 'screens', 'sfv', 'srr',
        'srs', 'sub', 'subs', 'the', 'thumb', 'thumbs', 'txt', 'url',
        'video', 'video_ts', 'vob', 'vts', 'webp', 'zip',
    ];

    /**
     * @var list<string>
     */
    private const GENERIC_QUERY_PATTERNS = [
        '/^\d+$/',
        '/^(?:audio|video)[ ._-]?ts$/i',
        '/^vts[ ._-]?\d+(?:[ ._-]\d+)?$/i',
        '/^(?:image|img)\d*$/i',
        '/^(?:cd|dvd|disc|disk|part|vol)\d*$/i',
    ];

    protected FileNameCleaner $fileNameCleaner;

    protected PredbMatchGuard $guard;

    public function __construct(?FileNameCleaner $fileNameCleaner = null, ?PredbMatchGuard $guard = null)
    {
        $this->fileNameCleaner = $fileNameCleaner ?? new FileNameCleaner;
        $this->guard = $guard ?? new PredbMatchGuard;
    }

    /**
     * @param  array<int, array<string, mixed>|object>  $hits
     * @return array<string, mixed>|null
     */
    public function selectBestMatch(string $query, array $hits): ?array
    {
        $normalizedQuery = $this->normalizeForComparison($query);
        if (! $this->isSearchableQuery($normalizedQuery)) {
            return null;
        }

        $queryTokens = $this->extractMeaningfulTokens($normalizedQuery);
        if ($queryTokens === []) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0.0;

        foreach (array_slice($hits, 0, self::MAX_RESULTS_TO_SCORE) as $hit) {
            $hitData = is_array($hit) ? $hit : (array) $hit;
            $score = $this->scoreHit($normalizedQuery, $queryTokens, $hitData);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $hitData;
            }
        }

        if ($bestScore < self::MIN_ACCEPTABLE_SCORE) {
            return null;
        }

        return $bestMatch;
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  array<string, mixed>  $hit
     */
    protected function scoreHit(string $normalizedQuery, array $queryTokens, array $hit): float
    {
        $title = $this->normalizeForComparison((string) ($hit['title'] ?? ''));

        // Untrusted sources (xrel) hold loosely named P2P releases: only an identical title counts.
        if (! $this->guard->allowsFilenameMatch((string) ($hit['source'] ?? '')) && $title !== $normalizedQuery) {
            return 0.0;
        }

        $filename = $this->usableFilename($hit);
        if ($filename !== '' && $this->hasConflictingTraits($normalizedQuery, $filename)) {
            $filename = '';
        }

        if ($this->hasConflictingTraits($normalizedQuery, $title)) {
            $title = '';
        }

        if ($title === '' && $filename === '') {
            return 0.0;
        }

        $titleTokens = $this->extractMeaningfulTokens($title);
        $filenameTokens = $this->extractMeaningfulTokens($filename);

        $score = max(
            $this->scoreAgainstField($normalizedQuery, $queryTokens, $title, $titleTokens),
            $this->scoreAgainstField($normalizedQuery, $queryTokens, $filename, $filenameTokens) + ($filename !== '' ? 5.0 : 0.0)
        );

        if ($this->hasMarkerMismatch($normalizedQuery, $title, $filename, '/\bxxx\b/i')) {
            $score -= 30.0;
        }

        return max($score, 0.0);
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $fieldTokens
     */
    protected function scoreAgainstField(string $query, array $queryTokens, string $field, array $fieldTokens): float
    {
        if ($field === '') {
            return 0.0;
        }

        $similarity = $this->similarityPercent($query, $field);
        $sharedTokens = array_values(array_intersect($queryTokens, $fieldTokens));
        $sharedTokenCount = count($sharedTokens);
        $overlapRatio = $this->tokenOverlapRatio($queryTokens, $fieldTokens);

        $score = ($similarity * 0.6) + ($overlapRatio * 45.0) + ($sharedTokenCount * 6.0);

        if ($field === $query) {
            $score += 80.0;
        } elseif (strlen($query) >= 8 && str_contains($field, $query)) {
            $score += 18.0;
        }

        if ($this->longestSharedTokenLength($sharedTokens) >= 8) {
            $score += 8.0;
        }

        if ($sharedTokenCount === 0) {
            $score -= 15.0;
        }

        return $score;
    }

    /**
     * The hit's normalized filename, or '' when it must not be matched against: the source
     * announces unreliable filenames, or the filename is too generic to identify one release.
     *
     * @param  array<string, mixed>  $hit
     */
    protected function usableFilename(array $hit): string
    {
        if (! $this->guard->allowsFilenameMatch((string) ($hit['source'] ?? ''))) {
            return '';
        }

        $filename = $this->normalizePredbFilename((string) ($hit['filename'] ?? ''));
        if (strlen($filename) < self::MIN_FILENAME_LENGTH || count($this->extractMeaningfulTokens($filename)) < 2) {
            return '';
        }

        return $filename;
    }

    protected function normalizePredbFilename(string $filename): string
    {
        $cleaned = $this->fileNameCleaner->cleanForMatching($filename);
        $value = is_string($cleaned) && $cleaned !== '' ? $cleaned : $filename;

        return $this->normalizeForComparison($value);
    }

    protected function normalizeForComparison(string $value): string
    {
        $value = $this->fileNameCleaner->normalizeCandidateTitle($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function isSearchableQuery(string $normalizedQuery): bool
    {
        if ($normalizedQuery === '' || strlen($normalizedQuery) < 3) {
            return false;
        }

        foreach (self::GENERIC_QUERY_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalizedQuery)) {
                return false;
            }
        }

        $tokens = $this->extractMeaningfulTokens($normalizedQuery);
        if ($tokens === []) {
            return false;
        }

        if (count($tokens) === 1 && strlen($tokens[0]) < 5) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    protected function extractMeaningfulTokens(string $normalizedValue): array
    {
        $tokens = preg_split('/\s+/u', $normalizedValue) ?: [];
        $meaningfulTokens = [];

        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, self::NOISE_TOKENS, true)) {
                continue;
            }

            if (preg_match('/^(?:cd|dvd|disc|disk|part|vol)\d*$/i', $token)) {
                continue;
            }

            if (preg_match('/^\d{1,2}$/', $token)) {
                continue;
            }

            if (strlen($token) < 2) {
                continue;
            }

            $meaningfulTokens[] = $token;
        }

        return array_values(array_unique($meaningfulTokens));
    }

    /**
     * @param  list<string>  $leftTokens
     * @param  list<string>  $rightTokens
     */
    protected function tokenOverlapRatio(array $leftTokens, array $rightTokens): float
    {
        if ($leftTokens === []) {
            return 0.0;
        }

        $sharedTokens = array_intersect($leftTokens, $rightTokens);

        return count($sharedTokens) / count($leftTokens);
    }

    /**
     * @param  list<string>  $sharedTokens
     */
    protected function longestSharedTokenLength(array $sharedTokens): int
    {
        $longest = 0;

        foreach ($sharedTokens as $token) {
            $longest = max($longest, strlen($token));
        }

        return $longest;
    }

    protected function similarityPercent(string $left, string $right): float
    {
        $percent = 0.0;
        similar_text($left, $right, $percent);

        return $percent;
    }

    /**
     * Two names describe different releases when both state a resolution, codec, episode
     * or year and those values differ (e.g. x265 vs x264, 1080p vs 720p, S01E02 vs S01E03).
     */
    protected function hasConflictingTraits(string $query, string $candidate): bool
    {
        foreach (self::TRAIT_PATTERNS as $patterns) {
            $queryValues = $this->traitValues($query, $patterns);
            $candidateValues = $this->traitValues($candidate, $patterns);

            if ($queryValues !== [] && $candidateValues !== [] && array_intersect($queryValues, $candidateValues) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $patterns  regex => canonical value ('' keeps the matched text)
     * @return list<string>
     */
    protected function traitValues(string $normalizedValue, array $patterns): array
    {
        $values = [];
        foreach ($patterns as $pattern => $canonical) {
            if (preg_match_all($pattern, $normalizedValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $values[] = $canonical !== '' ? $canonical : (string) preg_replace('/\s+/', '', $match);
                }
            }
        }

        return array_values(array_unique($values));
    }

    protected function hasMarkerMismatch(string $query, string $title, string $filename, string $pattern): bool
    {
        $queryHasMarker = preg_match($pattern, $query) === 1;
        $candidateHasMarker = preg_match($pattern, $title) === 1 || preg_match($pattern, $filename) === 1;

        return $queryHasMarker xor $candidateHasMarker;
    }
}
