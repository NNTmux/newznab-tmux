<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TempWorkspaceService
{
    /**
     * Ensure the main temp path exists and is namespaced by groupID or guidChar.
     * Returns the resolved main temp path.
     */
    public function ensureMainTempPath(
        string $basePath,
        string $guidChar = '',
        string $groupID = '',
        string $workerToken = ''
    ): string {
        // Normalize separator at end
        if (! Str::endsWith($basePath, ['/', '\\'])) {
            $basePath .= '/';
        }

        if ($groupID !== '') {
            $basePath .= $groupID.'/';
        } elseif ($guidChar !== '') {
            $basePath .= $guidChar.'/';
        }

        if ($workerToken !== '') {
            $basePath .= hash('sha256', $workerToken).'/';
        }

        $this->ensureWritableDirectory($basePath, 'Additional post-processing temp path');

        return $basePath;
    }

    /**
     * Create a per-release temp folder and return its path.
     */
    public function createReleaseTempFolder(string $mainTmpPath, string $guid): string
    {
        $this->assertWritableDirectory(rtrim($mainTmpPath, '/\\'), 'Additional post-processing temp path');

        $tmpPath = rtrim($mainTmpPath, '/\\').'/'.$guid.'/';
        $this->ensureWritableDirectory($tmpPath, 'Release temp path');

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
            // Delete all files recursively without materializing the tree
            (new StreamingDirectoryCleaner)->deleteFiles($path);
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

    public function pruneStaleWorkerDirectories(string $bucketPath, int $staleAfterSeconds): int
    {
        if ($bucketPath === '' || ! File::isDirectory($bucketPath)) {
            return 0;
        }

        $removed = 0;
        $staleBefore = time() - max(1, $staleAfterSeconds);

        foreach (File::directories($bucketPath) as $directory) {
            if (File::lastModified($directory) >= $staleBefore) {
                continue;
            }

            if (File::deleteDirectory($directory)) {
                $removed++;
            }
        }

        return $removed;
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
        } catch (Throwable $e) {
            throw new RuntimeException('ERROR: Could not open temp dir: '.$e->getMessage());
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

    private function ensureWritableDirectory(string $path, string $label): void
    {
        if (File::isDirectory($path)) {
            $this->repairDirectoryIfPossible($path);
        } else {
            try {
                if (! File::makeDirectory($path, 0777, true, true) && ! File::isDirectory($path)) { // @phpstan-ignore booleanNot.alwaysTrue
                    throw new RuntimeException('mkdir returned false');
                }
            } catch (Throwable $e) {
                throw new RuntimeException(sprintf(
                    '%s "%s" could not be created: %s',
                    $label,
                    $path,
                    $e->getMessage()
                ), 0, $e);
            }
        }

        $this->assertWritableDirectory($path, $label);
    }

    private function repairDirectoryIfPossible(string $path): void
    {
        if (is_writable($path)) {
            return;
        }

        @chmod($path, 0777);
        clearstatcache(true, $path);
        if (is_writable($path)) {
            return;
        }

        $normalizedPath = rtrim($path, '/\\');
        $parent = dirname($normalizedPath);
        if (! File::isDirectory($parent) || ! is_writable($parent)) {
            return;
        }

        File::deleteDirectory($normalizedPath);
        if (! File::isDirectory($normalizedPath)) {
            File::makeDirectory($normalizedPath, 0777, true, true);
        }
    }

    private function assertWritableDirectory(string $path, string $label): void
    {
        if (! File::isDirectory($path)) {
            throw new RuntimeException(sprintf('%s "%s" is not a directory', $label, $path));
        }

        if (! is_writable($path)) {
            throw new RuntimeException(sprintf(
                '%s "%s" is not writable by the PHP/tmux user. Check TEMP_UNRAR_PATH ownership and permissions.',
                $label,
                $path
            ));
        }
    }
}
