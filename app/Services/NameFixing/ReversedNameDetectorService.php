<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;
use Illuminate\Support\Facades\Log;

/**
 * Service for detecting and decoding reversed/obfuscated release names.
 *
 * Some uploaders reverse release names to avoid automated takedowns.
 * This service detects reversed strings and decodes them.
 *
 * Example inputs:
 * - 2D.20xoB011.furieziloP -> Polizeiruf.110Box02.D2
 * - p027.VTH.10E10S.sutatS -> Status.S01E01.HTV.720p
 * - pirT.daoR.0202 -> 2020.Road.Trip
 */
class ReversedNameDetectorService
{
    /**
     * Common release name patterns that indicate a valid (non-reversed) name.
     * Used to determine if a string is reversed or not.
     */
    private const VALID_NAME_PATTERNS = [
        // Starts with uppercase letter (most release names do)
        '/^[A-Z][a-z]/',
        // TV shows: Name.S01E01
        '/\.[Ss]\d{1,2}[Ee]\d{1,2}/',
        // Year patterns: .2020. or .2021.
        '/\.(19|20)\d{2}\./',
        // Quality indicators at reasonable positions
        '/\.(?:720p|1080p|2160p|4K|HDR)[\.\-]/i',
        // Source indicators
        '/\.(?:BluRay|BDRip|WEB|HDTV|DVDRip|WEBRip)[\.\-]/i',
        // Codec indicators
        '/\.(?:x264|x265|H\.?264|H\.?265|HEVC|XviD)[\.\-]/i',
        // Common German TV show prefixes
        '/^(?:Polizeiruf|Tatort|Derrick|Alarm|SOKO|Kommissar|Ein\.Fall|Der\.Alte)/i',
        // Common English words at start
        '/^(?:The|A|An|One|Two|Three|First|Last|New|Old|Big|Little)[\.\s]/i',
        // Movie/show names with years
        '/^[A-Z][a-zA-Z0-9\.]+\.(19|20)\d{2}\./i',
    ];

    /**
     * Patterns that indicate a reversed/invalid name.
     * If a name matches these, it's likely reversed.
     */
    private const REVERSED_INDICATORS = [
        // Ends with what looks like season/episode reversed: 10E10S.
        '/\d{1,2}[Ee]\d{1,2}[Ss]\./',
        // Year at wrong position (at end like .0202 for 2020)
        '/\.\d{2}(91|02)\b/',
        // Quality indicators at start (reversed)
        '/^(?:p027|p0801|p0612|K4|RDH)/i',
        // Source indicators at start (reversed)
        '/^(?:yaRulB|piRDB|BEW|VTDH|piRDVD|piRBEW)/i',
        // Ends with lowercase followed by uppercase (reversed camelCase)
        '/[a-z][A-Z]$/',
    ];

    /**
     * Patterns that indicate a hash/random obfuscated string (NOT a reversed name).
     * These should NOT be processed as reversed names.
     */
    private const HASH_PATTERNS = [
        // Long alphanumeric string without dots (typical hash)
        '/^[A-Za-z0-9]{20,}$/',
        // Base64-like pattern (alphanumeric with possible + and /)
        '/^[A-Za-z0-9+\/]{16,}={0,2}$/',
        // Random mixed case without structure (no dots, dashes, or underscores)
        '/^(?:[A-Z][a-z0-9]*|[a-z][A-Z0-9]*){5,}$/',
        // Hex-like strings
        '/^[A-Fa-f0-9]{24,}$/',
    ];

    /**
     * Minimum score difference to consider a name as reversed.
     */
    private const MIN_SCORE_DIFF = 2;

    protected bool $echoOutput;

    public function __construct(bool $echoOutput = false)
    {
        $this->echoOutput = $echoOutput;
    }

    private ?ReleaseUpdateService $releaseUpdates = null;

    /**
     * Renames go through the guarded path: row lock, byte-exact identity, search sync after commit.
     */
    public function setReleaseUpdateService(ReleaseUpdateService $releaseUpdates): void
    {
        $this->releaseUpdates = $releaseUpdates;
    }

    protected function releaseUpdates(): ReleaseUpdateService
    {
        return $this->releaseUpdates ??= app(ReleaseUpdateService::class);
    }

    /**
     * Check if a name appears to be reversed.
     *
     * @param  string  $name  The name to check
     * @return bool True if the name appears to be reversed
     */
    public function isReversed(string $name): bool
    {
        if (strlen($name) < 5) {
            return false;
        }

        // Clean name before analysis
        $cleanedName = $this->cleanName($name);
        if (strlen($cleanedName) < 5) {
            return false;
        }

        // Reject hash-like strings - these are obfuscated, not reversed
        if ($this->looksLikeHash($cleanedName)) {
            return false;
        }

        $originalScore = $this->scoreValidName($cleanedName);
        $reversedScore = $this->scoreValidName(strrev($cleanedName));

        // Also check for reversed indicators
        $hasReversedIndicators = $this->hasReversedIndicators($cleanedName);

        // If reversed version scores significantly better, it's likely reversed
        if ($reversedScore - $originalScore >= self::MIN_SCORE_DIFF) {
            return true;
        }

        // If has reversed indicators and reversed scores at least as good
        if ($hasReversedIndicators && $reversedScore >= $originalScore) {
            return true;
        }

        return false;
    }

    /**
     * Decode a potentially reversed name.
     *
     * @param  string  $name  The name to decode
     * @return array{decoded: bool, original: string, result: string, confidence: string}
     */
    public function decode(string $name): array
    {
        $result = [
            'decoded' => false,
            'original' => $name,
            'result' => $name,
            'confidence' => 'none',
        ];

        if (strlen($name) < 5) {
            return $result;
        }

        // Clean name before analysis to handle leading/trailing dots
        $cleanedName = $this->cleanName($name);
        if (strlen($cleanedName) < 5) {
            return $result;
        }

        // Reject hash-like strings - these are obfuscated, not reversed
        if ($this->looksLikeHash($cleanedName)) {
            return $result;
        }

        $reversed = strrev($cleanedName);
        $originalScore = $this->scoreValidName($cleanedName);
        $reversedScore = $this->scoreValidName($reversed);
        $scoreDiff = $reversedScore - $originalScore;

        if ($scoreDiff >= self::MIN_SCORE_DIFF) {
            $result['decoded'] = true;
            $result['result'] = $this->cleanName($reversed);

            if ($scoreDiff >= 5) {
                $result['confidence'] = 'high';
            } elseif ($scoreDiff >= 3) {
                $result['confidence'] = 'medium';
            } else {
                $result['confidence'] = 'low';
            }
        } elseif ($this->hasReversedIndicators($cleanedName) && $reversedScore >= $originalScore) {
            $result['decoded'] = true;
            $result['result'] = $this->cleanName($reversed);
            $result['confidence'] = 'low';
        }

        return $result;
    }

    /**
     * Score how likely a name is to be a valid release name.
     * Higher score = more likely to be valid.
     */
    protected function scoreValidName(string $name): int
    {
        $score = 0;

        // Check against valid patterns
        foreach (self::VALID_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                $score += 2;
            }
        }

        // Starts with uppercase letter
        if (preg_match('/^[A-Z]/', $name)) {
            $score += 1;
        }

        // Contains recognizable words (3+ letters that could be words)
        if (preg_match('/[A-Z][a-z]{2,}/', $name)) {
            $score += 1;
        }

        // Has reasonable dot-separated structure
        if (preg_match('/^[A-Za-z0-9]+(\.[A-Za-z0-9]+)+$/', $name)) {
            $score += 1;
        }

        // Penalize if starts with number followed by letters (often reversed)
        if (preg_match('/^\d+[A-Za-z]/', $name)) {
            $score -= 1;
        }

        // Penalize if ends with uppercase letter (unusual for release names)
        if (preg_match('/[A-Z]$/', $name) && ! preg_match('/^[A-Z]+$/', $name)) {
            $score -= 1;
        }

        return $score;
    }

    /**
     * Check if name has indicators that suggest it's reversed.
     */
    protected function hasReversedIndicators(string $name): bool
    {
        foreach (self::REVERSED_INDICATORS as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string looks like a hash or random obfuscated string.
     * These should NOT be treated as reversed names.
     */
    protected function looksLikeHash(string $name): bool
    {
        // Check against hash patterns
        foreach (self::HASH_PATTERNS as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        // Additional heuristics for hash-like strings:

        // No dots, dashes, or underscores (real release names have these)
        if (! preg_match('/[.\-_]/', $name)) {
            // Long alphanumeric string without separators
            if (strlen($name) >= 15 && preg_match('/^[A-Za-z0-9]+$/', $name)) {
                return true;
            }
        }

        // High entropy check: lots of mixed case transitions suggest random string
        $caseTransitions = 0;
        $len = strlen($name);
        for ($i = 1; $i < $len; $i++) {
            $prevUpper = ctype_upper($name[$i - 1]);
            $currUpper = ctype_upper($name[$i]);
            $prevAlpha = ctype_alpha($name[$i - 1]);
            $currAlpha = ctype_alpha($name[$i]);

            if ($prevAlpha && $currAlpha && $prevUpper !== $currUpper) {
                $caseTransitions++;
            }
        }

        // If more than 30% of characters are case transitions, it's likely random
        if ($len > 10 && $caseTransitions > ($len * 0.3)) {
            return true;
        }

        return false;
    }

    /**
     * Clean a decoded name.
     */
    protected function cleanName(string $name): string
    {
        // Remove leading/trailing dots and spaces
        $name = trim($name, '. ');

        // Normalize multiple dots
        $name = preg_replace('/\.{2,}/', '.', $name);

        return $name;
    }

    /**
     * Process a release and decode if name is reversed.
     *
     * @param  Release  $release  The release to process
     * @param  bool  $dryRun  If true, don't update the database
     * @return array{processed: bool, wasReversed: bool, originalName: string, decodedName: string|null, confidence: string}
     */
    public function processRelease(Release $release, bool $dryRun = false): array
    {
        $result = [
            'processed' => false,
            'wasReversed' => false,
            'originalName' => $release->searchname,
            'decodedName' => null,
            'confidence' => 'none',
        ];

        // Try to decode the searchname
        $decodeResult = $this->decode($release->searchname);

        if (! $decodeResult['decoded']) {
            return $result;
        }

        $result['processed'] = true;
        $result['wasReversed'] = true;
        $result['decodedName'] = $decodeResult['result'];
        $result['confidence'] = $decodeResult['confidence'];

        if (! $dryRun) {
            if (! $this->releaseUpdates()->applyRename(ReleaseIdentity::fromRelease($release), $decodeResult['result'], ['isrenamed' => 1], release: $release)->written()) {
                // Renamed or replaced meanwhile, or the name does not fit; leave the newer row alone.
                $result['processed'] = false;

                return $result;
            }
            $release->syncOriginal();

            Log::info('Reversed release name decoded', [
                'release_id' => $release->id,
                'original' => $result['originalName'],
                'decoded' => $result['decodedName'],
                'confidence' => $result['confidence'],
            ]);
        }

        return $result;
    }

    /**
     * Process multiple releases and decode reversed names.
     *
     * @param  int  $limit  Maximum number of releases to process (0 = no limit)
     * @param  bool  $dryRun  If true, don't update the database
     * @param  string  $minConfidence  Minimum confidence level to apply changes ('low', 'medium', 'high')
     * @return array{processed: int, decoded: int, skipped: int}
     */
    public function processReleases(int $limit = 0, bool $dryRun = false, string $minConfidence = 'low'): array
    {
        $stats = [
            'processed' => 0,
            'decoded' => 0,
            'skipped' => 0,
        ];

        $confidenceLevels = ['low' => 1, 'medium' => 2, 'high' => 3];
        $minConfidenceLevel = $confidenceLevels[$minConfidence] ?? 1;

        // Query releases that haven't been renamed and might be reversed
        // Look for patterns that suggest reversed names
        $query = Release::query()
            ->select(['id', 'guid', 'searchname', 'name', 'isrenamed'])
            ->where('isrenamed', '=', 0)
            ->where(function ($q) {
                // Look for names that might be reversed
                // Starts with number followed by letter
                $q->where('searchname', 'REGEXP', '^[0-9]+[A-Za-z]')
                    // Or contains reversed quality indicators
                    ->orWhere('searchname', 'LIKE', '%p027%')
                    ->orWhere('searchname', 'LIKE', '%p0801%')
                    ->orWhere('searchname', 'LIKE', '%p0612%')
                    // Or contains reversed source indicators
                    ->orWhere('searchname', 'LIKE', '%yaRulB%')
                    ->orWhere('searchname', 'LIKE', '%piRDB%')
                    ->orWhere('searchname', 'LIKE', '%VTDH%');
            })
            ->orderBy('id', 'desc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        foreach ($releases as $release) {
            $stats['processed']++;

            $decodeResult = $this->decode($release->searchname);

            if (! $decodeResult['decoded']) {
                $stats['skipped']++;

                continue;
            }

            // Check confidence level
            $resultConfidence = $confidenceLevels[$decodeResult['confidence']] ?? 0;
            if ($resultConfidence < $minConfidenceLevel) {
                $stats['skipped']++;

                continue;
            }

            if (! $dryRun) {
                $original = (string) $release->searchname;
                if (! $this->releaseUpdates()->applyRename(ReleaseIdentity::fromRelease($release), $decodeResult['result'], ['isrenamed' => 1])->written()) {
                    // Renamed or replaced meanwhile, or the name does not fit; leave the newer row alone.
                    $stats['skipped']++;

                    continue;
                }

                Log::info('Reversed release name decoded', [
                    'release_id' => $release->id,
                    'original' => $original,
                    'decoded' => $decodeResult['result'],
                    'confidence' => $decodeResult['confidence'],
                ]);
            }

            $stats['decoded']++;

            if ($this->echoOutput) {
                echo "Decoded: {$release->getOriginal('searchname')}\n";
                echo "      -> {$decodeResult['result']} [{$decodeResult['confidence']}]\n\n";
            }
        }

        return $stats;
    }

    /**
     * Preview what would be decoded from releases.
     *
     * @param  int  $limit  Maximum number of releases to preview
     * @return list<array<string, mixed>> Array of release details with decoded info
     */
    public function previewDecodings(int $limit = 100): array
    {
        $previews = [];

        // Get releases that might have reversed names
        $releases = Release::query()
            ->select(['id', 'guid', 'searchname', 'name', 'isrenamed'])
            ->where('isrenamed', '=', 0)
            ->orderBy('id', 'desc')
            ->limit($limit * 3) // Get more to filter
            ->get();

        foreach ($releases as $release) {
            if (count($previews) >= $limit) {
                break;
            }

            $decodeResult = $this->decode($release->searchname);

            if ($decodeResult['decoded']) {
                $previews[] = [
                    'id' => $release->id,
                    'guid' => $release->guid,
                    'original_name' => $release->searchname,
                    'decoded_name' => $decodeResult['result'],
                    'confidence' => $decodeResult['confidence'],
                    'already_renamed' => $release->isrenamed === 1,
                ];
            }
        }

        return $previews;
    }
}
