<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

/**
 * Service for prioritizing files during name matching.
 *
 * Handles file prioritization logic for PreDB matching and name extraction.
 */
class FilePrioritizer
{
    /**
     * Video file extensions.
     */
    private const VIDEO_EXTENSIONS = ['mkv', 'avi', 'mp4', 'm4v', 'wmv', 'divx', 'ts', 'm2ts'];

    /**
     * Priority levels (lower = higher priority).
     */
    private const PRIORITY_SRR = 1;
    private const PRIORITY_MAIN_RAR = 2;
    private const PRIORITY_FIRST_PART_RAR = 3;
    private const PRIORITY_VIDEO = 4;
    private const PRIORITY_NFO = 5;
    private const PRIORITY_OTHER_RAR = 6;
    private const PRIORITY_SAMPLE = 100;
    private const PRIORITY_OTHER = 50;

    /**
     * Prioritize files for name matching.
     *
     * Returns files sorted by usefulness for name matching:
     * 1. SRR files (contain original release name)
     * 2. Main RAR files (not split parts)
     * 3. First split RAR parts
     * 4. Video files
     * 5. NFO files
     * 6. Other files
     *
     * @param array $files Array of filenames
     * @return array Sorted array of filenames
     */
    public function prioritizeForMatching(array $files): array
    {
        $categorized = $this->categorizeFiles($files);

        // Merge in priority order
        return array_merge(
            $categorized['srr'],
            $categorized['mainRar'],
            $categorized['firstPart'],
            $categorized['video'],
            $categorized['nfo'],
            $categorized['other']
        );
    }

    /**
     * Prioritize files for PreDB matching.
     *
     * Similar to prioritizeForMatching but with slightly different priorities
     * optimized for PreDB filename lookups.
     *
     * @param array $files Array of filenames
     * @return array Sorted array of filenames
     */
    public function prioritizeForPreDb(array $files): array
    {
        $categorized = $this->categorizeFiles($files);

        // For PreDB, SRR files are most valuable, then main RARs
        return array_merge(
            $categorized['srr'],
            $categorized['mainRar'],
            $categorized['firstPart'],
            $categorized['video'],
            $categorized['other']
        );
    }

    /**
     * Categorize files into groups by type.
     *
     * @param array $files Array of filenames
     * @return array Categorized files
     */
    protected function categorizeFiles(array $files): array
    {
        $categories = [
            'srr' => [],
            'mainRar' => [],
            'firstPart' => [],
            'video' => [],
            'nfo' => [],
            'other' => [],
        ];

        foreach ($files as $file) {
            $lowerFile = strtolower($file);

            // Skip sample/proof files
            if ($this->isSampleOrProof($file)) {
                continue;
            }

            // Categorize by type
            if ($this->isSrrFile($lowerFile)) {
                $categories['srr'][] = $file;
            } elseif ($this->isMainRar($lowerFile)) {
                $categories['mainRar'][] = $file;
            } elseif ($this->isFirstSplitRar($lowerFile)) {
                $categories['firstPart'][] = $file;
            } elseif ($this->isVideoFile($lowerFile)) {
                $categories['video'][] = $file;
            } elseif ($this->isNfoFile($lowerFile)) {
                $categories['nfo'][] = $file;
            } else {
                $categories['other'][] = $file;
            }
        }

        // Sort video files by length (longer names often more descriptive)
        usort($categories['video'], fn ($a, $b) => strlen($b) - strlen($a));
        usort($categories['mainRar'], fn ($a, $b) => strlen($b) - strlen($a));

        return $categories;
    }

    /**
     * Get priority value for a filename based on CRC matching.
     *
     * @param string $filename The filename to check
     * @return int Priority value (lower = higher priority)
     */
    public function getCrcPriority(string $filename): int
    {
        $lower = strtolower($filename);

        if ($this->isSampleOrProof($filename)) {
            return self::PRIORITY_SAMPLE;
        }

        if ($this->isVideoFile($lower)) {
            return self::PRIORITY_VIDEO;
        }

        if ($this->isMainRar($lower)) {
            return self::PRIORITY_MAIN_RAR;
        }

        if ($this->isFirstSplitRar($lower)) {
            return self::PRIORITY_FIRST_PART_RAR;
        }

        if (preg_match('/\.(rar|r\d{2,3})$/i', $filename)) {
            return self::PRIORITY_OTHER_RAR;
        }

        if ($this->isNfoFile($lower)) {
            return self::PRIORITY_NFO;
        }

        return self::PRIORITY_OTHER;
    }

    /**
     * Check if a file is a sample or proof file.
     */
    protected function isSampleOrProof(string $filename): bool
    {
        return (bool) preg_match('/[\.\-_](sample|proof|subs?|thumbs?)[\.\-_]/i', $filename);
    }

    /**
     * Check if a file is an SRR file.
     */
    protected function isSrrFile(string $lowerFilename): bool
    {
        return str_ends_with($lowerFilename, '.srr') || str_ends_with($lowerFilename, '.srs');
    }

    /**
     * Check if a file is a main RAR (not split).
     */
    protected function isMainRar(string $lowerFilename): bool
    {
        return preg_match('/\.rar$/i', $lowerFilename) &&
               !preg_match('/\.part\d+\.rar$/i', $lowerFilename);
    }

    /**
     * Check if a file is the first split RAR part.
     */
    protected function isFirstSplitRar(string $lowerFilename): bool
    {
        return (bool) preg_match('/\.part0*1\.rar$/i', $lowerFilename);
    }

    /**
     * Check if a file is a video file.
     */
    protected function isVideoFile(string $lowerFilename): bool
    {
        $extension = pathinfo($lowerFilename, PATHINFO_EXTENSION);
        return in_array($extension, self::VIDEO_EXTENSIONS, true);
    }

    /**
     * Check if a file is an NFO file.
     */
    protected function isNfoFile(string $lowerFilename): bool
    {
        return str_ends_with($lowerFilename, '.nfo');
    }

    /**
     * Extract the most likely release name from multiple RAR files.
     *
     * Analyzes multiple RAR files from a release to determine the most likely
     * release name by finding common patterns.
     *
     * @param array $rarFiles Array of RAR filenames
     * @return string|null The most likely release name or null
     */
    public function findReleaseNameFromRarFiles(array $rarFiles): ?string
    {
        $candidates = [];

        foreach ($rarFiles as $file) {
            // Skip sample/proof files
            if ($this->isSampleOrProof($file)) {
                continue;
            }

            $extracted = $this->extractReleaseNameFromRar($file);
            if ($extracted !== null) {
                $candidates[$extracted] = ($candidates[$extracted] ?? 0) + 1;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Return the most common candidate
        arsort($candidates);
        $topCount = reset($candidates);
        $topCandidates = array_filter($candidates, fn ($count) => $count === $topCount);

        // If multiple candidates have the same count, prefer the longest one
        $best = null;
        foreach (array_keys($topCandidates) as $candidate) {
            if ($best === null || strlen($candidate) > strlen($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Extract release name from a RAR filename.
     */
    protected function extractReleaseNameFromRar(string $filename): ?string
    {
        // Extract filename from path
        if (preg_match('/[\\\\\/]([^\\\\\/]+)$/', $filename, $match)) {
            $filename = $match[1];
        }

        // Remove RAR extensions
        $baseName = preg_replace('/\.(rar|r\d{2,4}|part\d+\.rar|\d{3})$/i', '', $filename);

        // Check if it looks like a scene release name
        if (preg_match('/^([a-z0-9][a-z0-9._-]+[a-z0-9])\-([a-z0-9]{2,15})$/i', $baseName, $match)) {
            // Clean up any remaining artifacts
            $baseName = preg_replace('/[._-]?(sample|proof|subs?)$/i', '', $baseName);
            $baseName = trim($baseName, '.-_');

            if (strlen($baseName) >= 10) {
                return $baseName;
            }
        }

        return null;
    }
}

