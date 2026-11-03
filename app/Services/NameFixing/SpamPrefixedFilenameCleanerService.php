<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;

/**
 * Service for cleaning releases with spam-prefixed subject lines.
 *
 * These are subject lines that have promotional/spam text BEFORE the standard
 * part indicator and quoted filename pattern. The real release name is in
 * the quoted filename, not in the spam prefix.
 *
 * Example inputs:
 * - (Rick Moranis randomly punched in the head by a man in NYC) [01/66] - "AutoCAD_Architecture_2026_Multilingual_Win_64bit_db_002_002 - Copy.par2"
 * - (Rick Moranis randomly punched in the head by a man in NYC) [72/83] - "Office.2021-2024.18730.20186.64Bit.rar"
 * - [Some random spam text here] (01/50) - "Actual.Release.Name-GROUP.mkv"
 * - Random News Headline Text (001/150) - "Software.Name.v1.0-TEAM.r00"
 *
 * The spam prefix is typically:
 * - Text in parentheses that doesn't look like a release name
 * - Random sentences, news headlines, or promotional text
 * - Anything that appears before the [##/##] or (##/##) pattern
 */
class SpamPrefixedFilenameCleanerService
{
    /**
     * Patterns to detect spam-prefixed subject lines.
     * These match subjects where there's text before the part indicator.
     */
    private const SPAM_PREFIXED_PATTERNS = [
        // (spam text) [##/##] - "filename.ext"
        '/^.+?\)\s*\[\d+\/\d+\]\s*-?\s*"([^"]+)"/',
        // (spam text) (##/##) - "filename.ext"
        '/^.+?\)\s*\(\d+\/\d+\)\s*-?\s*"([^"]+)"/',
        // [spam text] [##/##] - "filename.ext"
        '/^\[[^\]]+\]\s*\[\d+\/\d+\]\s*-?\s*"([^"]+)"/',
        // [spam text] (##/##) - "filename.ext"
        '/^\[[^\]]+\]\s*\(\d+\/\d+\)\s*-?\s*"([^"]+)"/',
        // Any text followed by [###/###] - "filename.ext" (3-digit part numbers)
        '/^.{10,}\s+\[\d{1,3}\/\d{1,3}\]\s*-?\s*"([^"]+)"/',
        // Any text followed by (###/###) - "filename.ext" (3-digit part numbers)
        '/^.{10,}\s+\(\d{1,3}\/\d{1,3}\)\s*-?\s*"([^"]+)"/',
    ];

    /**
     * Patterns that indicate the prefix is spam (not a valid release name).
     */
    private const SPAM_INDICATORS = [
        // Placeholder patterns like (????), (***), (---), (===), etc.
        '/^\(\?+\)$/',
        '/^\(\*+\)$/',
        '/^\(-+\)$/',
        '/^\(=+\)$/',
        '/^\(\.+\)$/',
        '/^\([#@!%^&]+\)$/',
        // Single word in parentheses that's not a release name pattern
        '/^\([a-zA-Z]{1,10}\)$/',
        // Sentences with spaces and normal words
        '/^[A-Z][a-z]+(\s+[a-z]+){3,}/',
        // News headline style
        '/randomly|punched|breaking|headline|news|click|visit|download|free|subscribe|join/i',
        // Contains "in the" / "of the" / "by a" etc.
        '/\b(in the|of the|by a|at the|on the|to the|for the)\b/i',
        // Contains personal names followed by actions
        '/^[A-Z][a-z]+\s+[A-Z][a-z]+\s+(is|was|has|had|will|would|can|could)/i',
    ];

    /**
     * File extensions to strip from the extracted name.
     */
    private const STRIP_EXTENSIONS = '/\.(par2|vol\d+\+\d+\.par2|rar|r\d{2,3}|nfo|sfv|nzb|zip|7z|part\d+\.rar|mkv|avi|mp4|m4v|mov|wmv|flv|webm|mpg|mpeg|ts|vob|iso|img)$/i';

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
     * Check if a subject line has a spam prefix before the part indicator.
     */
    public function isSpamPrefixedRelease(string $subject): bool
    {
        // First check if there's a pattern match
        foreach (self::SPAM_PREFIXED_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject)) {
                // Check if the prefix looks like spam
                if ($this->hasSpamPrefix($subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the text before the part indicator looks like spam.
     */
    private function hasSpamPrefix(string $subject): bool
    {
        // Extract the text before the part indicator
        if (preg_match('/^(.+?)[\[(]\d+\/\d+[)\]]/', $subject, $match)) {
            $prefix = trim($match[1]);

            // Check for placeholder patterns BEFORE stripping parentheses
            // Patterns like (????), (***), (---), etc.
            if (preg_match('/^\([?*\-=.#@!%^&]+\)\s*$/', $prefix)) {
                return true;
            }

            // Check for empty or near-empty parentheses like (), ( ), (  )
            if (preg_match('/^\(\s*\)\s*$/', $prefix)) {
                return true;
            }

            // Remove trailing punctuation for other checks
            $cleanedPrefix = rtrim($prefix, '()[]- ');

            // Check if prefix matches spam patterns
            foreach (self::SPAM_INDICATORS as $pattern) {
                if (preg_match($pattern, $cleanedPrefix)) {
                    return true;
                }
            }

            // If prefix is very long and contains multiple spaces, likely spam
            if (strlen($cleanedPrefix) > 30 && substr_count($cleanedPrefix, ' ') > 4) {
                return true;
            }

            // If prefix starts with parentheses containing natural language
            if (preg_match('/^\([^)]+\s+[^)]+\s+[^)]+\)$/', $prefix)) {
                // Contains multiple words in parentheses - likely spam
                return true;
            }
        }

        return false;
    }

    /**
     * Extract clean release name from the quoted filename in a spam-prefixed subject.
     */
    public function extractReleaseName(string $subject): ?string
    {
        foreach (self::SPAM_PREFIXED_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject, $match)) {
                $filename = $match[1];

                // Strip file extensions (apply multiple times for compound extensions like .rar.par2)
                $cleanName = $filename;
                $previousName = '';
                while ($previousName !== $cleanName) {
                    $previousName = $cleanName;
                    $cleanName = preg_replace(self::STRIP_EXTENSIONS, '', $cleanName);
                }

                // Clean up the name
                $cleanName = $this->cleanReleaseName($cleanName);

                if (! empty($cleanName) && strlen($cleanName) >= 5 && $this->isValidReleaseName($cleanName)) {
                    return $cleanName;
                }
            }
        }

        return null;
    }

    /**
     * Clean a release name by removing common patterns and normalizing.
     */
    protected function cleanReleaseName(string $name): string
    {
        // Remove " - Copy" suffix (common in the examples)
        $name = preg_replace('/\s*-\s*Copy$/i', '', $name);

        // Remove sample/proof markers
        $name = preg_replace('/[.\-_](sample|proof|thumbs?)$/i', '', $name);

        // Remove part markers like .part01
        $name = preg_replace('/\.part\d+$/i', '', $name);

        // Remove vol markers like .vol36+35
        $name = preg_replace('/\.vol\d+\+\d+$/i', '', $name);

        // Remove trailing dots, underscores, hyphens
        $name = rtrim($name, '._- ');

        // Normalize spaces and special characters
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Check if the extracted name looks like a valid release name.
     */
    protected function isValidReleaseName(string $name): bool
    {
        // Must not be just numbers
        if (preg_match('/^\d+$/', $name)) {
            return false;
        }

        // Must not look like a hash (MD5, SHA1, etc.)
        if (preg_match('/^[a-fA-F0-9]{32,}$/', $name)) {
            return false;
        }

        // Should have some structure (dots, underscores, or hyphens typical of release names)
        if (preg_match('/[._-]/', $name)) {
            return true;
        }

        // Or at least some mixed case
        if (preg_match('/[a-z]/', $name) && preg_match('/[A-Z0-9]/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Process a subject line and extract all relevant information.
     *
     * @return array{name: string|null, matched: bool}
     */
    public function processSubject(string $subject): array
    {
        if (! $this->isSpamPrefixedRelease($subject)) {
            return [
                'name' => null,
                'matched' => false,
            ];
        }

        return [
            'name' => $this->extractReleaseName($subject),
            'matched' => true,
        ];
    }

    /**
     * Process releases and fix names for spam-prefixed releases.
     *
     * @param  int  $limit  Maximum number of releases to process (0 = no limit)
     * @param  bool  $dryRun  If true, don't actually update the database
     * @return array{processed: int, renamed: int, skipped: int}
     */
    public function processReleases(int $limit = 0, bool $dryRun = false): array
    {
        $stats = [
            'processed' => 0,
            'renamed' => 0,
            'skipped' => 0,
        ];

        // Find releases that have parenthetical text before part indicators
        // and contain quoted filenames
        $query = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'groups_id', 'categories_id'])
            ->where(function ($q) {
                // (spam text) [##/##] pattern
                $q->where('name', 'REGEXP', '^\\(.+\\)\\s*\\[\\d+/\\d+\\].*"')
                    // (spam text) (##/##) pattern
                    ->orWhere('name', 'REGEXP', '^\\(.+\\)\\s*\\(\\d+/\\d+\\).*"')
                    // [spam text] [##/##] pattern
                    ->orWhere('name', 'REGEXP', '^\\[.+\\]\\s*\\[\\d+/\\d+\\].*"');
            })
            ->where('isrenamed', '=', 0);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        if ($this->echoOutput) {
            cli()->info('Found '.count($releases).' releases with spam-prefixed patterns.');
        }

        foreach ($releases as $release) {
            $stats['processed']++;

            $result = $this->processSubject($release->name);

            if (! $result['matched'] || $result['name'] === null) {
                $stats['skipped']++;

                continue;
            }

            // Skip if extracted name is the same as current searchname (case-insensitive)
            if (strtolower($result['name']) === strtolower($release->searchname)) {
                $stats['skipped']++;

                continue;
            }

            $updates = [
                'isrenamed' => 1,
                // Reset postprocessing so release gets re-processed with new name
                'videos_id' => 0,
                'tv_episodes_id' => 0,
                'imdbid' => null,
                'movieinfo_id' => null,
                'musicinfo_id' => null,
                'consoleinfo_id' => null,
                'gamesinfo_id' => 0,
                'bookinfo_id' => null,
                'anidbid' => null,
            ];

            if ($this->echoOutput) {
                cli()->primary("Release ID {$release->id}: {$release->searchname} -> {$result['name']}");
            }

            if (! $dryRun && ! $this->releaseUpdates()->applyRename(ReleaseIdentity::fromRelease($release), $result['name'], $updates)->written()) {
                // Renamed or replaced meanwhile, or the name does not fit; leave the newer row alone.
                $stats['skipped']++;

                continue;
            }

            $stats['renamed']++;
        }

        return $stats;
    }

    /**
     * Get a preview of what would be processed without making changes.
     *
     * @return list<array<string, mixed>> Array of release previews
     */
    public function previewReleases(int $limit = 100): array
    {
        $previews = [];

        $releases = Release::query()
            ->select(['id', 'name', 'searchname'])
            ->where(function ($q) {
                $q->where('name', 'REGEXP', '^\\(.+\\)\\s*\\[\\d+/\\d+\\].*"')
                    ->orWhere('name', 'REGEXP', '^\\(.+\\)\\s*\\(\\d+/\\d+\\).*"')
                    ->orWhere('name', 'REGEXP', '^\\[.+\\]\\s*\\[\\d+/\\d+\\].*"');
            })
            ->where('isrenamed', '=', 0)
            ->limit($limit)
            ->get();

        foreach ($releases as $release) {
            $result = $this->processSubject($release->name);

            if ($result['matched']) {
                $willRename = $result['name'] !== null
                    && strtolower($result['name']) !== strtolower($release->searchname);

                $previews[] = [
                    'id' => $release->id,
                    'original_name' => $release->name,
                    'current_searchname' => $release->searchname,
                    'extracted_name' => $result['name'],
                    'will_rename' => $willRename,
                ];
            }
        }

        return $previews;
    }
}
