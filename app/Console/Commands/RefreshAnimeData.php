<?php

namespace App\Console\Commands;

use App\Models\AnidbInfo;
use App\Models\Category;
use App\Models\Release;
use App\Services\PopulateAniListService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAnimeData extends Command
{
    /**
     * Conservative rate limit: 20 requests per minute (to stay well below AniList's 90/min limit).
     * This allows for multiple API calls per release (search + getById).
     */
    private const RATE_LIMIT_PER_MINUTE = 20;

    /**
     * Track API request timestamps for rate limiting.
     *
     * @var array<int>
     */
    private array $requestTimestamps = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anime:refresh
                            {--limit=0 : Maximum number of releases to process (0 = all)}
                            {--chunk=100 : Process releases in chunks of this size}
                            {--missing-only : Only refresh releases missing AniList data (no anilist_id)}
                            {--retry-failed : Only refresh releases with anidbid <= 0 (failed processing: -1, -2, etc.)}
                            {--force : Force refresh even if data exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and refresh AniList data for existing anime releases in TV->Anime category by matching release searchname';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk');
        $missingOnly = $this->option('missing-only');
        $retryFailed = $this->option('retry-failed');
        $force = $this->option('force');

        $this->info('Starting AniList data refresh for anime releases...');
        if ($retryFailed) {
            $this->info('Mode: Retrying failed releases (anidbid <= 0)...');
        } elseif ($missingOnly) {
            $this->info('Mode: Missing AniList data only...');
        } else {
            $this->info('Mode: All releases...');
        }
        $this->info('Matching releases by searchname to AniList API...');
        $this->newLine();

        // Build query for releases in TV_ANIME category
        $query = Release::query()
            ->select(['releases.id', 'releases.anidbid', 'releases.searchname'])
            ->where('categories_id', Category::TV_ANIME);

        // If retry-failed, only get releases with anidbid <= 0 (failed processing)
        if ($retryFailed) {
            $query->where('releases.anidbid', '<=', 0);
        }

        // If missing-only, only get releases without anilist_id
        if ($missingOnly) {
            $query->leftJoin('anidb_info as ai', 'ai.anidbid', '=', 'releases.anidbid')
                ->whereNull('ai.anilist_id');
        }

        // Get releases (not distinct anidbids, since we're matching by searchname)
        $releases = $query->orderBy('releases.id')
            ->get();

        $totalCount = $releases->count();

        if ($totalCount === 0) {
            $this->warn('No anime releases found to process.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} anime releases to process.");

        if ($limit > 0) {
            $releases = $releases->take($limit);
            $totalCount = $releases->count();
            $this->info("Processing {$totalCount} releases (limited).");
        }

        $this->newLine();

        $populateAniList = new PopulateAniListService;
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $notFound = 0;
        $failedSearchnames = []; // Track failed searchnames for summary

        // Process in chunks
        $chunks = $releases->chunk($chunkSize);
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        foreach ($chunks as $chunk) {
            foreach ($chunk as $release) {
                $searchname = $release->searchname ?? '';
                $progressBar->setMessage('Processing: '.substr($searchname, 0, 50).'...');

                try {
                    // Extract clean title from searchname
                    $titleData = $this->extractTitleFromSearchname($searchname);

                    if (empty($titleData) || empty($titleData['title'])) {
                        $notFound++;
                        $failedSearchnames[] = [
                            'searchname' => $searchname,
                            'reason' => 'Failed to extract title',
                            'cleaned_title' => null,
                        ];
                        if ($this->getOutput()->isVerbose()) {
                            $this->newLine();
                            $this->warn("Failed to extract title from searchname: {$searchname}");
                        }
                        $processed++;
                        $progressBar->advance();

                        continue;
                    }

                    $cleanTitle = $titleData['title'];

                    // Check if we should skip (if not forcing and data exists)
                    // Don't skip if we're retrying failed releases (anidbid <= 0)
                    if (! $force && ! $missingOnly && ! $retryFailed) {
                        // Check if release already has complete AniList data
                        if ($release->anidbid > 0) {
                            $anidbInfo = DB::table('anidb_info')
                                ->where('anidbid', $release->anidbid)
                                ->whereNotNull('anilist_id')
                                ->whereNotNull('country')
                                ->whereNotNull('media_type')
                                ->first();

                            if ($anidbInfo) {
                                $skipped++;
                                $processed++;
                                $progressBar->advance();

                                continue;
                            }
                        }
                    }

                    // Search AniList for this title (with rate limiting)
                    $this->enforceRateLimit();
                    $searchResults = $populateAniList->searchAnime($cleanTitle, 1);

                    if (! $searchResults || empty($searchResults)) {
                        // Try with spaces replaced for broader matching
                        $altTitle = preg_replace('/\s+/', ' ', $cleanTitle);
                        if ($altTitle !== $cleanTitle) {
                            $this->enforceRateLimit();
                            $searchResults = $populateAniList->searchAnime($altTitle, 1);
                        }
                    }

                    if (! $searchResults || empty($searchResults)) {
                        $notFound++;
                        $failedSearchnames[] = [
                            'searchname' => $searchname,
                            'reason' => 'No AniList match found',
                            'cleaned_title' => $cleanTitle,
                        ];
                        if ($this->getOutput()->isVerbose()) {
                            $this->newLine();
                            $this->warn('No AniList match found for:');
                            $this->line("  Searchname: {$searchname}");
                            $this->line("  Cleaned title: {$cleanTitle}");
                        }
                        $processed++;
                        $progressBar->advance();

                        continue;
                    }

                    $anilistData = $searchResults[0];
                    $anilistId = $anilistData['id'] ?? null;

                    if (! $anilistId) {
                        $notFound++;
                        $failedSearchnames[] = [
                            'searchname' => $searchname,
                            'reason' => 'AniList result missing ID',
                            'cleaned_title' => $cleanTitle,
                        ];
                        if ($this->getOutput()->isVerbose()) {
                            $this->newLine();
                            $this->warn('AniList search returned result but no ID for:');
                            $this->line("  Searchname: {$searchname}");
                            $this->line("  Cleaned title: {$cleanTitle}");
                        }
                        $processed++;
                        $progressBar->advance();

                        continue;
                    }

                    // Fetch full data from AniList and insert/update (with rate limiting)
                    // This will create/update anidb_info entry using anilist_id as anidbid if needed
                    $this->enforceRateLimit();
                    $populateAniList->populateTable('info', $anilistId);

                    // Get the anidbid that was created/updated (it uses anilist_id as anidbid)
                    $anidbid = AnidbInfo::query()
                        ->where('anilist_id', $anilistId)
                        ->value('anidbid');

                    if (! $anidbid) {
                        // Fallback: use anilist_id as anidbid
                        $anidbid = (int) $anilistId;
                    }

                    // Update release with the anidbid
                    Release::query()
                        ->where('id', $release->id)
                        ->update(['anidbid' => $anidbid]);

                    $successful++;
                } catch (\Exception $e) {
                    // Check if this is a 429 rate limit error
                    if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'rate limit exceeded')) {
                        $this->newLine();
                        $this->error('AniList API rate limit exceeded (429). Stopping processing for 15 minutes.');
                        $this->warn('Please wait 15 minutes before running this command again.');
                        $progressBar->finish();
                        $this->newLine();

                        // Show summary of what was processed before the error
                        $this->info('Summary (before rate limit error):');
                        $this->table(
                            ['Status', 'Count'],
                            [
                                ['Total Processed', $processed],
                                ['Successful', $successful],
                                ['Failed', $failed],
                                ['Not Found', $notFound],
                                ['Skipped', $skipped],
                            ]
                        );

                        // Show failed searchnames if any
                        if (! empty($failedSearchnames)) {
                            $this->newLine();
                            $this->warn('Failed searchnames (before rate limit error):');
                            $this->line('Showing up to 10 examples:');
                            $examples = array_slice($failedSearchnames, 0, 10);
                            foreach ($examples as $item) {
                                $cleanedTitle = $item['cleaned_title'] ?? '(extraction failed)';
                                $this->line("  - {$item['searchname']} -> {$cleanedTitle} ({$item['reason']})");
                            }
                            if (count($failedSearchnames) > 10) {
                                $this->line('  ... and '.(count($failedSearchnames) - 10).' more.');
                            }
                        }

                        return self::FAILURE;
                    }

                    $failed++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->error("Error processing release ID {$release->id}: ".$e->getMessage());
                    }
                }

                $processed++;
                $progressBar->advance();
            }
        }

        $progressBar->setMessage('Complete!');
        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Processed', $processed],
                ['Successful', $successful],
                ['Failed', $failed],
                ['Not Found', $notFound],
                ['Skipped', $skipped],
            ]
        );

        // Show failed searchnames if any
        if (! empty($failedSearchnames) && $notFound > 0) {
            $this->newLine();
            $this->warn("Failed to fetch data for {$notFound} release(s):");
            $this->newLine();

            // Show up to 20 examples
            $examples = array_slice($failedSearchnames, 0, 20);
            $rows = [];
            foreach ($examples as $item) {
                $cleanedTitle = $item['cleaned_title'] ?? '(extraction failed)';
                $rows[] = [
                    substr($item['searchname'], 0, 60).(strlen($item['searchname']) > 60 ? '...' : ''),
                    substr($cleanedTitle, 0, 40).(strlen($cleanedTitle) > 40 ? '...' : ''),
                    $item['reason'],
                ];
            }

            $this->table(
                ['Searchname', 'Cleaned Title', 'Reason'],
                $rows
            );

            if (count($failedSearchnames) > 20) {
                $this->line('... and '.(count($failedSearchnames) - 20).' more. Use --verbose to see all.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Extract clean anime title from release searchname.
     * Similar to extractTitleEpisode in AniDB.php but simplified.
     *
     * @return array{title: string}|array{}
     */
    private function extractTitleFromSearchname(string $searchname): array
    {
        if (empty($searchname)) {
            return [];
        }

        // Fix UTF-8 encoding issues (double-encoding, corrupted sequences)
        $s = $this->fixEncoding($searchname);

        // Normalize common separators
        $s = str_replace(['_', '.'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = trim($s);

        // Strip leading group tags like [Group]
        $s = preg_replace('/^(?:\[[^\]]+\]\s*)+/', '', $s);
        $s = trim($s);

        // Remove language codes and tags
        $s = preg_replace('/\[(?:ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\]/i', ' ', $s);
        $s = preg_replace('/\((?:ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\)/i', ' ', $s);

        // Extract title by removing episode patterns
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

        $title = $this->cleanTitle($title);

        if ($title === '') {
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

        return trim($title);
    }

    /**
     * Enforce rate limiting: 35 requests per minute (conservative limit).
     * Adds delays between API calls to prevent hitting AniList's 90/min limit.
     */
    private function enforceRateLimit(): void
    {
        $now = time();

        // Clean old timestamps (older than 1 minute)
        $this->requestTimestamps = array_filter($this->requestTimestamps, function ($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });

        $requestCount = count($this->requestTimestamps);

        // If we're at or over the limit, wait
        if ($requestCount >= self::RATE_LIMIT_PER_MINUTE) {
            // Calculate wait time based on oldest request
            if (! empty($this->requestTimestamps)) {
                $oldestRequest = min($this->requestTimestamps);
                $waitTime = 60 - ($now - $oldestRequest) + 1; // +1 for safety margin

                if ($waitTime > 0 && $waitTime <= 60) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->warn("Rate limit reached ({$requestCount}/".self::RATE_LIMIT_PER_MINUTE."). Waiting {$waitTime} seconds...");
                    }
                    sleep($waitTime);

                    // Clean timestamps again after waiting
                    $now = time();
                    $this->requestTimestamps = array_filter($this->requestTimestamps, function ($timestamp) use ($now) {
                        return ($now - $timestamp) < 60;
                    });
                }
            }
        }

        // Calculate minimum delay between requests (to maintain 20/min rate)
        // 60 seconds / 20 requests = 3 seconds per request
        $minDelay = 60.0 / self::RATE_LIMIT_PER_MINUTE;

        // If we have recent requests, ensure we wait at least the minimum delay
        if (! empty($this->requestTimestamps)) {
            $lastRequest = max($this->requestTimestamps);
            $timeSinceLastRequest = $now - $lastRequest;

            if ($timeSinceLastRequest < $minDelay) {
                $waitTime = $minDelay - $timeSinceLastRequest;
                if ($waitTime > 0 && $waitTime < 2) { // Only wait if less than 2 seconds
                    usleep((int) ($waitTime * 1000000)); // Convert to microseconds
                    $now = time(); // Update now after waiting
                }
            }
        }

        // Record this request timestamp (after all delays)
        $this->requestTimestamps[] = $now;
    }
}
