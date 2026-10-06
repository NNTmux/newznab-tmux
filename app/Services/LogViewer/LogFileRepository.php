<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Lists readable files under the log directory. The listing doubles as the access whitelist:
 * only paths it returns can be read, searched, downloaded, truncated or deleted.
 */
class LogFileRepository
{
    private const int STRUCTURE_SNIFF_BYTES = 8192;

    private string $directory;

    /**
     * @var array<string, LogFile>|null
     */
    private ?array $files = null;

    public function __construct(private readonly LogEntryParser $parser, ?string $directory = null)
    {
        $directory = rtrim($directory ?? (string) config('nntmux.log_viewer.path', storage_path('logs')), '/\\');
        $realDirectory = realpath($directory);

        $this->directory = $realDirectory === false ? $directory : $realDirectory;
    }

    /**
     * @return list<LogFile> newest first
     */
    public function all(): array
    {
        return array_values($this->map());
    }

    public function find(string $relativePath): ?LogFile
    {
        return $this->map()[$this->normalize($relativePath)] ?? null;
    }

    public function truncate(LogFile $file): void
    {
        $handle = @fopen($file->absolutePath, 'r+');

        if ($handle === false) {
            throw new RuntimeException('Log file could not be opened for writing.');
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Log file is locked by another process.');
            }

            ftruncate($handle, 0);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
            clearstatcache(true, $file->absolutePath);
            $this->files = null;
        }
    }

    public function delete(LogFile $file): void
    {
        if (! @unlink($file->absolutePath)) {
            throw new RuntimeException('Log file could not be deleted.');
        }

        clearstatcache(true, $file->absolutePath);
        $this->files = null;
    }

    /**
     * @return array<string, LogFile>
     */
    private function map(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        if (! File::isDirectory($this->directory)) {
            return $this->files = [];
        }

        $prefix = $this->directory.DIRECTORY_SEPARATOR;
        $files = [];

        foreach (File::allFiles($this->directory) as $file) {
            $absolutePath = $file->getPathname();
            $realPath = realpath($absolutePath);

            if ($realPath === false || ! str_starts_with($realPath, $prefix) || ! is_file($realPath) || ! is_readable($realPath)) {
                continue;
            }

            $relativePath = $this->normalize(substr($absolutePath, strlen($prefix)));
            $directory = dirname($relativePath);

            $files[$relativePath] = new LogFile(
                path: $relativePath,
                name: $file->getFilename(),
                directory: $directory === '.' ? null : $directory,
                absolutePath: $realPath,
                size: (int) $file->getSize(),
                modifiedAt: CarbonImmutable::createFromTimestamp($file->getMTime()),
                structured: $this->looksStructured($realPath),
            );
        }

        uasort($files, static function (LogFile $left, LogFile $right): int {
            return [$right->modifiedAt->getTimestamp(), $left->path] <=> [$left->modifiedAt->getTimestamp(), $right->path];
        });

        return $this->files = $files;
    }

    private function looksStructured(string $absolutePath): bool
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $sample = (string) fread($handle, self::STRUCTURE_SNIFF_BYTES);
        fclose($handle);

        foreach (explode("\n", $sample) as $line) {
            if ($this->parser->isHeader(rtrim($line, "\r"))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    }
}
