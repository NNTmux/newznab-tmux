<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;

/**
 * Service for cleaning releases with quoted filename style subject lines.
 *
 * Extracts clean release names from subject lines that contain quoted filenames
 * with part indicators, without requiring specific promotional markers.
 *
 * Example inputs:
 * - [1/10] "Industry.S04E02.The.Commander.and.The.Grey.Lady.1080p.AMZN.WEB-DL.DDP5.1.H.264-GP-TV-NLsubs.mkv"
 * - (01/50) "Some.Release.Name.720p.BluRay.x264-GROUP.rar"
 * - [15/30] - "Release.Name.HD1080p.vol36+35.par2"
 */
class QuotedFilenameCleanerService
{
    /**
     * Patterns to detect quoted filename style subjects.
     * These patterns match common usenet subject formats with part indicators and quoted filenames.
     */
    private const QUOTED_FILENAME_PATTERNS = [
        // [##/##] "filename.ext" or [##/##] - "filename.ext"
        '/^\s*\[\d+\/\d+\]\s*-?\s*"([^"]+)"/',
        // (##/##) "filename.ext" or (##/##) - "filename.ext"
        '/^\s*\(\d+\/\d+\)\s*-?\s*"([^"]+)"/',
        // (##/##) -Tag- "filename.ext" - handles uploader tags like -Olli-
        '/^\s*\(\d+\/\d+\)\s*-[A-Za-z0-9_]+?-\s*"([^"]+)"/',
        // [##/##] -Tag- "filename.ext" - handles uploader tags
        '/^\s*\[\d+\/\d+\]\s*-[A-Za-z0-9_]+?-\s*"([^"]+)"/',
        // (##/##) - Description - "filename.ext" - handles description tags
        '/^\s*\(\d+\/\d+\)\s*-\s*[^"]+\s*-\s*"([^"]+)"/',
        // [##/##] - Description - "filename.ext" - handles description tags
        '/^\s*\[\d+\/\d+\]\s*-\s*[^"]+\s*-\s*"([^"]+)"/',
        // ##/## - "filename.ext"
        '/^\s*\d+\/\d+\s*-\s*"([^"]+)"/',
        // "(##/##) "filename.ext""clean name" - duplicate name pattern
        '/^"\s*\(\d+\/\d+\)\s*"[^"]+"\s*"([^"]+)"/',
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
     * Check if a subject line matches the quoted filename pattern.
     */
    public function isQuotedFilenameRelease(string $subject): bool
    {
        foreach (self::QUOTED_FILENAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract clean release name from the quoted filename in subject.
     */
    public function extractReleaseName(string $subject): ?string
    {
        foreach (self::QUOTED_FILENAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject, $match)) {
                $filename = $match[1];

                // Strip file extension
                $cleanName = preg_replace(self::STRIP_EXTENSIONS, '', $filename);

                // Clean up the name
                $cleanName = $this->cleanReleaseName($cleanName);

                if (! empty($cleanName) && strlen($cleanName) >= 5) {
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
        // Remove sample/proof markers
        $name = preg_replace('/[.\-_](sample|proof|thumbs?)$/i', '', $name);

        // Remove part markers like .part01
        $name = preg_replace('/\.part\d+$/i', '', $name);

        // Remove vol markers like .vol36+35
        $name = preg_replace('/\.vol\d+\+\d+$/i', '', $name);

        // Remove -NLsubs, -ENGsubs, etc. subtitle markers (optional, keeps release name cleaner)
        // $name = preg_replace('/[.\-_](NL|EN|ENG|NLD|DUT|GER|FRE|SPA|ITA|POR|RUS|JAP|KOR|CHI|ARA|HEB|POL|CZE|HUN|ROM|GRE|TUR|DAN|SWE|NOR|FIN)subs?$/i', '', $name);

        // Normalize spaces and special characters
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Check if a searchname looks like a raw/uncleaned subject line.
     *
     * A raw subject typically starts with part indicators like [1/10] or (01/50)
     * and contains quoted filenames. If it doesn't match these patterns,
     * it's likely already been cleaned/renamed.
     */
    protected function looksLikeRawSubject(string $searchname): bool
    {
        // Check if it starts with [##/##] or (##/##) pattern (with optional uploader tag)
        if (preg_match('/^\s*[[(]\d+\/\d+[)\]]/', $searchname)) {
            return true;
        }

        // Check if it starts with ##/## pattern
        if (preg_match('/^\s*\d+\/\d+\s*-?\s*"/', $searchname)) {
            return true;
        }

        // Check for duplicate name pattern: "(##/##) "filename""cleanname"
        if (preg_match('/^"\s*\(\d+\/\d+\)\s*"/', $searchname)) {
            return true;
        }

        // If the searchname contains quotes with a filename pattern, it's likely raw
        if (preg_match('/^[^"]*"[^"]+(\.mkv|\.avi|\.mp4|\.rar|\.par2|\.nfo)"/', $searchname)) {
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
        if (! $this->isQuotedFilenameRelease($subject)) {
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
     * Process releases and fix names for quoted filename releases.
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

        // Find releases that start with [##/##] or (##/##) pattern and contain quoted filename
        // Exclude releases already handled by HouseOfUsenetCleanerService
        $query = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'groups_id', 'categories_id'])
            ->where(function ($q) {
                $q->where('name', 'REGEXP', '^\\s*\\[\\d+/\\d+\\].*"')
                    ->orWhere('name', 'REGEXP', '^\\s*\\(\\d+/\\d+\\).*"')
                    ->orWhere('name', 'REGEXP', '^"\\s*\\(\\d+/\\d+\\)'); // duplicate name pattern
            })
            ->where('name', 'NOT LIKE', '%house-of-usenet%')
            ->where('name', 'NOT LIKE', '%HOUSE-OF-USENET%')
            ->where('name', 'NOT LIKE', '%town.ag%')
            ->where('name', 'NOT LIKE', '%ssl-news%')
            ->where('isrenamed', '=', 0);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        if ($this->echoOutput) {
            cli()->info('Found '.count($releases).' releases with quoted filename patterns.');
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
            // (i.e., it doesn't start with [##/##] or (##/##) pattern - already cleaned)
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
                cli()->primary("Release ID {$release->id}: name: {$release->searchname} -> {$result['name']}");
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
                $q->where('name', 'REGEXP', '^\\s*\\[\\d+/\\d+\\].*"')
                    ->orWhere('name', 'REGEXP', '^\\s*\\(\\d+/\\d+\\).*"')
                    ->orWhere('name', 'REGEXP', '^"\\s*\\(\\d+/\\d+\\)'); // duplicate name pattern
            })
            ->where('name', 'NOT LIKE', '%house-of-usenet%')
            ->where('name', 'NOT LIKE', '%HOUSE-OF-USENET%')
            ->where('name', 'NOT LIKE', '%town.ag%')
            ->where('name', 'NOT LIKE', '%ssl-news%')
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
                    'will_rename' => $willRename,
                ];
            }
        }

        return $previews;
    }
}
