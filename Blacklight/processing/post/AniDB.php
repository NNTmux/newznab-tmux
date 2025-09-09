<?php

declare(strict_types=1);

namespace Blacklight\processing\post;

use App\Models\AnidbEpisode;
use App\Models\AnidbInfo;
use App\Models\AnidbTitle;
use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\PopulateAniDB as PaDb;

class AniDB
{
    private const PROC_EXTFAIL = -1; // Release Anime title/episode # could not be extracted from searchname
    private const PROC_NOMATCH = -2; // AniDB ID was not found in anidb table using extracted title/episode #

    /** @var bool Whether to echo messages to CLI */
    public bool $echooutput;

    public PaDb $padb;

    /** @var int number of AniDB releases to process */
    private int $aniqty;

    /** @var int|null The status of the release being processed */
    private ?int $status;

    protected ColorCLI $colorCli;

    /**
     * Simple cache of looked up titles -> anidbid to reduce repeat queries within one run.
     * @var array<string,int>
     */
    private array $titleCache = [];

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->echooutput = (bool) config('nntmux.echocli');
        $this->padb = new PaDb;
        $this->colorCli = new ColorCLI;

        $quantity = (int) Settings::settingValue('maxanidbprocessed');
        $this->aniqty = $quantity > 0 ? $quantity : 100;
        $this->status = null;
    }

    /**
     * Queues anime releases for processing.
     *
     * @throws \Exception
     */
    public function processAnimeReleases(): void
    {
        $results = Release::query()
            ->whereNull('anidbid')
            ->where('categories_id', Category::TV_ANIME)
            ->orderByDesc('postdate')
            ->limit($this->aniqty)
            ->get();

        if ($results->count() > 0) {
            // Honor AniDB API cooldown before starting a batch.
            $this->doRandomSleep();

            foreach ($results as $release) {
                $matched = $this->matchAnimeRelease($release);
                if ($matched === false) {
                    // Persist status so we do not keep retrying hopeless releases immediately.
                    Release::query()->where('id', $release->id)->update(['anidbid' => $this->status]);
                }
            }
        } else {
            $this->colorCli->info('No anidb releases to process.');
        }
    }

    /**
     * Retrieves episode info if present.
     */
    private function checkAniDBInfo(int $anidbId, int $episode = -1): array
    {
        $q = AnidbEpisode::query()->where(['anidbid' => $anidbId]);
        if ($episode >= 0) {
            $q->where('episode_no', $episode);
        }
        $result = $q->select(['anidbid', 'episode_no', 'airdate', 'episode_title'])->first();
        return $result ? $result->toArray() : [];
    }

    /**
     * Sleeps between 10 and 15 seconds for AniDB API cooldown.
     *
     * @throws \Exception
     */
    private function doRandomSleep(): void
    {
        sleep(random_int(10, 15));
    }

    /**
     * Extracts anime title and episode info from release searchname.
     * Returns ['title' => string, 'epno' => int] on success else empty array.
     */
    private function extractTitleEpisode(string $cleanName = ''): array
    {
        // Normalize common separators
        $s = str_replace(['_', '.'], ' ', $cleanName);
        $s = preg_replace('/\s+/', ' ', (string) $s);
        $s = trim((string) $s);

        // Strip leading group tags like [Group]
        $s = preg_replace('/^(?:\[[^\]]+\]\s*)+/', '', $s);
        $s = trim((string) $s);

        $title = '';
        $ep = -1;

        // 1) Look for " - NNN" first
        if (preg_match('/\s-\s*(\d{1,3})\b/', $s, $m, PREG_OFFSET_CAPTURE)) {
            $ep = (int) $m[1][0];
            $title = substr($s, 0, (int) $m[0][1]);
        }

        // 2) If not found, look for " E0*NNN"
        if ($ep < 0 && preg_match('/\sE0*(\d{1,3})\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $ep = (int) $m[1][0];
            $title = substr($s, 0, (int) $m[0][1]);
        }

        // 3) Keywords Movie/OVA/Complete Series
        if ($ep < 0 && preg_match('/\b(Movie|OVA|Complete Series)\b/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $kind = strtolower($m[1][0]);
            $ep = match ($kind) {
                'movie', 'ova' => 1,
                'complete series' => 0,
                default => -1,
            };
            $title = substr($s, 0, (int) $m[0][1]);
        }

        // 4) BD/resolution releases: pick title before next bracket token
        if ($ep < 0 && preg_match('/\[(?:BD|\d{3,4}[ipx])\]/i', $s, $m, PREG_OFFSET_CAPTURE)) {
            $ep = 1;
            $title = substr($s, 0, (int) $m[0][1]);
        }

        $title = $this->cleanTitle((string) $title);

        if ($title === '' || $ep < 0) {
            $this->status = self::PROC_EXTFAIL;
            return [];
        }

        return ['title' => $title, 'epno' => $ep];
    }

    /**
     * Strip stray separators or tokens accidentally captured at the end of title.
     */
    private function cleanTitle(string $title): string
    {
        // Remove trailing "- 123", trailing dash, trailing E/E0/Episode tokens, and trailing Vol.
        $patterns = [
            '/\s*-\s*\d+\s*$/i',
            '/\s*-\s*$/',
            '/\s+E(?:pi(?:sode)?)?\s*0?\s*$/i',
            '/\s+Vol\.?\s*$/i',
        ];
        $title = preg_replace($patterns, '', $title);
        return trim((string) $title);
    }

    /**
     * Retrieve AniDB title row (id + title) by name attempt exact then partial.
     */
    private function getAnidbByName(string $searchName = ''): ?AnidbTitle
    {
        if ($searchName === '') {
            return null;
        }

        $key = strtolower($searchName);
        if (isset($this->titleCache[$key])) {
            return AnidbTitle::query()->select(['anidbid', 'title'])->where('anidbid', $this->titleCache[$key])->first();
        }

        // Exact (case-insensitive) first
        $exact = AnidbTitle::query()->whereRaw('LOWER(title) = ?', [$key])->select(['anidbid', 'title'])->first();
        if ($exact) {
            $this->titleCache[$key] = (int) $exact->anidbid;
            return $exact;
        }

        // Partial fallback
        $partial = AnidbTitle::query()->where('title', 'like', '%'.$searchName.'%')->select(['anidbid', 'title'])->first();
        if ($partial) {
            $this->titleCache[$key] = (int) $partial->anidbid;
        }
        return $partial;
    }

    /**
     * Matches the anime release to AniDB Info; fetches remotely if needed.
     *
     * @throws \Exception
     */
    private function matchAnimeRelease($release): bool
    {
        $matched = false;
        $type = 'Local';

        $cleanArr = $this->extractTitleEpisode((string) $release->searchname);
        if (empty($cleanArr)) {
            return false;
        }

        $title = $cleanArr['title'];
        $epno = $cleanArr['epno'];

        // We ignore episode number 0 (Complete Series) for matching episodes but still link the title.
        if ($this->echooutput) {
            $this->colorCli->climate()->info('Looking Up: Title: '.$title.' Episode: '.$epno);
        }

        $anidbTitle = $this->getAnidbByName($title);
        if (!$anidbTitle) {
            // Try with spaces replaced by % for broader matching
            $tmpName = preg_replace('/\s+/', '%', $title);
            $anidbTitle = $this->getAnidbByName($tmpName);
        }

        if ($anidbTitle && is_numeric($anidbTitle->anidbid) && (int) $anidbTitle->anidbid > 0) {
            $anidbId = (int) $anidbTitle->anidbid;

            $episodeInfo = ($epno > 0) ? $this->checkAniDBInfo($anidbId, $epno) : [];

            if (empty($episodeInfo) && $this->shouldUpdateInfo($anidbId)) {
                // Fetch remote info
                $this->padb->populateTable('info', $anidbId);
                $this->doRandomSleep();
                $episodeInfo = ($epno > 0) ? $this->checkAniDBInfo($anidbId, $epno) : [];
                $type = 'Remote';
            }

            $episodeTitle = $episodeInfo['episode_title'] ?? 'Unknown';
            $this->updateRelease($anidbId, (int) $release->id);

            if ($this->echooutput) {
                $this->colorCli->headerOver('Matched '.$type.' AniDB ID: ')
                    ->primary((string) $anidbId)
                    ->alternateOver('   Title: ')
                    ->primary($anidbTitle->title)
                    ->alternateOver('   Episode #: ')
                    ->primary((string) $epno)
                    ->alternateOver('   Episode Title: ')
                    ->primary($episodeTitle);
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
