<?php

declare(strict_types=1);

namespace App\Services;

/**
 * GamesTitleParser - Parse and clean game release titles.
 *
 * Extracts clean game titles from scene release names by removing
 * common scene group names, edition tags, version numbers, and other noise.
 */
class GamesTitleParser
{
    // Scene/release group patterns
    protected const array SCENE_GROUPS = [
        'CODEX', 'PLAZA', 'GOG', 'CPY', 'HOODLUM', 'EMPRESS', 'RUNE', 'TENOKE', 'FLT',
        'RELOADED', 'SKIDROW', 'PROPHET', 'RAZOR1911', 'CORE', 'REFLEX', 'P2P', 'GOLDBERG',
        'DARKSIDERS', 'TINYISO', 'DOGE', 'ANOMALY', 'ELAMIGOS', 'FITGIRL', 'DODI', 'XATAB',
        'GOG-GAMES', 'BLG', 'RARGB', 'CHRONOS', 'FCKDRM', 'I_KnoW', 'STEAM', 'PLAZA',
        'SPTGAMES', 'DARKSiDERS', 'TiNYiSO', 'KaOs', 'SiMPLEX', 'ElAmigos', 'FitGirl',
        'PROPHET', 'ALI213', 'FLTDOX', '3DMGAME', 'POSTMORTEM', 'VACE', 'ROGUE', 'OUTLAWS',
    ];

    // Legacy regex pattern for fallback
    protected const string LEGACY_TITLE_REGEX =
        '#(?P<title>[\w\s\.]+)(-(?P<relgrp>FLT|RELOADED|Elamigos|SKIDROW|PROPHET|RAZOR1911|CORE|REFLEX))?\s?(\s*(\?('.
        '(?P<reltype>PROPER|MULTI\d|RETAIL|CRACK(FIX)?|ISO|(RE)?(RIP|PACK))|(?P<year>(19|20)\d{2})|V\s?'.
        '(?P<version>(\d+\.)+\d+)|(-\s)?(?P=relgrp))\)?)\s?)*\s?(\.\w{2,4})?#i';

    /**
     * Parse a release title and extract the clean game title.
     *
     * @return array{title: string, release: string}|false
     */
    public function parse(string $releaseName): array|false
    {
        $name = $this->cleanReleaseName($releaseName);

        if ($name === '') {
            return $this->fallbackParse($releaseName);
        }

        return [
            'title' => $name,
            'release' => $releaseName,
        ];
    }

    /**
     * Clean release name by removing scene artifacts.
     */
    protected function cleanReleaseName(string $name): string
    {
        // Remove simple file extensions at the end
        $name = (string) preg_replace('/\.(zip|rar|7z|iso|nfo|sfv|exe|mkv|mp4|avi)$/i', '', $name);
        $name = str_replace('%20', ' ', $name);

        // Remove leading bracketed tag like [GROUP] or [PC]
        $name = (string) preg_replace('/^\[[^]]+\]\s*/', '', $name);

        // Remove "PC ISO) (" artifact
        $name = (string) preg_replace('/^(PC\s*ISO\)\s*\()/i', '', $name);

        // Remove common bracketed/parenthesized tags (languages, qualities, platforms, versions)
        $name = (string) preg_replace('/\[[^]]{1,80}\]|\([^)]{1,80}\)/', ' ', $name);

        // Remove edition and extra tags
        $name = (string) preg_replace(
            '/\b(Game\s+of\s+the\s+Year|GOTY|Definitive Edition|Deluxe Edition|Ultimate Edition|Complete Edition|Remastered|HD Remaster|Directors? Cut|Anniversary Edition)\b/i',
            ' ',
            $name
        );

        // Remove update/patch followed by version numbers
        $name = (string) preg_replace('/\b(Update|Patch|Hotfix)\b[\s._-]*v?\d+(?:\.\d+){1,}\b/i', ' ', $name);

        // Remove MULTI tokens
        $name = (string) preg_replace('/\bMULTI\d+|MULTi\d+\b/i', ' ', $name);

        // Remove version tokens
        $name = (string) preg_replace('/(?:^|[\s._-])v\d+(?:[\s._-]\d+){1,}(?=$|[\s._-])/i', ' ', $name);
        $name = (string) preg_replace('/(?:^|[\s._-])\d+(?:[\s._-]\d+){2,}(?=$|[\s._-])/', ' ', $name);

        // Remove other common release tags
        $name = (string) preg_replace('/\b(Incl(?:uding)?\s+DLCs?|DLCs?|PROPER|REPACK|RIP|ISO|CRACK(?:FIX)?|BETA|ALPHA)\b/i', ' ', $name);
        $name = (string) preg_replace('/\b(Update|Patch|Hotfix)\b/i', ' ', $name);

        // Remove scene group suffix
        $groupAlternation = implode('|', array_map('preg_quote', self::SCENE_GROUPS));
        $name = (string) preg_replace('/\s*-\s*(?:'.$groupAlternation.'|[A-Z0-9]{2,})\s*$/i', '', $name);

        // Replace separators with spaces
        $name = (string) preg_replace('/[._+]+/', ' ', $name);

        // Second pass: remove edition tokens now that separators are normalized
        $name = (string) preg_replace(
            '/\b(Game\s+of\s+the\s+Year|GOTY|Definitive Edition|Deluxe Edition|Ultimate Edition|Complete Edition|Remastered|HD Remaster|Directors? Cut|Anniversary Edition)\b/i',
            ' ',
            $name
        );
        $name = (string) preg_replace('/\b(Incl|Including|DLC|DLCs)\b/i', ' ', $name);

        // Token-based cleanup for version sequences
        $name = $this->cleanVersionTokens($name);

        // Remove spaced-out version sequences
        $name = (string) preg_replace('/(?:^|\s)v\d+(?:\s+\d+){1,}(?=$|\s)/i', ' ', $name);
        $name = (string) preg_replace('/(?:^|\s)\d+(?:\s+\d+){2,}(?=$|\s)/', ' ', $name);

        // Collapse multiple spaces and trim
        $name = (string) preg_replace('/\s{2,}/', ' ', $name);
        $name = trim($name, " \t\n\r\0\x0B-_");

        // Special fix from previous implementation
        $name = str_replace(' RF ', ' ', $name);

        // Final aggressive cleanup
        for ($i = 0; $i < 2; $i++) {
            $name = (string) preg_replace('/(?:^|\s)v\d+(?:\s+\d+){1,}(?=$|\s)/i', ' ', $name);
            $name = (string) preg_replace('/(?:^|\s)\d+(?:\s+\d+){2,}(?=$|\s)/', ' ', $name);
            $name = (string) preg_replace('/\b[vV]\d+(?:[ ._-]\d+){1,}\b/', ' ', $name);
            $name = (string) preg_replace('/\b\d+(?:[ ._-]\d+){2,}\b/', ' ', $name);
            $name = (string) preg_replace('/\s{2,}/', ' ', $name);
            $name = trim($name, " \t\n\r\0\x0B-_");
        }

        return $name;
    }

    /**
     * Clean version token sequences from name.
     */
    protected function cleanVersionTokens(string $name): string
    {
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        $filtered = [];
        $i = 0;
        $tcount = count($tokens);

        while ($i < $tcount) {
            $tok = $tokens[$i];
            if (preg_match('/^v?\d+$/i', $tok)) {
                $j = $i + 1;
                $numRun = 0;
                while ($j < $tcount && preg_match('/^\d+$/', $tokens[$j])) {
                    $numRun++;
                    $j++;
                }
                $startsWithV = preg_match('/^v\d+$/i', $tok) === 1;
                if (($startsWithV && $numRun >= 1) || (! $startsWithV && $numRun >= 2)) {
                    // Skip this version run
                    $i = $j;

                    continue;
                }
                // Not a version run: keep the single number token
                $filtered[] = $tok;
                $i++;

                continue;
            }
            $filtered[] = $tok;
            $i++;
        }

        return implode(' ', $filtered);
    }

    /**
     * Fallback to legacy regex parsing.
     *
     * @return array{title: string, release: string}|false
     */
    protected function fallbackParse(string $releaseName): array|false
    {
        $cleanedName = preg_replace('/\sMulti\d?\s/i', '', $releaseName);
        if (preg_match(self::LEGACY_TITLE_REGEX, $cleanedName, $hits)) {
            $result = [];
            $result['title'] = str_replace(' RF ', ' ', preg_replace('/(?:[-:._]|%20|[\[\]])/', ' ', $hits['title']));
            $result['title'] = preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|english|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $result['title']);
            $result['title'] = preg_replace('/^(PC\sISO\)\s\()/i', '', $result['title']);
            $result['title'] = trim(preg_replace('/\s{2,}/', ' ', $result['title']));

            if (empty($result['title'])) {
                return false;
            }

            $result['release'] = $releaseName;

            return array_map('trim', $result);
        }

        return false;
    }

    /**
     * Normalize a title for comparison/matching.
     */
    public function normalizeForMatch(string $title): string
    {
        $t = mb_strtolower($title);

        // Strip scene groups at end
        $groupAlternation = implode('|', array_map('preg_quote', self::SCENE_GROUPS));
        $t = (string) preg_replace('/\s*-\s*(?:'.$groupAlternation.'|[A-Z0-9]{2,})\s*$/i', '', $t);

        // Remove edition tokens and common noise
        $t = (string) preg_replace('/\b(game of the year|goty|definitive edition|deluxe edition|ultimate edition|complete edition|remastered|hd remaster|directors? cut|anniversary edition|update|patch|hotfix|incl(?:uding)? dlcs?|dlcs?|repack|rip|iso|crack(?:fix)?|beta|alpha)\b/i', ' ', $t);

        // Remove languages/platform tokens
        $t = (string) preg_replace('/\b(pc|gog|steam|x64|x86|win64|win32|mult[iy]?\d*|eng|english|fr|french|de|german|es|spanish|it|italian|pt|ptbr|portuguese|ru|russian|pl|polish|tr|turkish|nl|dutch|se|swedish|no|norwegian|da|danish|fi|finnish|jp|japanese|cn|chs|cht|ko|korean)\b/i', ' ', $t);

        // Remove punctuation
        $t = (string) preg_replace('/[^a-z0-9]+/i', ' ', $t);
        $t = trim(preg_replace('/\s{2,}/', ' ', $t));

        return $t;
    }

    /**
     * Compute similarity between two strings.
     */
    public function computeSimilarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 100.0;
        }

        $percent = 0.0;
        similar_text($a, $b, $percent);

        // Levenshtein-based score
        $levScore = 0.0;
        $len = max(strlen($a), strlen($b));
        if ($len > 0) {
            $dist = levenshtein($a, $b);
            if ($dist >= 0) {
                $levScore = (1 - ($dist / $len)) * 100.0;
            }
        }

        return max($percent, $levScore);
    }
}
