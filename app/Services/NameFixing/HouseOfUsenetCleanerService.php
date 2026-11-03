<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;

/**
 * Service for cleaning releases with House-of-Usenet style subject lines.
 *
 * Extracts clean release names from promotional subject lines.
 *
 * Example inputs:
 * - [29/30] - "HostelXXX 02 - Liza Rowe - HD1080p.vol36+35.par2" - 2,08 GB Join us @ <<House-of-Usenet.com>><<PW FREE 4 ALL>>
 * - [29/30] - "Creampie Filling 4 DiSC4 XXX DVDRip x264-DigitalSin.vol35+34.par2" - 2,03 GB PW free 4 all from <<HOUSE-OF-USENET.COM>> <<visit us>>
 */
class HouseOfUsenetCleanerService
{
    /**
     * Pattern to detect House-of-Usenet style subjects.
     */
    private const HOUSE_OF_USENET_PATTERN = '/house-of-usenet\.com/i';

    /**
     * Patterns to extract the clean release name from the quoted filename.
     */
    private const FILENAME_PATTERNS = [
        // Standard pattern: [##/##] - "filename.ext" - size
        '/\[\d+\/\d+\]\s*-\s*"([^"]+)"/',
        // Alternative: (##/##) - "filename.ext"
        '/\(\d+\/\d+\)\s*-\s*"([^"]+)"/',
    ];

    /**
     * File extensions to strip from the extracted name.
     */
    private const STRIP_EXTENSIONS = '/\.(par2|vol\d+\+\d+\.par2|rar|r\d{2,3}|nfo|sfv|nzb|zip|7z|part\d+\.rar)$/i';

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
     * Check if a subject line matches House-of-Usenet pattern.
     */
    public function isHouseOfUsenetRelease(string $subject): bool
    {
        return (bool) preg_match(self::HOUSE_OF_USENET_PATTERN, $subject);
    }

    /**
     * Extract clean release name from the quoted filename in subject.
     */
    public function extractReleaseName(string $subject): ?string
    {
        foreach (self::FILENAME_PATTERNS as $pattern) {
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

        // Normalize spaces and special characters
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Process a subject line and extract all relevant information.
     *
     * @return array{name: string|null, matched: bool}
     */
    public function processSubject(string $subject): array
    {
        if (! $this->isHouseOfUsenetRelease($subject)) {
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
     * Process releases and fix names for House-of-Usenet releases.
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

        $query = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'groups_id', 'categories_id'])
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%house-of-usenet%')
                    ->orWhere('name', 'LIKE', '%HOUSE-OF-USENET%');
            });

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        if ($this->echoOutput) {
            cli()->info('Found '.count($releases).' releases with House-of-Usenet patterns.');
        }

        foreach ($releases as $release) {
            $stats['processed']++;

            $result = $this->processSubject($release->name);

            if (! $result['matched']) {
                $stats['skipped']++;

                continue;
            }

            $updates = [];
            $logParts = [];
            // Handle name
            if ($result['name'] !== null && strtolower($result['name']) !== strtolower($release->searchname)) {
                $updates['searchname'] = $result['name'];
                $updates['isrenamed'] = 1;
                $logParts[] = "name: {$release->searchname} -> {$result['name']}";
                $stats['renamed']++;
            }

            if (! empty($updates)) {
                if ($this->echoOutput) {
                    cli()->primary("Release ID {$release->id}: ".implode(', ', $logParts));
                }

                if (! $dryRun) {
                    if (! $this->releaseUpdates()->applyRename(ReleaseIdentity::fromRelease($release), $updates['searchname'], ['isrenamed' => 1])->written()) {
                        // Renamed or replaced meanwhile, or the name does not fit; leave the newer row alone.
                        $stats['renamed']--;
                    }
                }
            }
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
                $q->where('name', 'LIKE', '%house-of-usenet%')
                    ->orWhere('name', 'LIKE', '%HOUSE-OF-USENET%');
            })
            ->limit($limit)
            ->get();

        foreach ($releases as $release) {
            $result = $this->processSubject($release->name);

            if ($result['matched']) {
                $previews[] = [
                    'id' => $release->id,
                    'original_name' => $release->name,
                    'current_searchname' => $release->searchname,
                    'extracted_name' => $result['name'],
                    'will_rename' => $result['name'] !== null && strtolower($result['name']) !== strtolower($release->searchname),
                ];
            }
        }

        return $previews;
    }
}
