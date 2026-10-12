<?php

declare(strict_types=1);

namespace App\Services\Yenc;

final readonly class ArticleFrame
{
    /**
     * @param  array<string, string>  $header
     * @param  array<string, string>|null  $part
     * @param  array<string, string>  $trailer
     */
    private function __construct(
        public array $header,
        public ?array $part,
        public array $trailer,
        public int $payloadOffset,
        public int $payloadLength,
    ) {}

    public static function parse(string $text): ?self
    {
        $offset = 0;
        while (($begin = self::findControl($text, '=ybegin', $offset)) !== false) {
            $lineEnd = strpos($text, "\n", $begin);
            if ($lineEnd === false) {
                return null;
            }
            $header = self::fields(substr($text, $begin + 7, $lineEnd - $begin - 7), true);
            $offset = $lineEnd + 1;
            if (! isset($header['line'], $header['size'], $header['name'])) {
                continue;
            }

            $part = null;
            if (strncasecmp(substr($text, $offset, 7), '=ypart ', 7) === 0 || strncasecmp(substr($text, $offset, 7), "=ypart\t", 7) === 0) {
                $partEnd = strpos($text, "\n", $offset);
                if ($partEnd === false) {
                    return null;
                }
                $part = self::fields(substr($text, $offset + 6, $partEnd - $offset - 6));
                $offset = $partEnd + 1;
            }

            $end = self::findControl($text, '=yend', $offset);
            if ($end === false) {
                return null;
            }
            $nextBegin = self::findControl($text, '=ybegin', $offset);
            if ($nextBegin !== false && $nextBegin < $end) {
                $offset = $nextBegin;

                continue;
            }
            $trailerEnd = strpos($text, "\n", $end);

            return new self(
                $header,
                $part,
                self::fields(substr($text, $end + 5, ($trailerEnd === false ? strlen($text) : $trailerEnd) - $end - 5)),
                $offset,
                $end - $offset,
            );
        }

        return null;
    }

    private static function findControl(string $text, string $control, int $offset): int|false
    {
        if ($offset === 0 && strncasecmp($text, $control, strlen($control)) === 0
            && (! isset($text[strlen($control)]) || str_contains(" \t\r\n", $text[strlen($control)]))) {
            return 0;
        }
        $offset = max(0, $offset - 1);
        while (($newline = stripos($text, "\n".$control, $offset)) !== false) {
            $position = $newline + 1;
            $after = $position + strlen($control);
            if (! isset($text[$after]) || str_contains(" \t\r\n", $text[$after])) {
                return $position;
            }
            $offset = $after;
        }

        return false;
    }

    /** @return array<string, string> */
    private static function fields(string $line, bool $withName = false): array
    {
        $line = rtrim($line, "\r");
        $fields = [];
        preg_match_all('/(?:^|[ \t])([a-z][a-z0-9_-]*)=([^ \t]*)/i', $line, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $key = strtolower($match[1][0]);
            if ($withName && $key === 'name') {
                $nameOffset = $match[1][1] + strlen($match[1][0]) + 1;
                $fields[$key] = substr($line, $nameOffset);

                break;
            }
            $fields[$key] = $match[2][0];
        }

        return $fields;
    }
}
