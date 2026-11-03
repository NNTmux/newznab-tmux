<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;

/**
 * Service for cleaning releases with category-prefixed subject lines.
 *
 * These are subject lines that have a category tag in parentheses before the
 * actual release title, followed by file extensions.
 *
 * Example inputs:
 * - "(Porn Video) Milf Fucks Teen Guy Erotic Porn.part01.rar.vol031+32.par2"
 * - "(Porn Video) Busty Babe Gets Pounded By Huge Bbc Erotic Porn.part001.rar.vol031+32.par2"
 * - "(Porn Video) Brazzers - Taylor Sands Gets Her Pipes Cleaned Erotic Porn.part01.rar.vol031+32.par2"
 *
 * The category prefix is typically:
 * - Text in parentheses that describes the content type (e.g., "Porn Video", "Music", "Movie")
 * - Followed by the actual title with spaces
 * - Ends with file extension patterns (.part01.rar.vol031+32.par2)
 */
class CategoryPrefixedCleanerService
{
    /**
     * Known category prefixes to detect.
     * Add more as needed.
     */
    private const CATEGORY_PREFIXES = [
        'Porn Video',
        'Adult Video',
        'XXX Video',
        'XXX Movie',
        'Adult Movie',
        'Porn Movie',
        'Music Video',
        'Music',
        'Movie',
        'Video',
        'Game',
        'Software',
        'App',
        'eBook',
        'Book',
        'Audiobook',
        'Audio Book',
        'Magazine',
        'Comic',
        'Anime',
        'TV Show',
        'TV',
        'Series',
    ];

    /**
     * Patterns to detect category-prefixed subject lines.
     * These match subjects that start with a quoted category prefix.
     */
    private const CATEGORY_PREFIXED_PATTERNS = [
        // "(<category>) <title>.<extensions>" yEnc - captures everything after the prefix
        '/^"\(([^)]+)\)\s+(.+)"(?:\s*-?\s*yEnc)?$/i',
    ];

    /**
     * File extensions to strip from the extracted name.
     */
    private const STRIP_EXTENSIONS = '/\.(?:par2?|vol\d+\+\d+(?:\.par2?)?|rar|r\d{2,3}|nfo|sfv|nzb|zip|7z|part\d+(?:\.rar)?|mkv|avi|mp4|m4v|mov|wmv|flv|webm|mpg|mpeg|ts|vob|iso|img)$/i';

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
     * Check if a subject line matches the category-prefixed pattern.
     */
    public function isCategoryPrefixedRelease(string $subject): bool
    {
        // Quick check: must start with a quote and have a parenthetical prefix
        if (! preg_match('/^"\([^)]+\)\s+/', $subject)) {
            return false;
        }

        // Check if it matches any of our patterns
        foreach (self::CATEGORY_PREFIXED_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }

        // Alternative: check for known category prefixes
        foreach (self::CATEGORY_PREFIXES as $prefix) {
            if (stripos($subject, '"('.$prefix.')') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract the category prefix from the subject.
     */
    public function extractCategory(string $subject): ?string
    {
        if (preg_match('/^"\(([^)]+)\)/', $subject, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Extract clean release name from the category-prefixed subject.
     */
    public function extractReleaseName(string $subject): ?string
    {
        // Try to extract using patterns
        foreach (self::CATEGORY_PREFIXED_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject, $match)) {
                $title = $match[2];

                return $this->cleanReleaseName($title);
            }
        }

        // Fallback: manually parse the subject
        if (preg_match('/^"\(([^)]+)\)\s+(.+)"?$/', $subject, $match)) {
            $title = $match[2];

            return $this->cleanReleaseName($title);
        }

        return null;
    }

    /**
     * Clean a release name by removing file extensions and normalizing.
     */
    protected function cleanReleaseName(string $name): string
    {
        // Remove trailing yEnc (with optional leading dash/space)
        $name = preg_replace('/\s*-?\s*yEnc$/i', '', $name);

        // Remove trailing quote if present
        $name = rtrim($name, '"');

        // Remove compound extension patterns like .part01.rar.vol031+32.par2
        // Handle the common pattern: .partXXX.rar.volXXX+XX.par2
        $name = preg_replace('/\.part\d+\.rar\.vol\d+\+\d+\.par2?$/i', '', $name);
        $name = preg_replace('/\.part\d+\.rar\.vol\d+\+\d+$/i', '', $name);

        // Handle .partXXX.rar pattern
        $name = preg_replace('/\.part\d+\.rar$/i', '', $name);

        // Handle standalone vol+par patterns
        $name = preg_replace('/\.vol\d+\+\d+\.par2?$/i', '', $name);

        // Remove file extensions (apply multiple times for compound extensions)
        $previousName = '';
        while ($previousName !== $name) {
            $previousName = $name;
            $name = preg_replace(self::STRIP_EXTENSIONS, '', $name);
        }

        // Remove sample/proof markers
        $name = preg_replace('/[.\-_](sample|proof|thumbs?)$/i', '', $name);

        // Remove part markers like .part01
        $name = preg_replace('/\.part\d+$/i', '', $name);

        // Remove vol markers like .vol36+35
        $name = preg_replace('/\.vol\d+\+\d+$/i', '', $name);

        // Remove trailing dots, underscores, hyphens
        $name = rtrim($name, '._- ');

        // Normalize multiple spaces
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Check if the extracted name looks like a valid release name.
     */
    protected function isValidReleaseName(string $name): bool
    {
        // Must be at least 5 characters
        if (strlen($name) < 5) {
            return false;
        }

        // Must not be just numbers
        if (preg_match('/^\d+$/', $name)) {
            return false;
        }

        // Must not look like a hash (MD5, SHA1, etc.)
        if (preg_match('/^[a-fA-F0-9]{32,}$/', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Process a subject line and extract all relevant information.
     *
     * @return array{name: string|null, category: string|null, matched: bool}
     */
    public function processSubject(string $subject): array
    {
        if (! $this->isCategoryPrefixedRelease($subject)) {
            return [
                'name' => null,
                'category' => null,
                'matched' => false,
            ];
        }

        $name = $this->extractReleaseName($subject);
        $category = $this->extractCategory($subject);

        if ($name !== null && ! $this->isValidReleaseName($name)) {
            $name = null;
        }

        return [
            'name' => $name,
            'category' => $category,
            'matched' => true,
        ];
    }

    /**
     * Check if a searchname looks like a raw/uncleaned subject line with category prefix.
     */
    protected function looksLikeRawSubject(string $searchname): bool
    {
        // Check if it starts with "(<category>) pattern
        if (preg_match('/^"\([^)]+\)\s+/', $searchname)) {
            return true;
        }

        // Check if it contains file extension patterns at the end
        if (preg_match('/\.(par2?|vol\d+\+\d+\.par2?|part\d+\.rar)"?$/i', $searchname)) {
            return true;
        }

        return false;
    }

    /**
     * Process releases and fix names for category-prefixed releases.
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

        // Find releases that match the category-prefixed pattern
        // Pattern: "(<category>) <title>.<extensions>"
        $query = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'groups_id', 'categories_id'])
            ->where('name', 'REGEXP', '^"\\([^)]+\\)\\s+.+\\.(par2|rar|vol[0-9])')
            ->where('isrenamed', '=', 0);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        if ($this->echoOutput) {
            cli()->info('Found '.count($releases).' releases with category-prefixed patterns.');
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

            // Skip if the searchname doesn't look like a raw subject line
            if (! $this->looksLikeRawSubject($release->searchname)) {
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
            ->where('name', 'REGEXP', '^"\\([^)]+\\)\\s+.+\\.(par2|rar|vol[0-9])')
            ->where('isrenamed', '=', 0)
            ->limit($limit)
            ->get();

        foreach ($releases as $release) {
            $result = $this->processSubject($release->name);

            if ($result['matched']) {
                $willRename = $result['name'] !== null
                    && strtolower($result['name']) !== strtolower($release->searchname)
                    && $this->looksLikeRawSubject($release->searchname);

                $previews[] = [
                    'id' => $release->id,
                    'original_name' => $release->name,
                    'current_searchname' => $release->searchname,
                    'extracted_name' => $result['name'],
                    'extracted_category' => $result['category'],
                    'will_rename' => $willRename,
                ];
            }
        }

        return $previews;
    }
}
