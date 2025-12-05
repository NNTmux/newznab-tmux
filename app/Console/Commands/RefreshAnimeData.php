<?php

namespace App\Console\Commands;

use App\Models\AnidbInfo;
use App\Models\Category;
use App\Models\Release;
use Blacklight\PopulateAniList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAnimeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anime:refresh
                            {--limit=0 : Maximum number of releases to process (0 = all)}
                            {--chunk=100 : Process releases in chunks of this size}
                            {--missing-only : Only refresh releases missing AniList data (no anilist_id)}
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
        $force = $this->option('force');

        $this->info('Starting AniList data refresh for anime releases...');
        $this->info('Matching releases by searchname to AniList API...');
        $this->newLine();

        // Build query for releases in TV_ANIME category
        $query = Release::query()
            ->select(['releases.id', 'releases.anidbid', 'releases.searchname'])
            ->where('categories_id', Category::TV_ANIME);

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

        $populateAniList = new PopulateAniList;
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $notFound = 0;

        // Process in chunks
        $chunks = $releases->chunk($chunkSize);
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% -- %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        foreach ($chunks as $chunk) {
            foreach ($chunk as $release) {
                $searchname = $release->searchname ?? '';
                $progressBar->setMessage("Processing: " . substr($searchname, 0, 50) . "...");

                try {
                    // Extract clean title from searchname
                    $titleData = $this->extractTitleFromSearchname($searchname);
                    
                    if (empty($titleData) || empty($titleData['title'])) {
                        $notFound++;
                        $processed++;
                        $progressBar->advance();
                        continue;
                    }

                    $cleanTitle = $titleData['title'];

                    // Check if we should skip (if not forcing and data exists)
                    if (! $force && ! $missingOnly) {
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

                    // Search AniList for this title
                    $searchResults = $populateAniList->searchAnime($cleanTitle, 1);
                    
                    if (! $searchResults || empty($searchResults)) {
                        // Try with spaces replaced for broader matching
                        $altTitle = preg_replace('/\s+/', ' ', $cleanTitle);
                        if ($altTitle !== $cleanTitle) {
                            $searchResults = $populateAniList->searchAnime($altTitle, 1);
                        }
                    }

                    if (! $searchResults || empty($searchResults)) {
                        $notFound++;
                        $processed++;
                        $progressBar->advance();
                        continue;
                    }

                    $anilistData = $searchResults[0];
                    $anilistId = $anilistData['id'] ?? null;

                    if (! $anilistId) {
                        $notFound++;
                        $processed++;
                        $progressBar->advance();
                        continue;
                    }

                    // Fetch full data from AniList and insert/update
                    // This will create/update anidb_info entry using anilist_id as anidbid if needed
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
                    $failed++;
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->error("Error processing release ID {$release->id}: " . $e->getMessage());
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

        // Normalize common separators
        $s = str_replace(['_', '.'], ' ', $searchname);
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
        // 1) Look for " - NNN" and extract title before it
        if (preg_match('/\s-\s*(\d{1,3})\b/', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 2) If not found, look for " E0*NNN" or " Ep NNN"
        elseif (preg_match('/\sE(?:p(?:isode)?)?\s*0*(\d{1,3})\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, (int) $m[0][1]);
        }
        // 3) Look for " S01E01" or " S1E1" pattern
        elseif (preg_match('/\sS\d+E\d+/i', $s, $m, PREG_OFFSET_CAPTURE)) {
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
     * Strip stray separators, language codes, episode numbers, and other release tags from title.
     */
    private function cleanTitle(string $title): string
    {
        // Remove all bracketed tags (language, quality, etc.)
        $title = preg_replace('/\[[^\]]+\]/', ' ', $title);
        
        // Remove all parenthesized tags
        $title = preg_replace('/\([^)]+\)/', ' ', $title);
        
        // Remove language codes (standalone or with separators)
        $title = preg_replace('/\b(ENG|JAP|JPN|SUB|DUB|MULTI|RAW|HARDSUB|SOFTSUB|HARDDUB|SOFTDUB|ITA|SPA|FRE|GER|RUS|CHI|KOR)\b/i', ' ', $title);
        
        // Remove episode patterns
        $title = preg_replace('/\s*-\s*\d{1,4}\s*$/i', '', $title);
        $title = preg_replace('/\s*-\s*$/i', '', $title);
        $title = preg_replace('/\s+E(?:p(?:isode)?)?\s*0*\d{1,4}\s*$/i', '', $title);
        $title = preg_replace('/\s+S\d+E\d+\s*$/i', '', $title);
        
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
}

