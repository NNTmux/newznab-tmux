<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

use App\Services\NameFixing\Data\NzbSplitSubject;

final class NzbSplitUnwrapper
{
    private const string WRAPPER_PATTERN = '/__NZBSPLIT__([a-f0-9]{8,})__NZBSPLIT__(.+)$/is';

    private const string TAG_PATTERN = '/([A-Za-z0-9][A-Za-z0-9.+\-]{0,40})$/';

    private const string FILE_COUNTER_PATTERN = '/\[(\d{1,5})\/(\d{1,5})\]/';

    /**
     * Stripped from the end of an unquoted value, before any file suffix.
     *
     * @var list<string>
     */
    private const array TRAILING_SUBJECT_PATTERNS = [
        '/\s*\(\d+\/\d+\)$/',
        '/\s+-\s+\d+(?:[.,]\d+)?\s*[KMGT]i?B\b.*$/i',
        '/["\s]*yEnc.*$/i',
    ];

    /**
     * File suffixes, tried in order on every pass. The volume set pattern must run before the
     * plain `.par2` one, or `X.vol01+02.par2` loses only its extension.
     *
     * @var list<string>
     */
    private const array ARCHIVE_PATTERNS = [
        '/\.vol\d+[+\-]\d+\.par2?$/i',
        '/\.par2?$/i',
        '/\.part\d+\.rar$/i',
        '/\.7z\.\d{2,4}$/i',
        '/\.7z$/i',
        '/\.rar$/i',
        '/\.r\d{2,4}$/i',
        '/\.zip$/i',
        '/\.z\d{2}$/i',
        '/\.(?i:nfo|sfv|srr)$/',
    ];

    /**
     * Lowercase-only `ts` keeps an uppercase telesync tag (`Movie.2026.TS`) in the title.
     */
    private const string CONTENT_EXTENSION_PATTERN = '/\.((?i:mkv|mp4|m4v|m2ts|avi|mov|wmv|mpe?g|webm|pdf|epub|mobi|azw3|cbr|cbz)|ts)$/';

    /**
     * A bare split-file volume (`Show.mkv.001`). The lookbehind keeps codecs such as `H.264`.
     */
    private const string BARE_VOLUME_PATTERN = '/(?<![._ -][hHxX])\.\d{3}$/';

    private const string SAMPLE_PATTERN = '/[._ -]sample$/i';

    /**
     * Streaming brands whose `+` belongs to the name (`Disney+`), even when a word follows.
     */
    private const string PLUS_BRAND_PATTERN = '/(Disney|AppleTV|Paramount|Peacock|Canal|ESPN|Discovery|MGM|Victory|NESN)\+(?=[A-Za-z])/i';

    public function unwrap(string $value): ?string
    {
        return $this->parse($value)?->title;
    }

    public function parse(string $value): ?NzbSplitSubject
    {
        if (! preg_match(self::WRAPPER_PATTERN, $value, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $prefix = substr($value, 0, $matches[0][1]);
        $hash = strtolower($matches[1][0]);
        $rest = $matches[2][0];

        // A re-wrapped name keeps only the innermost title.
        while (preg_match(self::WRAPPER_PATTERN, $rest, $inner)) {
            $hash = strtolower($inner[1]);
            $rest = $inner[2];
        }

        $tag = preg_match(self::TAG_PATTERN, $prefix, $tagMatch) ? $tagMatch[1] : null;

        $fileIndex = null;
        $declaredFiles = null;
        if (preg_match(self::FILE_COUNTER_PATTERN, $prefix, $counter)) {
            $fileIndex = (int) $counter[1];
            $declaredFiles = (int) $counter[2] > 0 ? (int) $counter[2] : null;
        }

        [$title, $extension] = $this->stripSuffixes($this->stripSubjectTail($rest));
        $isSample = preg_match(self::SAMPLE_PATTERN, $title) === 1;
        $title = $this->normalize($title);

        if ($title === '') {
            return null;
        }

        return new NzbSplitSubject(
            tag: $tag,
            hash: $hash,
            title: $title,
            isSample: $isSample,
            extension: $extension,
            fileIndex: $fileIndex,
            declaredFiles: $declaredFiles,
        );
    }

    private function stripSubjectTail(string $value): string
    {
        $quote = strpos($value, '"');
        if ($quote !== false) {
            return trim(substr($value, 0, $quote));
        }

        do {
            $previous = $value;
            foreach (self::TRAILING_SUBJECT_PATTERNS as $pattern) {
                $value = rtrim((string) preg_replace($pattern, '', $value), " \t\n\r\0\x0B\"'");
            }
        } while ($value !== $previous);

        return trim($value);
    }

    /**
     * @return array{0: string, 1: ?string} The title and the first media or document extension removed.
     */
    private function stripSuffixes(string $value): array
    {
        $extension = null;
        $strippedAny = false;

        do {
            $previous = $value;

            foreach (self::ARCHIVE_PATTERNS as $pattern) {
                $value = (string) preg_replace($pattern, '', $value);
            }

            if (preg_match(self::CONTENT_EXTENSION_PATTERN, $value, $match)) {
                $extension ??= strtolower($match[1]);
                $value = substr($value, 0, -strlen($match[0]));
            }

            // Only the last suffix of the original value can be a bare volume number;
            // after anything was removed, `.300` in `UFC_300.7z.001` is part of the title.
            if (! $strippedAny && $value === $previous) {
                $value = (string) preg_replace(self::BARE_VOLUME_PATTERN, '', $value);
            }

            $strippedAny = $strippedAny || $value !== $previous;
        } while ($value !== $previous);

        return [$value, $extension];
    }

    private function normalize(string $title): string
    {
        $title = str_replace('_', '.', $title);
        $title = (string) preg_replace(self::PLUS_BRAND_PATTERN, '$1+.', $title);
        // `+` joining two words (`Ghana+Postgame`) or standing alone (`Turkey_+_Pre`) is a separator;
        // before a digit (`DD+5.1`) or at the end of a token (`HDR10+`, `Disney+`) it is part of the name.
        $title = (string) preg_replace('/(?<=[A-Za-z0-9])\+(?=[A-Za-z])/', '.', $title);
        $title = (string) preg_replace('/(?<![^.\s])\+/', '.', $title);
        $title = (string) preg_replace('/\s+/', '.', $title);
        $title = (string) preg_replace('/\.{2,}/', '.', $title);

        return trim($title, " \t\n\r\0\x0B.-_\"'");
    }
}
