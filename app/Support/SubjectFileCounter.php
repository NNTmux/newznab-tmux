<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Finds the file counter ("[03/12]", "(3 of 12)", "[-003/102]", "[62/ 115]")
 * in a Usenet subject.
 *
 * Ingest uses the counter twice: its total becomes collections.totalfiles,
 * and stripping it is what makes every file of a post clean to the same
 * collection name. Both steps must recognise the same formats, otherwise a
 * post is split into one collection per file.
 */
final class SubjectFileCounter
{
    /**
     * Opener: "[" or "(" (optionally followed by "-"), or whitespace.
     * Separator: "/" (spaces allowed around it), " of " / "_of_", or "-".
     * Closer: an optional bracket, or whitespace / ":" / end of subject.
     */
    private const string COUNTER = '(?:[\[(]-?\s*|\s)(\d{1,5})(?:\s*\/\s*|[\s_]of[\s_]|-)(\d{1,5})';

    private const string PARSE_PATTERN = '/'.self::COUNTER.'(?=\s*[\])]|[\s:]|$)/i';

    private const string STRIP_PATTERN = '/'.self::COUNTER.'(?:\s*[\])]|(?=[\s:]|$))/i';

    /**
     * First counter in the subject, or null when there is none.
     *
     * @return array{filenumber: int, totalfiles: int}|null
     */
    public static function parse(string $subject): ?array
    {
        if (preg_match(self::PARSE_PATTERN, $subject, $match) !== 1) {
            return null;
        }

        return ['filenumber' => (int) $match[1], 'totalfiles' => (int) $match[2]];
    }

    /**
     * Replace every counter in the subject with a single space.
     */
    public static function strip(string $subject): string
    {
        return preg_replace(self::STRIP_PATTERN, ' ', $subject) ?? $subject;
    }
}
