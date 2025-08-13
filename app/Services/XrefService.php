<?php

namespace App\Services;

class XrefService
{
    /**
     * Regex patterns to parse Xref tokens (e.g. alt.binaries.* optionally followed by :number).
     */
    private const XREF_PATTERN_WITH_NUM = '/(^[a-zA-Z]{2,3}\\.(bin(aries|arios|aer))\\.[a-zA-Z0-9]?.+)(:\\d+)/';

    private const XREF_PATTERN_NO_NUM = '/(^[a-zA-Z]{2,3}\\.(bin(aries|arios|aer))\\.[a-zA-Z0-9]?.+)/';

    /**
     * Extracts valid Xref tokens from a space-separated Xref string.
     */
    public function extractTokens(?string $xref): array
    {
        if (empty($xref)) {
            return [];
        }
        $tokens = [];
        foreach (explode(' ', $xref) as $token) {
            if (preg_match(self::XREF_PATTERN_WITH_NUM, $token, $m) || preg_match(self::XREF_PATTERN_NO_NUM, $token, $m)) {
                $tokens[] = $m[0];
            }
        }

        return $tokens;
    }

    /**
     * Returns tokens that appear in $headerXref but not in $existingXref.
     */
    public function diffNewTokens(?string $existingXref, ?string $headerXref): array
    {
        $existing = $this->extractTokens($existingXref);
        $incoming = $this->extractTokens($headerXref);

        return array_values(array_diff($incoming, $existing));
    }
}
