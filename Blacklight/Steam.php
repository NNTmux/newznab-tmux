<?php

namespace Blacklight;

use App\Models\SteamApp;
use b3rs3rk\steamfront\Main;
use DivineOmega\CliProgressBar\ProgressBar;
use Illuminate\Support\Arr;

/**
 * Class Steam.
 */
class Steam
{
    private const STEAM_MATCH_PERCENTAGE = 90;

    /**
     * @var string The parsed game name from searchname
     */
    public string $searchTerm;

    /**
     * @var int The ID of the Steam Game matched
     */
    protected int $steamGameID;

    protected $lastUpdate;

    protected Main $steamFront;

    protected ColorCLI $colorCli;

    /**
     * Steam constructor.
     *
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->steamFront = new Main(
            [
                'country_code' => 'us',
                'local_lang' => 'english',
            ]
        );

        $this->colorCli = new ColorCLI;
    }

    /**
     * Gets all Information for the game.
     *
     * @return array|false
     */
    public function getAll(int $appID): bool|array
    {
        $res = $this->steamFront->getAppDetails($appID);

        if ($res !== false) {
            // Normalize description
            $description = null;
            if (isset($res->description)) {
                if (is_array($res->description)) {
                    $description = $res->description['short'] ?? ($res->description['about'] ?? null);
                } elseif (is_object($res->description)) {
                    $description = $res->description->short ?? null;
                } elseif (is_string($res->description)) {
                    $description = $res->description;
                }
            }

            // Normalize images
            $cover = null;
            $backdrop = null;
            if (isset($res->images)) {
                if (is_array($res->images)) {
                    $cover = $res->images['header'] ?? null;
                    $backdrop = $res->images['background'] ?? null;
                } elseif (is_object($res->images)) {
                    $cover = $res->images->header ?? null;
                    $backdrop = $res->images->background ?? null;
                }
            }

            // Normalize publishers
            $publisher = $res->publishers ?? null;
            if (is_array($publisher)) {
                $publisher = implode(', ', array_filter(array_map('strval', $publisher)));
            }

            // Normalize rating
            $rating = null;
            if (isset($res->metacritic)) {
                if (is_array($res->metacritic)) {
                    $rating = $res->metacritic['score'] ?? null;
                } elseif (is_object($res->metacritic)) {
                    $rating = $res->metacritic->score ?? null;
                }
            }

            // Normalize release date
            $releaseDate = null;
            if (isset($res->releasedate)) {
                if (is_array($res->releasedate)) {
                    $releaseDate = $res->releasedate['date'] ?? null;
                } elseif (is_object($res->releasedate)) {
                    $releaseDate = $res->releasedate->date ?? null;
                } elseif (is_string($res->releasedate)) {
                    $releaseDate = $res->releasedate;
                }
            }

            // Normalize genres
            $genres = '';
            if (isset($res->genres)) {
                if (is_array($res->genres)) {
                    // When array of arrays/objects with description
                    $descs = [];
                    foreach ($res->genres as $g) {
                        if (is_array($g) && isset($g['description'])) {
                            $descs[] = $g['description'];
                        } elseif (is_object($g) && isset($g->description)) {
                            $descs[] = $g->description;
                        } elseif (is_string($g)) {
                            $descs[] = $g;
                        }
                    }
                    $genres = implode(',', $descs);
                } elseif (is_string($res->genres)) {
                    $genres = $res->genres;
                }
            }

            return [
                'title' => $res->name,
                'description' => $description,
                'cover' => $cover,
                'backdrop' => $backdrop,
                'steamid' => $res->appid,
                'directurl' => Main::STEAM_STORE_ROOT.'app/'.$res->appid,
                'publisher' => $publisher,
                'rating' => $rating,
                'releasedate' => $releaseDate,
                'genres' => $genres,
            ];
        }

        $this->colorCli->notice('Steam did not return game data');

        return false;
    }

    /**
     * Searches Steam Apps table for best title match -- prefers 100% match but returns highest over 90%.
     *
     * @param  string  $searchTerm  The parsed game name from the release searchname
     * @return false|int $bestMatch The Best match from the given search term
     *
     * @throws \Exception
     */
    public function search(string $searchTerm): bool|int
    {
        $bestMatch = false;

        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            $this->colorCli->notice('Search term cannot be empty');

            return false;
        }

        // Generate query variants from the original term to improve recall.
        $variants = $this->generateQueryVariants($searchTerm);

        $bestScore = -1.0;
        $bestAppId = null;

        foreach ($variants as $variant) {
            // Primary: Scout full-text search
            try {
                $results = SteamApp::search($variant)->get();
            } catch (\Throwable $e) {
                $results = collect();
            }

            if ($results instanceof \Traversable || $results instanceof \Countable) {
                foreach ($results as $result) {
                    if (!isset($result['name'], $result['appid'])) {
                        // When Scout returns model instances
                        $name = $result->name ?? null;
                        $appid = $result->appid ?? null;
                    } else {
                        $name = $result['name'];
                        $appid = $result['appid'];
                    }

                    if ($name === null || $appid === null) {
                        continue;
                    }

                    $score = $this->scoreTitle((string) $name, $searchTerm);

                    // Short-circuit on perfect normalized match
                    if ($score >= 100.0) {
                        $bestAppId = (int) $appid;
                        $bestScore = $score;
                        break 2;
                    }

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestAppId = (int) $appid;
                    }
                }
            }

            // If Scout produced no good results, try a LIKE fallback using a compacted variant
            if ($bestScore < self::STEAM_MATCH_PERCENTAGE) {
                $likeTerm = $this->toSqlLike($variant);
                $fallbacks = SteamApp::query()
                    ->select(['appid', 'name'])
                    ->where('name', 'like', $likeTerm)
                    ->limit(25)
                    ->get();

                foreach ($fallbacks as $row) {
                    $name = $row->name;
                    $appid = $row->appid;
                    $score = $this->scoreTitle((string) $name, $searchTerm);

                    if ($score >= 100.0) {
                        $bestAppId = (int) $appid;
                        $bestScore = $score;
                        break 2;
                    }

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestAppId = (int) $appid;
                    }
                }
            }
        }

        if ($bestAppId !== null && $bestScore >= self::STEAM_MATCH_PERCENTAGE) {
            return $bestAppId;
        }

        // As a final attempt, apply a lower threshold if we have a very close match by normalized prefix.
        if ($bestAppId !== null && $bestScore >= (self::STEAM_MATCH_PERCENTAGE - 10)) {
            return $bestAppId;
        }

        $this->colorCli->notice('Steam search returned no valid results');

        return $bestMatch;
    }

    /**
     * Downloads full Steam Store dump and imports data into local table.
     *
     * @throws \Exception
     */
    public function populateSteamAppsTable(): void
    {
        $bar = new ProgressBar;
        $fullAppArray = $this->steamFront->getFullAppList();
        $inserted = $dupe = 0;
        $this->colorCli->info('Populating steam apps table');
        $appsArray = Arr::pluck($fullAppArray, 'apps');
        $max = count($appsArray[0]);
        $bar->setMaxProgress($max);
        foreach ($appsArray as $appArray) {
            foreach ($appArray as $app) {
                $dupeCheck = SteamApp::query()->where('appid', '=', $app['appid'])->first(['appid']);
                if ($dupeCheck === null) {
                    SteamApp::query()->insert(['name' => $app['name'], 'appid' => $app['appid']]);
                    $inserted++;
                } else {
                    $dupe++;
                }
                $bar->advance()->display();
            }
        }

        $bar->complete();

        \Laravel\Prompts\info('Added '.$inserted.' new steam app(s), '.$dupe.' duplicates skipped');
    }

    // --------------------
    // Matching helpers
    // --------------------

    /**
     * Build multiple query variants from a noisy scene/PC release name.
     * Order matters: earlier variants are tried first.
     */
    private function generateQueryVariants(string $term): array
    {
        $variants = [];

        $variants[] = $term;

        $stripped = $this->stripEditionTags($term);
        if ($stripped !== $term) {
            $variants[] = $stripped;
        }

        $noParen = $this->removeParentheses($stripped);
        if ($noParen !== $stripped) {
            $variants[] = $noParen;
        }

        // If title contains a colon, try the left side (base title)
        if (str_contains($noParen, ':')) {
            $left = trim(explode(':', $noParen, 2)[0]);
            if ($left !== '' && $left !== $noParen) {
                $variants[] = $left;
            }
        }

        $clean = $this->normalizeTitle($noParen, false);
        if ($clean !== $noParen) {
            $variants[] = $clean;
        }

        // A tighter LIKE-friendly variant (no spaces to percent expansion)
        $compact = preg_replace('/\s+/', ' ', $clean);
        if ($compact !== $clean) {
            $variants[] = (string) $compact;
        }

        // De-duplicate while preserving order
        $unique = [];
        foreach ($variants as $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            if (!in_array(mb_strtolower($v), array_map('mb_strtolower', $unique), true)) {
                $unique[] = $v;
            }
        }

        return $unique;
    }

    /**
     * Compute a 0..100 similarity score between a Steam title and the original noisy search term.
     * Combines token Jaccard, normalized Levenshtein, prefix boost, and exact normalized match.
     */
    private function scoreTitle(string $candidate, string $original): float
    {
        $normCand = $this->normalizeTitle($candidate);
        $normOrig = $this->normalizeTitle($this->stripEditionTags($this->removeParentheses($original)));

        if ($normCand === '' || $normOrig === '') {
            return 0.0;
        }

        if ($normCand === $normOrig) {
            return 100.0; // perfect normalized match
        }

        // Token Jaccard similarity
        $tCand = $this->tokenize($normCand);
        $tOrig = $this->tokenize($normOrig);

        // If one token set fully contains the other, treat as extremely strong match
        $diffCandInOrig = array_diff($tCand, $tOrig);
        $diffOrigInCand = array_diff($tOrig, $tCand);
        $shortCount = min(count($tCand), count($tOrig));
        $longCount = max(count($tCand), count($tOrig));
        $coverage = $longCount > 0 ? ($shortCount / $longCount) : 0.0;
        if (empty($diffCandInOrig) || empty($diffOrigInCand)) {
            // All tokens of one are contained in the other (extras are likely scene tags)
            if ($coverage >= 0.8) {
                return 100.0;
            }
        }

        $intersect = count(array_intersect($tCand, $tOrig));
        $union = count(array_unique(array_merge($tCand, $tOrig)));
        $jaccard = $union > 0 ? ($intersect / $union) : 0.0;

        // Normalized Levenshtein similarity
        $lev = levenshtein($normCand, $normOrig);
        $maxLen = max(strlen($normCand), strlen($normOrig));
        $levSim = $maxLen > 0 ? (1.0 - ($lev / $maxLen)) : 0.0;

        // Prefix boost if one starts with the other
        $prefixBoost = 0.0;
        if (str_starts_with($normCand, $normOrig) || str_starts_with($normOrig, $normCand)) {
            $prefixBoost = 0.15; // 15% boost
        }

        // Weigh components: Jaccard (60%), Levenshtein (40%), then add prefix boost
        $score = ($jaccard * 0.6 + $levSim * 0.4 + $prefixBoost) * 100.0;

        // Containment soft-boost if not fully covered above
        if ($coverage >= 0.6) {
            $score = max($score, 90.0 + ($coverage - 0.6) * 25.0); // up to ~96
        }

        // Cap between 0..100
        return max(0.0, min(100.0, $score));
    }

    /**
     * Normalize a title: lower, remove punctuation/extra whitespace, drop common scene/release noise and stop-words.
     */
    private function normalizeTitle(string $s, bool $dropStopWords = true): string
    {
        $s = mb_strtolower($s);

        // Remove brackets content and common separators
        $s = $this->removeParentheses($s);

        // Replace separators with spaces
        $s = preg_replace('/[._\-+]+/u', ' ', $s);

        // Normalize roman numerals to numbers (iv -> 4, vii -> 7, etc.)
        $s = $this->replaceRomanNumerals($s);

        // Remove version/build numbers that add noise
        $s = preg_replace('/(?:v|build)\s*\d+(?:\.\d+)*/iu', ' ', $s);

        // Remove common release tags/groups/platform markers
        $noise = [
            'repack', 'rip', 'iso', 'multi', 'proper', 'gog', 'steamrip', 'update', 'dlc', 'incl', 'fitgirl', 'elamigos',
            'razor1911', 'codex', 'plaza', 'fl t', 'flt', 'reloaded', 'empress', 'skidrow', 'goldberg', 'dodi', 'kaos',
            'onlinefix', 'gog-galaxy', 'pc', 'x86', 'x64', 'x32', 'x86-64', 'win', 'windows', 'launcher', 'preinstall',
            'ultimate', 'definitive', 'complete', 'remastered', 'game of the year', 'goty', 'deluxe', 'edition', 'bundle',
            'soundtrack', 'ost', 'demo', 'alpha', 'beta', 'playtest', 'prologue', 'teaser', 'public test', 'test server'
        ];
        $noisePattern = '/\b(' . implode('|', array_map(static fn($w) => preg_quote($w, '/'), $noise)) . ')\b/u';
        $s = preg_replace($noisePattern, ' ', $s);

        // Remove any leftover non-alnum (keep spaces), then collapse whitespace
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $s = preg_replace('/\s{2,}/u', ' ', $s);
        $s = trim($s);

        if ($dropStopWords) {
            $tokens = $this->tokenize($s);
            $stop = $this->stopWords();
            $tokens = array_values(array_filter($tokens, static fn($t) => $t !== '' && !in_array($t, $stop, true)));
            $s = implode(' ', $tokens);
        }

        // Normalize a leading article removal e.g., "the witcher" -> "witcher"
        $s = preg_replace('/^(the|a|an)\s+/u', '', $s);

        return $s;
    }

    private function removeParentheses(string $s): string
    {
        // Remove content in (), [], {}, including the brackets
        return trim(preg_replace('/\s*[\(\[\{][^\)\]\}]*[\)\]\}]\s*/u', ' ', $s) ?? '');
    }

    private function stripEditionTags(string $s): string
    {
        // Remove common edition/suffix tags separated by dashes or brackets
        $s = preg_replace('/\s*-\s*(repack|rip|iso|multi|proper|gog|steamrip|update|dlc|incl|fitgirl|elamigos|razor1911|codex|plaza|flt|reloaded|empress|skidrow|goldberg|dodi|kaos|onlinefix)\b.*/iu', '', $s);
        // Remove "Edition" style suffixes
        $s = preg_replace('/\b(ultimate|definitive|complete|remastered|game\s*of\s*the\s*year|goty|deluxe)\s+edition\b.*/iu', '', $s);
        return trim($s ?? '');
    }

    private function tokenize(string $s): array
    {
        $s = mb_strtolower($s);
        $parts = preg_split('/\s+/u', $s) ?: [];
        // Deduplicate while preserving order
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (!isset($seen[$p])) {
                $seen[$p] = true;
                $out[] = $p;
            }
        }
        return $out;
    }

    private function stopWords(): array
    {
        return [
            'the', 'a', 'an', 'and', 'or', 'of', 'for', 'to', 'in', 'on', 'with', 'at', 'by', 'from'
        ];
    }

    /**
     * Replace common roman numerals (I..XX) with their arabic digit counterparts as whole words.
     */
    private function replaceRomanNumerals(string $s): string
    {
        $map = [
            'xviii' => '18', 'xvii' => '17', 'xvi' => '16', 'xv' => '15', 'xiv' => '14', 'xiii' => '13', 'xii' => '12', 'xi' => '11',
            'xx' => '20', 'xix' => '19', 'x' => '10', 'ix' => '9', 'viii' => '8', 'vii' => '7', 'vi' => '6', 'v' => '5', 'iv' => '4', 'iii' => '3', 'ii' => '2', 'i' => '1'
        ];
        foreach ($map as $roman => $arabic) {
            $s = preg_replace('/\b' . $roman . '\b/u', $arabic, $s);
        }
        return $s;
    }

    private function toSqlLike(string $term): string
    {
        // Convert spaces to wildcards to increase match coverage in LIKE
        $term = $this->normalizeTitle($term, false);
        $term = preg_replace('/\s+/', '%', $term);
        $term = trim($term ?? '');
        if ($term === '') {
            return '%';
        }
        return '%'.$term.'%';
    }
}
