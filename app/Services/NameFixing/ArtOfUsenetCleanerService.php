<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Models\Release;
use App\Services\NameFixing\Data\ReleaseIdentity;
use Illuminate\Support\Facades\Log;

/**
 * Service for cleaning releases with Art-of-Usenet style subject lines.
 *
 * Art-of-Usenet uses reversed/obfuscated release names to avoid automated takedowns.
 * This service detects and decodes these patterns.
 *
 * Example inputs:
 * - 2D.20xoB011.furieziloP [Art-of-Usenet] [Password] "2D.20xoB011.furieziloP.par2"
 *   Decodes to: Polizeiruf.110Box02.D2
 *
 * - 1D.20xoB011.furieziloP [Art-of-Usenet] [Password] "filename.rar"
 *   Decodes to: Polizeiruf.110Box02.D1
 *
 * Common patterns:
 * - [Art-of-Usenet] - Group identifier
 * - [Password] - Indicates release is password protected (password on their site)
 * - Reversed alphanumeric strings as release names
 */
class ArtOfUsenetCleanerService
{
    /**
     * Pattern to detect Art-of-Usenet style subjects.
     */
    private const ART_OF_USENET_PATTERN = '/\[Art-of-Usenet\]/i';

    /**
     * Pattern to detect password indicator.
     */
    private const PASSWORD_INDICATOR_PATTERN = '/\[Password\]/i';

    /**
     * Pattern to extract the obfuscated name from the subject.
     * Matches the first part before [Art-of-Usenet] or the quoted filename.
     */
    private const OBFUSCATED_NAME_PATTERNS = [
        // Name at the start before [Art-of-Usenet]
        '/^([A-Za-z0-9._-]+)\s*\[Art-of-Usenet\]/i',
        // Name in quoted filename
        '/\"([A-Za-z0-9._-]+)\.(?:par2|nfo|sfv|rar|r\d{2,3}|part\d+\.rar)\"/i',
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
     * Check if a subject line matches Art-of-Usenet pattern.
     */
    public function isArtOfUsenetRelease(string $subject): bool
    {
        return (bool) preg_match(self::ART_OF_USENET_PATTERN, $subject);
    }

    /**
     * Check if the release is marked as password protected.
     */
    public function hasPasswordIndicator(string $subject): bool
    {
        return (bool) preg_match(self::PASSWORD_INDICATOR_PATTERN, $subject);
    }

    /**
     * Extract and decode the obfuscated release name.
     *
     * @param  string  $subject  The subject line to process
     * @return string|null The decoded release name, or null if not found
     */
    public function extractReleaseName(string $subject): ?string
    {
        $obfuscatedName = $this->extractObfuscatedName($subject);

        if ($obfuscatedName === null) {
            return null;
        }

        // Check if the name appears to be reversed
        $decodedName = $this->decodeObfuscatedName($obfuscatedName);

        if ($decodedName !== null && $this->isValidReleaseName($decodedName)) {
            return $decodedName;
        }

        return null;
    }

    /**
     * Extract the obfuscated name portion from the subject.
     */
    protected function extractObfuscatedName(string $subject): ?string
    {
        foreach (self::OBFUSCATED_NAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $subject, $match)) {
                $name = $match[1];
                // Strip file extension if present
                $name = preg_replace(self::STRIP_EXTENSIONS, '', $name);

                if (! empty($name) && strlen($name) >= 5) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Decode an obfuscated name (currently handles reversed strings).
     *
     * @param  string  $obfuscatedName  The obfuscated name
     * @return string|null The decoded name, or null if decoding failed
     */
    protected function decodeObfuscatedName(string $obfuscatedName): ?string
    {
        // Try reversing the string
        $reversed = strrev($obfuscatedName);

        // Check if the reversed version looks more like a valid release name
        if ($this->looksLikeValidReleaseName($reversed) && ! $this->looksLikeValidReleaseName($obfuscatedName)) {
            return $this->cleanReleaseName($reversed);
        }

        // If original already looks valid, return it cleaned
        if ($this->looksLikeValidReleaseName($obfuscatedName)) {
            return $this->cleanReleaseName($obfuscatedName);
        }

        // Default to reversed as Art-of-Usenet typically uses reversed names
        return $this->cleanReleaseName($reversed);
    }

    /**
     * Check if a string looks like a valid release name.
     * Valid names typically start with uppercase letters and contain recognizable words.
     */
    protected function looksLikeValidReleaseName(string $name): bool
    {
        // Check if starts with uppercase letter (most release names do)
        if (! preg_match('/^[A-Z]/', $name)) {
            return false;
        }

        // Check for common release name patterns
        $patterns = [
            // TV shows: Name.S01E01 or Name.101
            '/\.[Ss]\d{1,2}[Ee]\d{1,2}/',
            '/\.\d{3,4}\./',
            // Movies: Name.Year or Name.2020
            '/\.\d{4}\./',
            '/\.(?:720p|1080p|2160p|4K|BluRay|BDRip|WEB|HDTV)/i',
            // Box sets: BoxXX, DiscXX, DVDXX
            '/(?:Box|Disc|DVD|CD)\d+/i',
            // Common words that appear at the start of names
            '/^(?:The|A|An|Der|Die|Das|Le|La|Les|El|Los)[\.\s]/i',
            // German TV shows (like Polizeiruf, Tatort, etc.)
            '/^(?:Polizeiruf|Tatort|Derrick|Alarm|SOKO|Kommissar)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        // Check for reasonable word-like segments (3+ consecutive letters)
        if (preg_match_all('/[A-Za-z]{3,}/', $name, $matches)) {
            return count($matches[0]) >= 1;
        }

        return false;
    }

    /**
     * Check if a decoded name is valid.
     */
    protected function isValidReleaseName(string $name): bool
    {
        // Must be at least 5 characters
        if (strlen($name) < 5) {
            return false;
        }

        // Should contain at least one letter
        if (! preg_match('/[A-Za-z]/', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Clean a release name by normalizing formatting.
     */
    protected function cleanReleaseName(string $name): string
    {
        // Remove any leading/trailing dots or spaces
        $name = trim($name, '. ');

        // Normalize multiple dots to single dot
        $name = preg_replace('/\.{2,}/', '.', $name);

        return $name;
    }

    /**
     * Process a subject line and extract all relevant information.
     *
     * @return array{name: string|null, hasPassword: bool, matched: bool, originalObfuscated: string|null}
     */
    public function processSubject(string $subject): array
    {
        if (! $this->isArtOfUsenetRelease($subject)) {
            return [
                'name' => null,
                'hasPassword' => false,
                'matched' => false,
                'originalObfuscated' => null,
            ];
        }

        $obfuscatedName = $this->extractObfuscatedName($subject);

        return [
            'name' => $this->extractReleaseName($subject),
            'hasPassword' => $this->hasPasswordIndicator($subject),
            'matched' => true,
            'originalObfuscated' => $obfuscatedName,
        ];
    }

    /**
     * Process releases and fix names for Art-of-Usenet releases.
     *
     * @param  int  $limit  Maximum number of releases to process (0 = no limit)
     * @param  bool  $dryRun  If true, don't actually update the database
     * @return array{processed: int, renamed: int, passwordMarked: int, skipped: int}
     */
    public function processReleases(int $limit = 0, bool $dryRun = false): array
    {
        $stats = [
            'processed' => 0,
            'renamed' => 0,
            'passwordMarked' => 0,
            'skipped' => 0,
        ];

        $query = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'fromname', 'passwordstatus', 'isrenamed'])
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%Art-of-Usenet%')
                    ->orWhere('name', 'LIKE', '%[Password]%');
            })
            ->where('isrenamed', '=', 0);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $releases = $query->get();

        foreach ($releases as $release) {
            $stats['processed']++;

            $result = $this->processSubject($release->name);

            if (! $result['matched']) {
                $stats['skipped']++;

                continue;
            }

            $updated = false;

            // Update searchname if we decoded a better name
            if ($result['name'] !== null && $result['name'] !== $release->searchname) {
                if (! $dryRun && ! $this->releaseUpdates()->applyRename(ReleaseIdentity::fromRelease($release), $result['name'], ['isrenamed' => 1], release: $release)->written()) {
                    // Renamed or replaced meanwhile, or the name does not fit; leave the newer row alone.
                    $stats['skipped']++;

                    continue;
                }
                $release->syncOriginal();
                $stats['renamed']++;

                if ($this->echoOutput) {
                    echo "Renamed: {$release->name}\n";
                    echo "      -> {$result['name']}\n\n";
                }

                Log::info('Art-of-Usenet release renamed', [
                    'release_id' => $release->id,
                    'original' => $result['originalObfuscated'],
                    'decoded' => $result['name'],
                ]);
            }

            // Mark as password protected if indicated
            if ($result['hasPassword'] && $release->passwordstatus !== 1) {
                if (! $dryRun) {
                    $release->passwordstatus = 1; // Marked as passworded
                    $updated = true;
                }
                $stats['passwordMarked']++;

                Log::info('Art-of-Usenet release marked as passworded', [
                    'release_id' => $release->id,
                    'searchname' => $result['name'] ?? $release->searchname,
                ]);
            }

            if ($updated && ! $dryRun) {
                $release->save();
            }
        }

        return $stats;
    }

    /**
     * Preview what would be decoded from releases (dry run with details).
     *
     * @param  int  $limit  Maximum number of releases to preview
     * @return list<array<string, mixed>> Array of release details with decoded info
     */
    public function previewExtractions(int $limit = 100): array
    {
        $previews = [];

        $releases = Release::query()
            ->select(['id', 'guid', 'name', 'searchname', 'passwordstatus', 'isrenamed'])
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%Art-of-Usenet%')
                    ->orWhere('name', 'LIKE', '%[Password]%');
            })
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        foreach ($releases as $release) {
            $result = $this->processSubject($release->name);

            if ($result['matched']) {
                $previews[] = [
                    'id' => $release->id,
                    'guid' => $release->guid,
                    'original_name' => $release->name,
                    'current_searchname' => $release->searchname,
                    'obfuscated' => $result['originalObfuscated'],
                    'decoded_name' => $result['name'],
                    'has_password' => $result['hasPassword'],
                    'already_renamed' => $release->isrenamed === 1,
                ];
            }
        }

        return $previews;
    }
}
