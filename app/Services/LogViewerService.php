<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SplFileObject;

class LogViewerService
{
    public const int DEFAULT_LINES = 200;

    /**
     * @var list<int>
     */
    public const array DISPLAY_LINE_OPTIONS = [100, 200, 500, 1000];

    private string $logsDirectory;

    public function __construct(?string $logsDirectory = null)
    {
        $this->logsDirectory = rtrim($logsDirectory ?? storage_path('logs'), DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<int, array{
     *     path: string,
     *     name: string,
     *     directory: string|null,
     *     size: int,
     *     human_size: string,
     *     modified_at: CarbonImmutable,
     *     absolute_path: string
     * }>
     */
    public function availableLogs(): array
    {
        if (! File::isDirectory($this->logsDirectory)) {
            return [];
        }

        $logs = [];

        foreach (File::allFiles($this->logsDirectory) as $file) {
            $absolutePath = $file->getPathname();

            if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
                continue;
            }

            $relativePath = $this->relativePath($absolutePath);
            $directory = dirname($relativePath);
            $size = (int) $file->getSize();

            $logs[] = [
                'path' => $relativePath,
                'name' => $file->getFilename(),
                'directory' => $directory === '.' ? null : $directory,
                'size' => $size,
                'human_size' => $this->formatBytes($size),
                'modified_at' => CarbonImmutable::createFromTimestamp($file->getMTime()),
                'absolute_path' => $absolutePath,
            ];
        }

        usort($logs, function (array $left, array $right): int {
            $timestampComparison = $right['modified_at']->getTimestamp() <=> $left['modified_at']->getTimestamp();

            if ($timestampComparison !== 0) {
                return $timestampComparison;
            }

            return strcmp($left['path'], $right['path']);
        });

        return $logs;
    }

    public function isAvailableLog(string $relativePath): bool
    {
        return $this->findLog($relativePath) !== null;
    }

    /**
     * @return array{
     *     path: string,
     *     name: string,
     *     directory: string|null,
     *     size: int,
     *     human_size: string,
     *     modified_at: CarbonImmutable,
     *     absolute_path: string
     * }|null
     */
    public function findLog(string $relativePath): ?array
    {
        return $this->availableLogsMap()[$this->normalizeRelativePath($relativePath)] ?? null;
    }

    /**
     * @return array{content: string, displayed_line_count: int, total_lines: int}
     */
    public function readLatestLines(string $relativePath, int $lineLimit): array
    {
        $file = $this->openLogFile($relativePath);
        $lineLimit = $this->normalizeLineLimit($lineLimit);

        $buffer = [];
        $totalLines = 0;

        while (! $file->eof()) {
            $line = $file->fgets();

            if ($file->eof() && $line === '') {
                break;
            }

            $totalLines++;
            $buffer[] = $this->trimLineEnding($line);

            if (count($buffer) > $lineLimit) {
                array_shift($buffer);
            }
        }

        return [
            'content' => implode("\n", $buffer),
            'displayed_line_count' => count($buffer),
            'total_lines' => $totalLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     paginator: LengthAwarePaginator,
     *     total_matches: int
     * }
     */
    public function searchLog(string $relativePath, string $needle, int $page, int $perPage, array $query): array
    {
        $file = $this->openLogFile($relativePath);
        $needle = trim($needle);
        $page = max($page, 1);
        $perPage = $this->normalizeLineLimit($perPage);
        $startIndex = (($page - 1) * $perPage) + 1;
        $endIndex = $startIndex + $perPage - 1;

        $matches = [];
        $matchIndex = 0;
        $lineNumber = 0;

        while (! $file->eof()) {
            $line = $file->fgets();

            if ($file->eof() && $line === '') {
                break;
            }

            $lineNumber++;

            if (! $this->lineContains($line, $needle)) {
                continue;
            }

            $matchIndex++;

            if ($matchIndex < $startIndex || $matchIndex > $endIndex) {
                continue;
            }

            $matches[] = [
                'line_number' => $lineNumber,
                'content' => $this->trimLineEnding($line),
            ];
        }

        $paginator = new LengthAwarePaginator(
            $matches,
            $matchIndex,
            $perPage,
            $page,
            [
                'path' => url()->current(),
                'query' => Arr::except($query, 'page'),
            ]
        );

        return [
            'paginator' => $paginator,
            'total_matches' => $matchIndex,
        ];
    }

    /**
     * @return array<string, array{
     *     path: string,
     *     name: string,
     *     directory: string|null,
     *     size: int,
     *     human_size: string,
     *     modified_at: CarbonImmutable,
     *     absolute_path: string
     * }>
     */
    private function availableLogsMap(): array
    {
        $map = [];

        foreach ($this->availableLogs() as $log) {
            $map[$log['path']] = $log;
        }

        return $map;
    }

    private function openLogFile(string $relativePath): SplFileObject
    {
        $log = $this->findLog($relativePath);

        if ($log === null) {
            throw new RuntimeException('Selected log file is not available.');
        }

        return new SplFileObject($log['absolute_path'], 'r');
    }

    private function relativePath(string $absolutePath): string
    {
        $prefix = $this->logsDirectory.DIRECTORY_SEPARATOR;

        return $this->normalizeRelativePath(str_starts_with($absolutePath, $prefix)
            ? substr($absolutePath, strlen($prefix))
            : basename($absolutePath));
    }

    private function normalizeRelativePath(string $relativePath): string
    {
        return ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    }

    private function normalizeLineLimit(int $lineLimit): int
    {
        return in_array($lineLimit, self::DISPLAY_LINE_OPTIONS, true)
            ? $lineLimit
            : self::DEFAULT_LINES;
    }

    private function trimLineEnding(string $line): string
    {
        return rtrim($line, "\r\n");
    }

    private function lineContains(string $line, string $needle): bool
    {
        return mb_stripos($line, $needle) !== false;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 1).' '.$units[$unitIndex];
    }
}
