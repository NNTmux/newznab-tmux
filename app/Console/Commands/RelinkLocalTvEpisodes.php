<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TvLookupStatus;
use App\Models\Release;
use App\Models\TvEpisode;
use App\Models\Video;
use App\Services\TvProcessing\LocalShowMatcher;
use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\AbstractTvProvider;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvLookupOutcomeWriter;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use App\Services\TvProcessing\TvProcessingResult;
use App\Services\TvProcessing\TvReleaseContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Links TV releases to episodes that are already stored, without calling any API.
 *
 * Episodes usually arrive after the first releases of an episode were processed, and
 * the legacy processor left many releases half done. Both are fixed here from the
 * local tables alone, so the API retry queue only keeps releases nothing local can solve.
 *
 * Default mode walks releases linked to a show but not to an episode. --unmatched walks
 * releases with no show (show not found or unparseable), which the current parser and
 * local title matching may now resolve. Each mode resumes where its last run stopped.
 */
class RelinkLocalTvEpisodes extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tv:relink-local
                            {--unmatched : Walk releases without a show (show not found, parse failed) instead of linked ones}
                            {--dry-run : Count what would change without writing}
                            {--chunk=1000 : Releases per batch in --unmatched mode}
                            {--shows=100 : Shows per batch in the default mode}
                            {--limit=0 : Stop after walking this many releases (0 = no limit)}
                            {--restart : Start from the lowest release id instead of resuming}';

    /**
     * @var string
     */
    protected $description = 'Link TV releases to episodes already stored locally (no API calls)';

    /** Releases linked to a show that still wait for an episode, legacy states included. */
    private const array LINKED_STATES = [0, -1, -2, -3, -4, -6, -11];

    /** Shows whose episode lists are kept in memory at once in --unmatched mode. */
    private const int EPISODE_CACHE_SHOWS = 500;

    private LocalDbProvider $parser;

    private LocalShowMatcher $matcher;

    private TvLookupOutcomeWriter $writer;

    private bool $dryRun = false;

    /** @var array<int, array{numbers: array<string, int>, dates: array<string, int>}> */
    private array $episodes = [];

    /** @var array{walked: int, linked: int, moved: int, packs: int, show_only: int, requeued: int} */
    private array $counts = ['walked' => 0, 'linked' => 0, 'moved' => 0, 'packs' => 0, 'show_only' => 0, 'requeued' => 0];

    public function handle(): int
    {
        $this->parser = new LocalDbProvider;
        $this->matcher = new LocalShowMatcher($this->parser);
        $this->writer = new TvLookupOutcomeWriter;
        $this->dryRun = (bool) $this->option('dry-run');
        $unmatched = (bool) $this->option('unmatched');
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));

        $this->matcher->preloadTitles();

        $cursorKey = 'tv:relink-local:cursor:'.($unmatched ? 'unmatched' : 'linked');
        $cursor = $this->option('restart') ? [] : (array) Cache::get($cursorKey, []);

        $this->writer->beginBatch();
        try {
            $next = $unmatched
                ? $this->walkUnmatched($cursor, $chunk, $limit)
                : $this->walkLinked($cursor, max(1, (int) $this->option('shows')), $limit);
        } finally {
            $this->writer->endBatch();
        }

        if (! $this->dryRun) {
            // A finished walk starts over next time: releases keep arriving and episodes keep being stored.
            $next === null ? Cache::forget($cursorKey) : Cache::forever($cursorKey, $next);
        }

        $this->table(['Walked', 'Linked', 'Moved to another show', 'Season packs', 'Show only', 'Requeued'], [[
            number_format($this->counts['walked']),
            number_format($this->counts['linked']),
            number_format($this->counts['moved']),
            number_format($this->counts['packs']),
            number_format($this->counts['show_only']),
            number_format($this->counts['requeued']),
        ]]);
        if ($this->dryRun) {
            $this->info('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }

    /**
     * Walk shows in id order and fetch their waiting releases through
     * ix_releases_tv_processing (videos_id, tv_episodes_id). Walking releases by id
     * instead scans the whole table: state 0 is shared with every non-TV release.
     *
     * @param  array<string, int>  $cursor
     *                                      --limit is checked after each batch of shows, so a run may walk a little more.
     * @return array<string, int>|null where to resume, or null when every show was walked
     */
    private function walkLinked(array $cursor, int $showsPerBatch, int $limit): ?array
    {
        $lastVideoId = (int) ($cursor['video'] ?? 0);

        while (true) {
            $videoIds = Video::query()
                ->where('type', AbstractTvProvider::TYPE_TV)
                ->where('id', '>', $lastVideoId)
                ->orderBy('id')
                ->limit($showsPerBatch)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if ($videoIds === []) {
                return null;
            }

            $releases = Release::query()
                ->select(['id', 'searchname', 'groups_id', 'categories_id', 'videos_id', 'tv_episodes_id'])
                ->whereRaw(TvProcessingCandidateQuery::categoryCondition())
                ->whereIn('videos_id', $videoIds)
                ->whereIn('tv_episodes_id', self::LINKED_STATES)
                ->get();

            $this->episodes = [];
            $this->loadEpisodes($releases->pluck('videos_id')->map(fn ($id): int => (int) $id)->unique()->all());
            foreach ($releases as $release) {
                $this->relinkLinked($release);
                $this->counts['walked']++;
            }

            $lastVideoId = end($videoIds);
            if ($limit > 0 && $this->counts['walked'] >= $limit) {
                return ['video' => $lastVideoId];
            }
        }
    }

    /**
     * Walk show-less releases one state at a time, by id, through ix_releases_tv_episodes_id.
     *
     * @param  array<string, int>  $cursor
     * @return array<string, int>|null where to resume, or null when every release was walked
     */
    private function walkUnmatched(array $cursor, int $chunk, int $limit): ?array
    {
        $states = [TvLookupStatus::ShowNotFound->value, TvLookupStatus::ParseFailed->value];
        $stateIndex = max(0, array_search((int) ($cursor['state'] ?? $states[0]), $states, true) ?: 0);
        $lastId = (int) ($cursor['id'] ?? 0);

        for (; $stateIndex < \count($states); $stateIndex++, $lastId = 0) {
            while (true) {
                $releases = Release::query()
                    ->select(['id', 'searchname', 'groups_id', 'categories_id', 'videos_id', 'tv_episodes_id'])
                    ->whereRaw(TvProcessingCandidateQuery::categoryCondition())
                    ->where('videos_id', 0)
                    ->where('tv_episodes_id', $states[$stateIndex])
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($chunk)
                    ->get();
                if ($releases->isEmpty()) {
                    break;
                }

                if (\count($this->episodes) > self::EPISODE_CACHE_SHOWS) {
                    $this->episodes = [];
                }
                foreach ($releases as $release) {
                    $this->relinkUnmatched($release);
                    $this->counts['walked']++;
                    $lastId = (int) $release->id;
                }

                if ($limit > 0 && $this->counts['walked'] >= $limit) {
                    return ['state' => $states[$stateIndex], 'id' => $lastId];
                }
            }
        }

        return null;
    }

    private function relinkLinked(Release $release): void
    {
        $parsed = $this->parse($release);
        if ($parsed === null) {
            return;
        }

        $videoId = (int) $release->videos_id;
        if ($parsed->seasonPack) {
            $this->record($release, TvProcessingResult::seasonPack($videoId, 'Local relink'), 'packs');

            return;
        }

        $episodeId = $this->episodeFor($videoId, $parsed);
        if ($episodeId > 0) {
            $this->record($release, TvProcessingResult::matched($videoId, $episodeId, 'Local relink'), 'linked');

            return;
        }

        // Linked to the wrong one of two same-named shows ("Jeopardy" vs "Jeopardy!"):
        // move it only when exactly one other show of that title holds the episode.
        $others = array_values(array_diff($this->matcher->candidates($parsed->titleWithoutYear()), [$videoId]));
        if ($others === [] || \count($others) > 5) {
            return;
        }

        $holders = [];
        foreach ($others as $other) {
            $otherEpisode = $this->episodeFor($other, $parsed);
            if ($otherEpisode > 0) {
                $holders[$other] = $otherEpisode;
            }
        }
        if (\count($holders) === 1) {
            $this->record($release, TvProcessingResult::matched((int) array_key_first($holders), (int) reset($holders), 'Local relink'), 'moved');
        }
    }

    private function relinkUnmatched(Release $release): void
    {
        $parsed = $this->parse($release);
        if ($parsed === null) {
            return;
        }

        $videoId = $this->matcher->find($parsed, looseFallback: false);
        if ($videoId <= 0) {
            // Unparseable before, parseable now: let the regular workers ask the APIs.
            if ((int) $release->tv_episodes_id === TvLookupStatus::ParseFailed->value) {
                $this->record($release, TvProcessingResult::pending(), 'requeued');
            }

            return;
        }

        if ($parsed->seasonPack) {
            $this->record($release, TvProcessingResult::seasonPack($videoId, 'Local relink'), 'packs');

            return;
        }

        $episodeId = $this->episodeFor($videoId, $parsed);
        $episodeId > 0
            ? $this->record($release, TvProcessingResult::matched($videoId, $episodeId, 'Local relink'), 'linked')
            // The show is known now, so the scheduled episode retries can fetch the episode.
            : $this->record($release, TvProcessingResult::episodeMissing($videoId, 'Local relink'), 'show_only');
    }

    private function parse(Release $release): ?ParsedTvRelease
    {
        return ParsedTvRelease::fromParseInfo($this->parser->parseInfo((string) $release->searchname));
    }

    /**
     * The stored episode a release refers to, by season and episode or else by airdate,
     * like AbstractTvProvider::getBySeasonEp() but from one in-memory list per show.
     */
    private function episodeFor(int $videoId, ParsedTvRelease $parsed): int
    {
        if (! isset($this->episodes[$videoId])) {
            $this->loadEpisodes([$videoId]);
        }
        $episodes = $this->episodes[$videoId];

        if ($parsed->hasEpisodeNumbers()) {
            return $episodes['numbers'][$parsed->season.':'.$parsed->episode] ?? 0;
        }

        return $parsed->hasAirdate() ? ($episodes['dates'][(string) $parsed->airdate] ?? 0) : 0;
    }

    /**
     * Load the episode lists of several shows with one query.
     *
     * @param  list<int>  $videoIds
     */
    private function loadEpisodes(array $videoIds): void
    {
        foreach ($videoIds as $videoId) {
            $this->episodes[$videoId] = ['numbers' => [], 'dates' => []];
        }
        if ($videoIds === []) {
            return;
        }

        TvEpisode::query()
            ->whereIn('videos_id', $videoIds)
            ->orderBy('id')
            ->select(['id', 'videos_id', 'series', 'episode', 'firstaired'])
            ->toBase()
            ->get()
            ->each(function (object $row): void {
                $videoId = (int) $row->videos_id;
                $id = (int) $row->id;
                if ((int) $row->episode > 0) {
                    $this->episodes[$videoId]['numbers'][(int) $row->series.':'.(int) $row->episode] ??= $id;
                }
                $date = substr((string) $row->firstaired, 0, 10);
                if ($date !== '') {
                    $this->episodes[$videoId]['dates'][$date] ??= $id;
                }
            });
    }

    /**
     * @param  'linked'|'moved'|'packs'|'show_only'|'requeued'  $count
     */
    private function record(Release $release, TvProcessingResult $result, string $count): void
    {
        $this->counts[$count]++;
        if ($this->dryRun) {
            return;
        }

        $this->writer->record(new TvReleaseContext(
            (int) $release->id,
            (string) $release->searchname,
            (int) $release->groups_id,
            (int) $release->categories_id,
            (int) $release->videos_id,
            (int) $release->tv_episodes_id,
        ), $result);
    }
}
