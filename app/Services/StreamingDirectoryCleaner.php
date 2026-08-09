<?php

declare(strict_types=1);

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Deletes files from arbitrarily large directory trees without materializing
 * a file list, keeping memory flat regardless of file count.
 */
final class StreamingDirectoryCleaner
{
    private int $failedDeletions = 0;

    public function __construct(private readonly int $progressInterval = 10000) {}

    /**
     * Recursively delete all regular files under $path, skipping any whose
     * basename is in $preserveBasenames. Directories are left in place.
     * Memory is O(1) with respect to the number of files.
     *
     * A file that cannot be unlinked is skipped (see failedDeletions()).
     * $onProgress, when given, is invoked with the running deletion count
     * every progressInterval deletions.
     *
     * @param  list<string>  $preserveBasenames
     * @param  (\Closure(int): void)|null  $onProgress
     * @return int number of files deleted
     */
    public function deleteFiles(string $path, array $preserveBasenames = [], ?\Closure $onProgress = null): int
    {
        $this->failedDeletions = 0;

        if ($path === '' || ! is_dir($path)) {
            return 0;
        }

        $preserve = array_flip($preserveBasenames);
        $deleted = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || isset($preserve[$file->getBasename()])) {
                continue;
            }

            if (! @unlink($file->getPathname())) {
                $this->failedDeletions++;

                continue;
            }

            $deleted++;
            if ($onProgress !== null && $deleted % $this->progressInterval === 0) {
                $onProgress($deleted);
            }
        }

        return $deleted;
    }

    /**
     * Number of files the most recent deleteFiles() run failed to unlink.
     */
    public function failedDeletions(): int
    {
        return $this->failedDeletions;
    }
}
