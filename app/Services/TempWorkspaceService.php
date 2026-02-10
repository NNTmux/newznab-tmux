<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TempWorkspaceService
{
    /**
     * Ensure the main temp path exists and is namespaced by groupID or guidChar.
     * Returns the resolved main temp path.
     */
    public function ensureMainTempPath(string $basePath, string $guidChar = '', string $groupID = ''): string
    {
        // Normalize separator at end
        if (! Str::endsWith($basePath, ['/', '\\'])) {
            $basePath .= '/';
        }

        if ($groupID !== '') {
            $basePath .= $groupID.'/';
        } elseif ($guidChar !== '') {
            $basePath .= $guidChar.'/';
        }

        if (! File::isDirectory($basePath)) {
            if (! File::makeDirectory($basePath, 0777, true, true) && ! File::isDirectory($basePath)) { // @phpstan-ignore booleanNot.alwaysTrue
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $basePath));
            }
        }

        return $basePath;
    }

    /**
     * Create a per-release temp folder and return its path.
     */
    public function createReleaseTempFolder(string $mainTmpPath, string $guid): string
    {
        $tmpPath = rtrim($mainTmpPath, '/\\').'/'.$guid.'/';
        if (! File::isDirectory($tmpPath)) {
            if (! File::makeDirectory($tmpPath, 0777, true, false) && ! File::isDirectory($tmpPath)) { // @phpstan-ignore booleanNot.alwaysTrue
                // Try again once in case of transient FS issues
                if (! File::makeDirectory($tmpPath, 0777, true, false) && ! File::isDirectory($tmpPath)) { // @phpstan-ignore booleanNot.alwaysTrue, booleanNot.alwaysTrue, booleanAnd.alwaysTrue
                    throw new \RuntimeException('Unable to create directory: '.$tmpPath);
                }
            }
        }

        return $tmpPath;
    }

    /**
     * Delete files recursively. If $preserveRoot is true, only clear contents of $path.
     */
    public function clearDirectory(string $path, bool $preserveRoot = true): void
    {
        if ($path === '' || ! File::exists($path)) {
            return;
        }

        if (File::isDirectory($path)) {
            // Delete all files recursively
            foreach (File::allFiles($path) as $file) {
                File::delete($file->getPathname());
            }
            // Delete sub-directories
            foreach (File::directories($path) as $dir) {
                File::deleteDirectory($dir);
            }
            if (! $preserveRoot) {
                File::deleteDirectory($path);
            }
        } elseif (File::isFile($path)) {
            File::delete($path);
        }
    }

    /**
     * List files under a directory.
     * If $pattern is provided, return an array of preg_match($pattern, $relativePath, $matches) arrays,
     * where $matches[0] is the ABSOLUTE path for convenience (remaining capture groups preserved if present).
     * Otherwise return an array of SplFileInfo.
     *
     * @return array<int, array<int,string>|\SplFileInfo>
     */
    public function listFiles(string $path, string $pattern = ''): array
    {
        try {
            $files = File::allFiles($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException('ERROR: Could not open temp dir: '.$e->getMessage());
        }

        if ($pattern !== '') {
            $filtered = [];
            $base = rtrim($path, '/\\');
            foreach ($files as $file) {
                $relative = $file->getRelativePathname();
                if (preg_match($pattern, $relative, $matches)) {
                    // Overwrite full match with absolute path to make downstream file ops straightforward
                    $matches[0] = $base.'/'.$relative;
                    $filtered[] = $matches;
                }
            }

            return $filtered;
        }

        return $files;
    }
}
