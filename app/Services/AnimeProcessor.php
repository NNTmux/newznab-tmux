<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use App\Services\PopulateAniListService as PaList;

class AnimeProcessor
{
    private const PROC_EXTFAIL = -1; // Release Anime title/episode # could not be extracted from searchname

    private const PROC_NOMATCH = -2; // AniList ID was not found in anidb table using extracted title

    /** @var bool Whether to echo messages to CLI */
    public bool $echooutput;

    public PaList $palist;

    /** @var int number of AniDB releases to process */
    private int $aniqty;

    /** @var int|null The status of the release being processed */
    private ?int $status;

    /**
     * Simple cache of looked up titles -> anidbid to reduce repeat queries within one run.
     *
     * @var array<string,int>
     */
    private array $titleCache = [];

    /**
     * Simple cache of looked up titles -> anilist_id to reduce repeat queries within one run.
     *
     * @var array<string,int>
     *
     * @phpstan-ignore property.onlyWritten
     */
    private array $anilistIdCache = [];

    /**
     * @throws \Exception
     */
    public function __construct(bool $echooutput = true)
    {
        $this->echooutput = $echooutput && (bool) config('nntmux.echocli');
        $this->palist = new PaList;

        $quantity = (int) Settings::settingValue('maxanidbprocessed');
        $this->aniqty = $quantity > 0 ? $quantity : 100;
        $this->status = null;
    }

    /**
     * Main entry point for processing anime releases.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     *
     * @throws \Exception
     */
    public function process(string $groupID = '', string $guidChar = ''): void
    {
        if ((int) Settings::settingValue('lookupanidb') === 0) {
            return;
        }

        $this->processAnimeReleases($groupID, $guidChar);
    }

    /**
     * Queues anime releases for processing.
     *
     * @param  string  $groupID  (Optional) ID of a group to work on.
     * @param  string  $guidChar  (Optional) First letter of a release GUID to use to get work.
     *
     * @throws \Exception
     */
    public function processAnimeReleases(string $groupID = '', string $guidChar = ''): void
    {
        $query = Release::query()
            ->whereNull('anidbid')
            ->where('categories_id', Category::TV_ANIME);

        if ($guidChar !== '') {
            $query->where('leftguid', 'like', $guidChar.'%');
        }

        if ($groupID !== '') {
            $query->where('groups_id', $groupID);
        }

        $results = $query->orderByDesc('postdate')
            ->limit($this->aniqty)
            ->get();

        if ($results->count() > 0) {
            // AniList rate limiting is handled internally in PopulateAniList

            foreach ($results as $release) {
                $matched = $this->matchAnimeRelease($release);
                if ($matched === false) {
                    // Persist status so we do not keep retrying hopeless releases immediately.
                    Release::query()->where('id', $release->id)->update(['anidbid' => $this->status]);
                }
            }
        } else {
            cli()->info('No anidb releases to process.');
        }
    }

    /**
     * Extracts anime title from release searchname.
     * Returns ['title' => string] on success else empty array.
     * Note: AniList doesn't support episode lookups, so we only extract the title.
     *
     * @return array<string, mixed>
     */
    private function extractTitleEpisode(string $cleanName = ''): array
    {
        // Fix UTF-8 encoding issues (double-encoding, corrupted sequences)
        $s = $this->fixEncoding($cleanName);

        // Normalize common separators
        $s = str_replace(['_', '.'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', (string) $s);
        $s = trim((string) $s);

        // Strip leading group tags like [Group]
        $s = preg_replace('/^(?:\[[^\]]+\]\s*)+/', '', $s);
        $s = trim((string) $s);

        // Remove language codes and tags (before extracting title)
        // Common language tags: [ENG], [JAP], [SUB], [DUB], [MULTI], etc.
        $s = preg_replace('/\[(?:ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\]/i', ' ', $s);
        $s = preg_replace('/\((?:ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\)/i', ' ', $s);

        // Remove episode patterns and extract title
        $title = '';

        // Try to extract title by removing episode patterns
        // 1) Look for " S01E01" or " S1E1" pattern
        if (preg_match('/\sS\d+E\d+/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 2) Look for " 1x18" or " 2x05" pattern (season x episode)
        elseif (preg_match('/\s\d+x\d+/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 3) Look for " - NNN" and extract title before it
        elseif (preg_match('/\s-\s*(\d{1,3})\b/', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 4) If not found, look for " E0*NNN" or " Ep NNN"
        elseif (preg_match('/\sE(?:p(?:isode)?)?\s*0*(\d{1,3})\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 4) Keywords Movie/OVA/Complete Series
        elseif (preg_match('/\b(Movie|OVA|Complete Series|Complete|Full Series)\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 5) BD/resolution releases: pick title before next bracket token
        elseif (preg_match('/\[(?:BD|BDRip|BluRay|Blu-Ray|\d{3,4}[ipx]|HEVC|x264|x265|H264|H265)\]/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        } else {
            // No episode pattern found, use the whole string as title
            $title = $s;
        }

        $title = $this->cleanTitle((string) $title);

        if ($title === '') {
            $this->status = self::PROC_EXTFAIL;

            return [];
        }

        return ['title' => $title];
    }

    /**
     * Fix UTF-8 encoding issues in strings (double-encoding, corrupted sequences).
     */
    private function fixEncoding(string $text): string
    {
        // Remove common corrupted character sequences (encoding artifacts)
        // Pattern: Ã¢Â_Â, Ã¢Â Â, Ã¢Â, etc.
        $text = preg_replace('/Ã¢Â[_\sÂ]*/u', '', $text);
        $text = preg_replace('/Ã[¢Â©€£]/u', '', $text);

        // Remove standalone Â characters (common encoding artifact)
        $text = preg_replace('/Â+/u', '', $text);

        // Remove any remaining Ã sequences (encoding artifacts)
        $text = preg_replace('/Ã[^\s]*/u', '', $text);

        // Try to detect and fix double-encoding issues
        // Common patterns: Ã©, Ã, etc. (UTF-8 interpreted as ISO-8859-1)
        if (preg_match('/Ã[^\s]/u', $text)) {
            // Try ISO-8859-1 -> UTF-8 conversion (common double-encoding fix)
            $converted = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
            if ($converted !== false && ! preg_match('/Ã[^\s]/u', $converted)) {
                $text = $converted;
            }
        }

        // Remove any remaining non-printable or control characters except spaces
        $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $text);

        // Normalize Unicode (NFD -> NFC) if available
        if (function_exists('normalizer_normalize')) {
            $text = normalizer_normalize($text, \Normalizer::FORM_C);
        }

        // Final cleanup: remove any remaining isolated non-ASCII control-like characters
        // This catches any remaining encoding artifacts
        $text = preg_replace('/[\xC0-\xC1\xC2-\xC5]/u', '', $text);

        return $text;
    }

    /**
     * Strip stray separators, language codes, episode numbers, and other release tags from title.
     */
    private function cleanTitle(string $title): string
    {
        // Fix encoding issues first
        $title = $this->fixEncoding($title);

        // Remove all bracketed tags (language, quality, etc.)
        $title = preg_replace('/\[[^\]]+\]/', ' ', $title);

        // Remove all parenthesized tags
        $title = preg_replace('/\([^)]+\)/', ' ', $title);

        // Remove language codes (standalone or with separators)
        $title = preg_replace('/\b(ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\b/i', ' ', $title);

        // Remove metadata words (JAV, Uncensored, Censored, etc.)
        $title = preg_replace('/\b(JAV|Uncensored|Censored|Mosaic|Mosaic-less|HD|SD|FHD|UHD)\b/i', ' ', $title);

        // Remove date patterns (6-digit dates like 091919, 200101, etc.)
        $title = preg_replace('/\b\d{6}\b/', ' ', $title);

        // Remove trailing numbers/underscores (like _01, 01, _001, etc.)
        $title = preg_replace('/[-_]\s*\d{1,4}\s*$/i', '', $title);
        $title = preg_replace('/\s+\d{1,4}\s*$/i', '', $title);

        // Remove episode patterns (including episode titles that follow)
        // Remove " - 1x18 - Episode Title" or " - 1x18" patterns
        $title = preg_replace('/\s*-\s*\d+x\d+.*$/i', '', $title);
        // Remove " S01E01" or " S1E1" pattern
        $title = preg_replace('/\s+S\d+E\d+.*$/i', '', $title);
        // Remove " - NNN" or " - NNN - Episode Title" patterns
        $title = preg_replace('/\s*-\s*\d{1,4}(?:\s*-\s*.*)?\s*$/i', '', $title);
        $title = preg_replace('/\s*-\s*$/i', '', $title);
        // Remove " E0*NNN" or " Ep NNN" patterns
        $title = preg_replace('/\s+E(?:p(?:isode)?)?\s*0*\d{1,4}\s*$/i', '', $title);

        // Remove quality/resolution tags
        $title = preg_replace('/\b(480p|720p|1080p|2160p|4K|BD|BDRip|BluRay|Blu-Ray|HEVC|x264|x265|H264|H265|WEB|WEBRip|DVDRip|TVRip)\b/i', ' ', $title);

        // Remove common release tags
        $title = preg_replace('/\b(PROPER|REPACK|RIP|ISO|CRACK|BETA|ALPHA|FINAL|COMPLETE|FULL)\b/i', ' ', $title);

        // Remove volume/chapter markers
        $title = preg_replace('/\s+Vol\.?\s*\d*\s*$/i', '', $title);
        $title = preg_replace('/\s+Ch\.?\s*\d*\s*$/i', '', $title);

        // Remove trailing dashes and separators
        $title = preg_replace('/\s*[-_]\s*$/', '', $title);

        // Normalize whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        return trim((string) $title);
    }

    /**
     * Retrieve AniList anime by searching for title.
     * First checks local database, then searches AniList API if not found.
     */
    private function getAnidbByName(string $searchName = ''): ?AnidbTitle
    {
        if ($searchName === '') {
            return null;
        }

        $key = strtolower($searchName);

        // Check cache first
        if (isset($this->titleCache[$key])) {
            return AnidbTitle::query()->select(['anidbid', 'title'])->where('anidbid', $this->titleCache[$key])->first();
        }

        // Try exact match in local database first
        $exact = AnidbTitle::query()->whereRaw('LOWER(title) = ?', [$key])->select(['anidbid', 'title'])->first();
        if ($exact) {
            $this->titleCache[$key] = (int) $exact->anidbid;

            return $exact;
        }

        // Try partial match in local database
        $partial = AnidbTitle::query()->where('title', 'like', '%'.$searchName.'%')->select(['anidbid', 'title'])->first();
        if ($partial) {
            $this->titleCache[$key] = (int) $partial->anidbid;

            return $partial;
        }

        // Not found locally, search AniList API
        try {
            $searchResults = $this->palist->searchAnime($searchName, 1);
            if ($searchResults) {
                $anilistData = $searchResults[0]; // @phpstan-ignore offsetAccess.notFound
                $anilistId = $anilistData['id'] ?? null;

                if ($anilistId) {
                    // Use anilist_id as anidbid for new entries
                    $anidbid = AnidbInfo::query()->where('anilist_id', $anilistId)->value('anidbid');

                    if (! $anidbid) {
                        // Create new entry using anilist_id as anidbid
                        $anidbid = (int) $anilistId;
                        $this->palist->populateTable('info', $anilistId);
                    }

                    // Get the title from database after insertion
                    $title = AnidbTitle::query()
                        ->where('anidbid', $anidbid)
                        ->where('lang', 'en')
                        ->value('title');

                    if ($title) {
                        $this->titleCache[$key] = $anidbid;

                        return AnidbTitle::query()
                            ->where('anidbid', $anidbid)
                            ->where('title', $title)
                            ->first();
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->echooutput) {
                cli()->error('AniList search failed: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Matches the anime release to AniList Info; fetches remotely if needed.
     * Note: AniList doesn't support episode lookups, so we only match by title.
     *
     * @throws \Exception
     */
    private function matchAnimeRelease(mixed $release): bool
    {
        $matched = false;
        $type = 'Local';

        $cleanArr = $this->extractTitleEpisode((string) $release->searchname);
        if (empty($cleanArr)) {
            return false;
        }

        $title = $cleanArr['title'];

        if ($this->echooutput) {
            cli()->info('Looking Up: Title: '.$title);
        }

        $anidbTitle = $this->getAnidbByName($title);
        if (! $anidbTitle) {
            // Try with spaces replaced by % for broader matching
            $tmpName = preg_replace('/\s+/', '%', $title);
            $anidbTitle = $this->getAnidbByName($tmpName);
        }

        if ($anidbTitle && is_numeric($anidbTitle->anidbid) && (int) $anidbTitle->anidbid > 0) {
            $anidbId = (int) $anidbTitle->anidbid;

            // Check if we need to update info from AniList
            $info = AnidbInfo::query()->where('anidbid', $anidbId)->first();
            if (! $info || $this->shouldUpdateInfo($anidbId)) {
                // Try to get anilist_id if we have it
                $anilistId = $info->anilist_id ?? null;

                if (! $anilistId) {
                    // Search AniList for this title
                    try {
                        $searchResults = $this->palist->searchAnime($title, 1);
                        if ($searchResults) {
                            $anilistId = $searchResults[0]['id'] ?? null;
                        }
                    } catch (\Exception $e) {
                        if ($this->echooutput) {
                            cli()->warning('AniList search failed: '.$e->getMessage());
                        }
                    }
                }

                if ($anilistId) {
                    // Fetch remote info from AniList
                    $this->palist->populateTable('info', $anilistId);
                    $type = 'Remote';
                }
            }

            $this->updateRelease($anidbId, (int) $release->id);

            if ($this->echooutput) {
                cli()->headerOver('Matched '.$type.' AniList ID: ');
                cli()->primary((string) $anidbId);
                cli()->alternateOver('   Title: ');
                cli()->primary($anidbTitle->title);
            }
            $matched = true;
        } else {
            $this->status = self::PROC_NOMATCH;
        }

        return $matched;
    }

    private function updateRelease(int $anidbId, int $relId): void
    {
        Release::query()->where('id', $relId)->update(['anidbid' => $anidbId]);
    }

    /**
     * Determine if we should attempt a remote AniDB info fetch (missing or stale > 1 week).
     */
    private function shouldUpdateInfo(int $anidbId): bool
    {
        $info = AnidbInfo::query()->where('anidbid', $anidbId)->first(['updated']);
        if ($info === null) {
            return true; // no info yet
        }

        return $info->updated < now()->subWeek();
    }
}
