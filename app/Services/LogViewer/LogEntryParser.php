<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

/**
 * Recognises Monolog line headers and turns raw log text into {@see LogEntry} objects.
 *
 * Monolog writes `[2026-10-06 18:11:29] channel.LEVEL: message {context}` on the first line of an
 * entry; stack traces and multi-line context follow on continuation lines without a header.
 * Files that never contain a header (Horizon/supervisor console output) are treated as one entry per line.
 */
class LogEntryParser
{
    /**
     * @var list<string>
     */
    public const array LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    private const string HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+([\w.\-]+)\.([A-Z]+):\s?(.*)$/s';

    private const string PLAIN_TIMESTAMP_PATTERN = '/^\s*\[?(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/';

    public function isHeader(string $line): bool
    {
        return str_starts_with($line, '[') && preg_match(self::HEADER_PATTERN, $line) === 1;
    }

    /**
     * @return array{timestamp: string, channel: string, level: string|null, message: string}|null
     */
    public function parseHeader(string $line): ?array
    {
        if (! str_starts_with($line, '[') || preg_match(self::HEADER_PATTERN, $line, $matches) !== 1) {
            return null;
        }

        $level = strtolower($matches[3]);

        return [
            'timestamp' => $matches[1],
            'channel' => $matches[2],
            'level' => in_array($level, self::LEVELS, true) ? $level : null,
            'message' => $matches[4],
        ];
    }

    public function plainTimestamp(string $line): ?string
    {
        return preg_match(self::PLAIN_TIMESTAMP_PATTERN, $line, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Build an entry from its first line and the (already newline-joined) continuation lines.
     */
    public function make(
        int $offset,
        int $end,
        string $firstLine,
        string $body,
        bool $truncated,
        bool $structured,
        ?int $lineNumber = null,
    ): LogEntry {
        $header = $structured ? $this->parseHeader($firstLine) : null;

        if ($header !== null) {
            return new LogEntry(
                offset: $offset,
                end: $end,
                lineNumber: $lineNumber,
                timestamp: $header['timestamp'],
                channel: $header['channel'],
                level: $header['level'],
                message: $header['message'],
                body: $body,
                truncated: $truncated,
            );
        }

        return new LogEntry(
            offset: $offset,
            end: $end,
            lineNumber: $lineNumber,
            timestamp: $this->plainTimestamp($firstLine),
            channel: null,
            level: null,
            message: $firstLine,
            body: $body,
            truncated: $truncated,
        );
    }
}
