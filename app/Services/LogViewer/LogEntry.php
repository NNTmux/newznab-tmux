<?php

declare(strict_types=1);

namespace App\Services\LogViewer;

/**
 * One logical log entry: a Monolog header line plus its continuation lines, or a single plain line.
 *
 * `offset` and `end` are byte positions in the file; `offset` doubles as the pagination cursor.
 */
final readonly class LogEntry
{
    public function __construct(
        public int $offset,
        public int $end,
        public ?int $lineNumber,
        public ?string $timestamp,
        public ?string $channel,
        public ?string $level,
        public string $message,
        public string $body,
        public bool $truncated,
    ) {}

    /**
     * @return array{
     *     offset: int,
     *     end: int,
     *     line: int|null,
     *     timestamp: string|null,
     *     channel: string|null,
     *     level: string|null,
     *     message: string,
     *     body: string,
     *     truncated: bool,
     *     message_segments: list<array{text: string, hit: bool}>|null,
     *     body_segments: list<array{text: string, hit: bool}>|null
     * }
     */
    public function toArray(?SearchQuery $query = null): array
    {
        $highlight = $query !== null && $query->hasTerm();

        return [
            'offset' => $this->offset,
            'end' => $this->end,
            'line' => $this->lineNumber,
            'timestamp' => $this->timestamp,
            'channel' => $this->channel,
            'level' => $this->level,
            'message' => $this->message,
            'body' => $this->body,
            'truncated' => $this->truncated,
            'message_segments' => $highlight ? $query->segments($this->message) : null,
            'body_segments' => $highlight && $this->body !== '' ? $query->segments($this->body) : null,
        ];
    }
}
