<?php

declare(strict_types=1);

namespace App\Support;

/** Presentation only: keep stored subjects intact for subsequent name-recovery passes. */
final class ReleaseNameDisplay
{
    public static function clean(?string $name): string
    {
        $name ??= '';
        // Match the complete counter-and-quoted-file wrapper, including optional transport decorations.
        // Anchoring the suffix leaves titles with meaningful text after their quotes untouched.
        if (preg_match('/^\s*\[\s*\d+\s*\/\s*\d+\s*\]\s*-?\s*(?<quote>"|#34;|&#34;|&quot;)(?<file>(?:(?!\k<quote>)[^\r\n])+)\k<quote>\s*(?:-?\s*yEnc(?:\s+\(\d+\/\d+\))?)?\s*$/i', $name, $match) !== 1) {
            return $name;
        }

        $filename = trim($match['file']);

        return $filename === '' ? $name : $filename;
    }
}
