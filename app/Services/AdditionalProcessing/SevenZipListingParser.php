<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing;

/**
 * Incremental parser for `7z l -slt -ba` output, fed in arbitrary chunks.
 *
 * Lines end only at LF, with one optional CR before it, and records end at blank lines.
 * Nothing else counts as a line break: the output is raw UTF-8 bytes, and Unicode-aware
 * line matching such as byte-mode PCRE `\R` would also break at the 0x85 byte inside
 * characters like "Å" (C3 85) or "久" (E4 B9 85).
 *
 * A record is rejected, and the listing marked unreliable, when it can't be read
 * unambiguously: a line that isn't `Key = value`, a property given twice, a Path with
 * control characters, or no Path or Size at all. Such output most likely comes from a
 * file name with a line break in it, so the record boundaries can't be trusted; callers
 * keep the other entries but must not treat the listing as complete.
 */
final class SevenZipListingParser
{
    /** @var list<array{name: string, size: int, date: int, pass: int, compressed: int, crc32?: string}> */
    public array $files = [];

    /** False once any record had to be rejected. */
    public bool $reliable = true;

    private string $partialLine = '';

    /** @var array<string, string> */
    private array $record = [];

    private bool $recordMalformed = false;

    public function feed(string $chunk): void
    {
        $start = 0;
        while (($end = strpos($chunk, "\n", $start)) !== false) {
            $this->line($this->partialLine.substr($chunk, $start, $end - $start));
            $this->partialLine = '';
            $start = $end + 1;
        }
        $this->partialLine .= substr($chunk, $start);
    }

    /**
     * End of output: the last line and record count even without a trailing line break.
     */
    public function finish(): void
    {
        if ($this->partialLine !== '') {
            $this->line($this->partialLine);
            $this->partialLine = '';
        }
        $this->endRecord();
    }

    private function line(string $line): void
    {
        if (str_ends_with($line, "\r")) {
            $line = substr($line, 0, -1);
        }
        if (strspn($line, " \t") === strlen($line)) {
            $this->endRecord();

            return;
        }

        $separator = strpos($line, ' = ');
        if ($separator !== false) {
            $key = substr($line, 0, $separator);
            $value = substr($line, $separator + 3);
        } elseif (str_ends_with($line, ' =')) {
            $key = substr($line, 0, -2);
            $value = '';
        } else {
            $this->recordMalformed = true;

            return;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9 ()._-]*$/', $key) !== 1 || array_key_exists($key, $this->record)) {
            $this->recordMalformed = true;

            return;
        }
        $this->record[$key] = $value;
    }

    private function endRecord(): void
    {
        $entry = $this->record;
        $malformed = $this->recordMalformed;
        $this->record = [];
        $this->recordMalformed = false;
        if ($entry === [] && ! $malformed) {
            return;
        }

        $path = $entry['Path'] ?? '';
        // C0 controls, DEL, and C1 controls in their UTF-8 form (C2 80 - C2 9F).
        if ($malformed || $path === '' || ! array_key_exists('Size', $entry)
            || preg_match('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F]/', $path) === 1
        ) {
            $this->reliable = false;

            return;
        }

        if (($entry['Folder'] ?? '') === '+' || str_starts_with($entry['Attributes'] ?? '', 'D')) {
            return;
        }

        $file = [
            'name' => $path,
            'size' => (int) $entry['Size'],
            'date' => (int) strtotime(substr($entry['Modified'] ?? '', 0, 19)),
            'pass' => ($entry['Encrypted'] ?? '') === '+' ? 1 : 0,
            'compressed' => in_array($entry['Method'] ?? '', ['', 'Copy'], true) ? 0 : 1,
        ];
        if (preg_match('/^[0-9A-F]{1,8}$/i', $entry['CRC'] ?? '') === 1) {
            $file['crc32'] = dechex((int) hexdec($entry['CRC']));
        }

        $this->files[] = $file;
    }
}
