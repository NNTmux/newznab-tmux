<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseNfo;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class NfoImportService
 *
 * Imports NFO files from a defined folder and matches them with existing releases
 * based on exact searchname matching (100% match required).
 */
class NfoImportService
{
    /**
     * @var bool Echo output to CLI
     */
    protected bool $echo;

    /**
     * @var NfoService NFO processing service
     */
    protected NfoService $nfoService;

    /**
     * @var MovieService Movie processing service for IMDB updates
     */
    protected MovieService $movieService;

    /**
     * @var string|null Path to temporary files
     */
    protected ?string $tmpPath;

    /**
     * @var int Maximum number of NFOs to process in one run
     */
    protected int $maxNfos;

    /**
     * Constructor
     */
    public function __construct(NfoService $nfoService, MovieService $movieService)
    {
        $this->echo = config('nntmux.echocli');
        $this->nfoService = $nfoService;
        $this->movieService = $movieService;

        $this->tmpPath = config('nntmux.tmp_unrar_path');
        if ($this->tmpPath && ! preg_match('/[\/\\\\]$/', $this->tmpPath)) {
            $this->tmpPath .= '/';
        }

        $this->maxNfos = app(ConfigurationProvider::class)->postProcessing()->maxNfoProcessed;
    }

    /**
     * Begin importing NFO files from the specified directory
     *
     * @param  string  $importPath  Path to directory containing NFO files
     * @param  bool  $processImdb  Whether to process IMDB IDs found in NFOs
     * @param  bool  $processTv  Whether to process TV IDs found in NFOs
     * @param  bool  $delete  Whether to delete successfully imported files
     * @param  bool  $deleteFailed  Whether to delete files that failed to import
     * @return bool True on success, false on failure
     */
    public function beginImport(
        string $importPath,
        bool $processImdb = true,
        bool $processTv = true,
        bool $delete = false,
        bool $deleteFailed = false
    ): bool {
        // Validate import path
        if (! File::isDirectory($importPath)) {
            $error = "ERROR: Import path does not exist or is not accessible: {$importPath}";
            $this->echoOut($error);
            Log::channel('nfo_import')->error($error);

            return false;
        }

        $start = now();
        $nfosImported = $nfosSkipped = $nfosMatched = 0;

        // Get all .nfo files from the directory
        $nfoFiles = $this->getNfoFiles($importPath);

        if (empty($nfoFiles)) {
            $message = "No NFO files found in directory: {$importPath}";
            $this->echoOut($message);

            return true;
        }

        $this->echoOut('Found '.count($nfoFiles)." NFO files to process in: {$importPath}");

        // Process each NFO file
        foreach (array_slice($nfoFiles, 0, $this->maxNfos) as $nfoFile) {
            try {
                $result = $this->processSingleNfo(
                    $nfoFile,
                    $processImdb,
                    $processTv,
                    $delete,
                    $deleteFailed
                );

                switch ($result) {
                    case 'imported':
                        $nfosImported++;
                        break;
                    case 'matched':
                        $nfosMatched++;
                        break;
                    case 'skipped':
                        $nfosSkipped++;
                        break;
                }
            } catch (Throwable $e) {
                $this->echoOut("ERROR: Exception processing {$nfoFile}: ".$e->getMessage());
                Log::channel('nfo_import')->error('Exception processing NFO file', [
                    'file' => $nfoFile,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($deleteFailed) {
                    $this->safeDeleteFile($nfoFile);
                }
                $nfosSkipped++;
            }
        }

        // Output summary
        $duration = $start->diffInSeconds(now());
        $summary = sprintf(
            'Processed %d NFO files in %d seconds. Imported: %d, Matched existing: %d, Skipped: %d',
            count(array_slice($nfoFiles, 0, $this->maxNfos)),
            $duration,
            $nfosImported,
            $nfosMatched,
            $nfosSkipped
        );

        $this->echoOut($summary);
        Log::channel('nfo_import')->info($summary);

        return true;
    }

    /**
     * Get all NFO files from the specified directory
     *
     * @param  string  $path  Directory path
     * @return list<string> Array of NFO file paths
     */
    protected function getNfoFiles(string $path): array
    {
        try {
            $files = File::files($path);
            $nfoFiles = [];

            foreach ($files as $file) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, ['nfo', 'txt'])) {
                    $nfoFiles[] = $file->getPathname();
                }
            }

            return $nfoFiles;
        } catch (Throwable $e) {
            $this->echoOut("ERROR: Failed to read directory {$path}: ".$e->getMessage());
            Log::channel('nfo_import')->error('Failed to read directory', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Process a single NFO file
     *
     * @param  string  $nfoFile  Path to NFO file
     * @param  bool  $processImdb  Process IMDB IDs
     * @param  bool  $processTv  Process TV IDs
     * @param  bool  $delete  Delete file after successful import
     * @param  bool  $deleteFailed  Delete file after failed import
     * @return string Result status: 'imported', 'matched', or 'skipped'
     */
    protected function processSingleNfo(
        string $nfoFile,
        bool $processImdb,
        bool $processTv,
        bool $delete,
        bool $deleteFailed
    ): string {
        // Extract the expected release name from filename
        $expectedReleaseName = $this->extractReleaseNameFromFilename($nfoFile);

        if (empty($expectedReleaseName)) {
            $this->echoOut('SKIP: Could not extract release name from: '.basename($nfoFile));
            if ($deleteFailed) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'skipped';
        }

        // Read NFO content
        $nfoContent = File::get($nfoFile);
        if ($nfoContent === false || empty(trim($nfoContent))) {
            $this->echoOut('SKIP: Could not read or empty NFO file: '.basename($nfoFile));
            if ($deleteFailed) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'skipped';
        }

        // Find exact matching release by searchname
        $release = $this->findMatchingRelease($expectedReleaseName);

        if (! $release) {
            $this->echoOut("SKIP: No matching release found for: {$expectedReleaseName}");

            return 'skipped';
        }

        // Validate that this is actually an NFO file
        if (! $this->nfoService->isNFO($nfoContent, $release->guid)) {
            $this->echoOut('SKIP: File is not a valid NFO: '.basename($nfoFile));
            if ($deleteFailed) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'skipped';
        }

        // Check if release already has an NFO
        $existingNfo = ReleaseNfo::where('releases_id', $release->id)->first();
        if ($existingNfo) {
            $this->echoOut("MATCH: Release already has NFO: {$expectedReleaseName}");
            if ($delete) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'matched';
        }

        // Import the NFO
        $success = $this->importNfoForRelease($release, $nfoContent, $processImdb, $processTv);

        if ($success) {
            $this->echoOut("SUCCESS: Imported NFO for: {$expectedReleaseName}");
            Log::channel('nfo_import')->info('Successfully imported NFO', [
                'release_id' => $release->id,
                'release_name' => $release->searchname,
                'nfo_file' => basename($nfoFile),
            ]);

            if ($delete) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'imported';
        } else {
            $this->echoOut("ERROR: Failed to import NFO for: {$expectedReleaseName}");
            if ($deleteFailed) {
                $this->safeDeleteFile($nfoFile);
            }

            return 'skipped';
        }
    }

    /**
     * Extract release name from NFO filename
     * Removes common extensions and cleans the name
     *
     * @param  string  $filepath  Full path to NFO file
     * @return string Cleaned release name
     */
    protected function extractReleaseNameFromFilename(string $filepath): string
    {
        $filename = basename($filepath);

        // Remove file extension
        $name = preg_replace('/\.(nfo|txt)$/i', '', $filename);

        // Remove common suffixes that might be added to NFO files
        $name = preg_replace('/[-_\.]+(nfo|info)$/i', '', $name);

        // Clean up the name
        $name = trim($name);

        return $name;
    }

    /**
     * Find a release that exactly matches the given name
     *
     * @param  string  $releaseName  Release name to match
     * @return Release|null Matching release or null
     */
    protected function findMatchingRelease(string $releaseName): ?Release
    {
        // Try exact match on searchname first
        $release = Release::where('searchname', $releaseName)->first();

        if (! $release) {
            // Try exact match on name as fallback
            $release = Release::where('name', $releaseName)->first();
        }

        return $release;
    }

    /**
     * Import NFO content for a specific release
     *
     * @param  Release  $release  The release to add NFO to
     * @param  string  $nfoContent  NFO file content
     * @param  bool  $processImdb  Whether to process IMDB IDs
     * @param  bool  $processTv  Whether to process TV IDs
     * @return bool Success status
     */
    protected function importNfoForRelease(
        Release $release,
        string $nfoContent,
        bool $processImdb,
        bool $processTv
    ): bool {
        DB::beginTransaction();

        try {
            // Insert compressed NFO into database
            ReleaseNfo::create([
                'releases_id' => $release->id,
                'nfo' => "\x1f\x8b\x08\x00".gzcompress($nfoContent),
            ]);

            // Update release NFO status
            Release::where('id', $release->id)->update([
                'nfostatus' => NfoService::NFO_FOUND,
            ]);

            // Process IMDB IDs if requested
            if ($processImdb) {
                $this->processImdbIds($release, $nfoContent);
            }

            // Process TV IDs if requested
            if ($processTv) {
                $this->processTvIds($release, $nfoContent);
            }

            DB::commit();

            return true;

        } catch (Throwable $e) {
            DB::rollBack();
            Log::channel('nfo_import')->error('Failed to import NFO for release', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process IMDB IDs found in NFO content
     *
     * @param  Release  $release  The release
     * @param  string  $nfoContent  NFO content to search
     */
    protected function processImdbIds(Release $release, string $nfoContent): void
    {
        try {
            // Look for IMDB IDs in the NFO content
            if (preg_match('/(?:imdb.*?)?(?:tt|Title\?)(?P<imdbid>\d{5,8})/i', $nfoContent, $matches)) {
                $imdbId = $matches['imdbid'];

                // Use MovieService to update movie information
                $this->movieService->doMovieUpdate('tt'.$imdbId, 'NFOImport', $release->id, 1);

                $this->echoOut("  -> Found and processed IMDB ID: tt{$imdbId}");
            }
        } catch (Throwable $e) {
            Log::channel('nfo_import')->warning('Failed to process IMDB ID', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process TV show IDs found in NFO content
     *
     * @param  Release  $release  The release
     * @param  string  $nfoContent  NFO content to search
     */
    protected function processTvIds(Release $release, string $nfoContent): void
    {
        try {
            // Look for TV show IDs using NfoService method
            $showId = $this->nfoService->parseShowId($nfoContent);

            if ($showId !== false) {
                $this->echoOut("  -> Found TV show ID: {$showId['showid']} from {$showId['site']}");

                // Here you could add logic to update TV show information
                // This would typically involve calling TV processing classes
                // For now, just log the discovery
                Log::channel('nfo_import')->info('Found TV show ID in NFO', [
                    'release_id' => $release->id,
                    'show_id' => $showId['showid'],
                    'site' => $showId['site'],
                ]);
            }
        } catch (Throwable $e) {
            Log::channel('nfo_import')->warning('Failed to process TV ID', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safely delete a file with error handling
     *
     * @param  string  $filepath  Path to file to delete
     */
    protected function safeDeleteFile(string $filepath): void
    {
        try {
            if (File::exists($filepath)) {
                File::delete($filepath);
                $this->echoOut('  -> Deleted: '.basename($filepath));
            }
        } catch (Throwable $e) {
            $this->echoOut('WARNING: Could not delete file '.basename($filepath).': '.$e->getMessage());
            Log::channel('nfo_import')->warning('Could not delete file', [
                'file' => $filepath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Output message to CLI
     *
     * @param  string  $message  Message to output
     */
    protected function echoOut(string $message): void
    {
        if ($this->echo) {
            cli()->info($message);
        }
    }

    /**
     * Get statistics about NFO import candidates
     *
     * @param  string  $importPath  Path to check for NFO files
     * @return array<string, mixed> Statistics array
     */
    public function getImportStats(string $importPath): array
    {
        if (! File::isDirectory($importPath)) {
            return [
                'error' => 'Directory does not exist or is not accessible',
                'nfo_files' => 0,
                'potential_matches' => 0,
            ];
        }

        $nfoFiles = $this->getNfoFiles($importPath);
        $potentialMatches = 0;

        foreach ($nfoFiles as $nfoFile) {
            $releaseName = $this->extractReleaseNameFromFilename($nfoFile);
            if (! empty($releaseName) && $this->findMatchingRelease($releaseName)) {
                $potentialMatches++;
            }
        }

        return [
            'nfo_files' => count($nfoFiles),
            'potential_matches' => $potentialMatches,
            'import_path' => $importPath,
        ];
    }
}
