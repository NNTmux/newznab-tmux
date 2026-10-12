<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TvLookupStatus;
use App\Models\Category;
use App\Models\Release;
use App\Models\TvEpisode;
use App\Models\TvInfo;
use App\Services\Releases\ReleaseBrowseService;
use App\Services\TvProcessing\ParsedTvRelease;
use App\Services\TvProcessing\Providers\LocalDbProvider;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repairs TV release links left behind by the legacy per-provider TV processor.
 *
 * Every step is idempotent: a second run changes nothing. Only steps that remove or
 * replace a positive episode link reindex the affected releases, because the search
 * index treats every tv_episodes_id <= 0 the same.
 */
class RepairTvReleaseLinks extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tv:repair-links
                            {--legacy : Move legacy provider hand-off statuses (-1..-4, -6) to the current states}
                            {--stuck : Requeue releases linked to a show but left without an episode or a lookup state}
                            {--wrong-episode : Unlink releases whose episode belongs to another show or doesn\'t match the release}
                            {--orphans : Reset releases linked to a show that no longer exists}
                            {--localzone : Clear literal \'\' timezones stored in tv_info}
                            {--dedupe-episodes : Merge duplicate tv_episodes rows of the same show, season and episode}
                            {--all : Run every repair}
                            {--dry-run : Only count what would change}
                            {--chunk=1000 : Rows per batch}
                            {--limit=0 : Stop each repair after this many rows (0 = no limit)}';

    /**
     * @var string
     */
    protected $description = 'Repair TV release links and lookup states left by the legacy TV processor';

    private bool $dryRun = false;

    private int $chunk = 1000;

    private int $limit = 0;

    private bool $linksChanged = false;

    private ?LocalDbProvider $parser = null;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunk = max(1, (int) $this->option('chunk'));
        $this->limit = max(0, (int) $this->option('limit'));

        $steps = [
            'legacy' => 'Legacy statuses normalized',
            'stuck' => 'Stuck releases requeued',
            'orphans' => 'Releases of deleted shows reset',
            'wrong-episode' => 'Wrong episode links removed',
            'localzone' => 'Literal \'\' timezones cleared',
            'dedupe-episodes' => 'Duplicate episodes merged',
        ];

        $selected = array_filter(array_keys($steps), fn (string $step): bool => (bool) $this->option('all') || (bool) $this->option($step));
        if ($selected === []) {
            $this->error('Choose at least one repair, or --all.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($selected as $step) {
            $count = match ($step) {
                'legacy' => $this->repairLegacy(),
                'stuck' => $this->repairStuck(),
                'orphans' => $this->repairOrphans(),
                'wrong-episode' => $this->repairWrongEpisodes(),
                'localzone' => $this->repairLocalzone(),
                'dedupe-episodes' => $this->dedupeEpisodes(),
            };
            $rows[] = [$steps[$step], number_format($count)];
        }

        if ($this->linksChanged) {
            ReleaseBrowseService::bumpCacheVersion();
        }

        $this->table(['Repair', $this->dryRun ? 'Would change' : 'Changed'], $rows);

        return self::SUCCESS;
    }

    private function repairLegacy(): int
    {
        $pending = $this->tvReleases()
            ->where('videos_id', 0)
            ->whereIn('tv_episodes_id', TvLookupStatus::LEGACY_PENDING);
        $exhausted = $this->tvReleases()
            ->where('videos_id', 0)
            ->whereIn('tv_episodes_id', TvLookupStatus::LEGACY_EXHAUSTED);

        return $this->updateInChunks($pending, ['tv_episodes_id' => TvLookupStatus::Pending->value, 'tv_lookup_attempts' => 0, 'tv_lookup_retry_at' => null])
            + $this->updateInChunks($exhausted, ['tv_episodes_id' => TvLookupStatus::ShowNotFound->value, 'tv_lookup_attempts' => 0, 'tv_lookup_retry_at' => null]);
    }

    /**
     * Releases linked to a show with episode 0 or a legacy status: season packs keep the
     * show as SeasonPack, everything else gets one more episode lookup.
     */
    private function repairStuck(): int
    {
        $query = $this->tvReleases()
            ->where('videos_id', '>', 0)
            ->where('tv_episodes_id', '<=', 0)
            ->whereNotIn('tv_episodes_id', [TvLookupStatus::EpisodeMissing->value, TvLookupStatus::SeasonPack->value]);

        return $this->eachChunk($query, ['id', 'searchname'], function (Collection $releases): void {
            $seasonPacks = [];
            $missing = [];
            foreach ($releases as $release) {
                if ($this->parse((string) $release->searchname)?->seasonPack) {
                    $seasonPacks[] = $release->id;
                } else {
                    $missing[] = $release->id;
                }
            }

            Release::query()->whereIn('id', $seasonPacks)->update(['tv_episodes_id' => TvLookupStatus::SeasonPack->value, 'tv_lookup_attempts' => 0, 'tv_lookup_retry_at' => null]);
            Release::query()->whereIn('id', $missing)->update(self::singleRetryColumns());
        });
    }

    private function repairOrphans(): int
    {
        $query = $this->tvReleases()
            ->where('videos_id', '>', 0)
            ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('videos')->whereColumn('videos.id', 'releases.videos_id'));

        return $this->eachChunk($query, ['id'], function (Collection $releases): void {
            $ids = $releases->pluck('id')->all();
            Release::query()->whereIn('id', $ids)->update(TvProcessingCandidateQuery::resetColumns());
            ReleaseSearchIndexSync::forIds($ids);
            $this->linksChanged = true;
        });
    }

    /**
     * Two shapes of bad links: an episode of another show (or a deleted episode), and
     * releases on tv_episodes.id = 1, where the old addEpisode() bug sent every newly
     * inserted episode. The latter are kept only when the episode really matches.
     */
    private function repairWrongEpisodes(): int
    {
        $foreign = $this->tvReleases()
            ->where('tv_episodes_id', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereNotExists(fn ($sub) => $sub->select(DB::raw(1))->from('tv_episodes')
                    ->whereColumn('tv_episodes.id', 'releases.tv_episodes_id')
                    ->whereColumn('tv_episodes.videos_id', 'releases.videos_id')));

        $changed = $this->eachChunk($foreign, ['id', 'videos_id'], function (Collection $releases): void {
            $this->requeueEpisodeLookup($releases->pluck('id')->all());
        });

        $episodeOne = TvEpisode::query()->find(1);
        if ($episodeOne === null) {
            return $changed;
        }

        // Needs a parse per release to know, so dry runs walk the rows as well.
        $wrongCount = 0;
        $this->tvReleases()
            ->where('tv_episodes_id', 1)
            ->where('videos_id', $episodeOne->videos_id)
            ->select(['id', 'searchname'])
            ->chunkById($this->chunk, function (Collection $releases) use ($episodeOne, &$wrongCount): bool {
                $wrong = $releases
                    ->reject(fn ($release): bool => $this->matchesEpisode($this->parse((string) $release->searchname), $episodeOne))
                    ->pluck('id');
                if ($this->limit > 0) {
                    $wrong = $wrong->take(max(0, $this->limit - $wrongCount));
                }
                if (! $this->dryRun) {
                    $this->requeueEpisodeLookup($wrong->all());
                }
                $wrongCount += $wrong->count();

                return $this->limit === 0 || $wrongCount < $this->limit;
            });

        return $changed + $wrongCount;
    }

    private function matchesEpisode(?ParsedTvRelease $parsed, TvEpisode $episode): bool
    {
        if ($parsed === null) {
            return false;
        }

        if ($parsed->hasEpisodeNumbers()) {
            return $parsed->season === (int) $episode->series && $parsed->episode === (int) $episode->episode;
        }

        return $parsed->hasAirdate() && substr((string) $episode->firstaired, 0, 10) === $parsed->airdate;
    }

    /**
     * @param  list<int>  $ids
     */
    private function requeueEpisodeLookup(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $withShow = Release::query()->whereIn('id', $ids)->whereExists(
            fn ($sub) => $sub->select(DB::raw(1))->from('videos')->whereColumn('videos.id', 'releases.videos_id')
        )->pluck('id')->all();

        Release::query()->whereIn('id', $withShow)->update(self::singleRetryColumns());
        Release::query()->whereIn('id', array_diff($ids, $withShow))->update(TvProcessingCandidateQuery::resetColumns());

        ReleaseSearchIndexSync::forIds($ids);
        $this->linksChanged = true;
    }

    /**
     * Queue an episode lookup now, with the retry budget already spent but one: these
     * episodes have been missing for a long time, so one fresh lookup (which also uses
     * episodes stored since) is worth it, a week of retries per release is not.
     *
     * @return array{tv_episodes_id: int, tv_lookup_attempts: int, tv_lookup_retry_at: Carbon}
     */
    private static function singleRetryColumns(): array
    {
        return [
            'tv_episodes_id' => TvLookupStatus::EpisodeMissing->value,
            'tv_lookup_attempts' => TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS - 1,
            'tv_lookup_retry_at' => now(),
        ];
    }

    private function repairLocalzone(): int
    {
        $query = TvInfo::query()->where('localzone', "''");
        $count = $this->capped($query->count());

        if (! $this->dryRun && $count > 0) {
            $query->limit($count)->update(['localzone' => '']);
        }

        return $count;
    }

    /**
     * Duplicates arise because the unique key includes the nullable firstaired. The row
     * with an airdate (then the oldest) is kept and releases are repointed to it.
     */
    private function dedupeEpisodes(): int
    {
        $groups = DB::table('tv_episodes')
            ->select(['videos_id', 'series', 'episode'])
            ->where('episode', '>', 0)
            ->groupBy(['videos_id', 'series', 'episode'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $removed = 0;
        foreach ($groups as $group) {
            if ($this->limit > 0 && $removed >= $this->limit) {
                break;
            }

            $ids = TvEpisode::query()
                ->where(['videos_id' => $group->videos_id, 'series' => $group->series, 'episode' => $group->episode])
                ->orderByRaw('firstaired IS NULL')
                ->orderBy('id')
                ->pluck('id')
                ->all();
            $keep = array_shift($ids);
            $removed += \count($ids);

            if ($this->dryRun || $ids === []) {
                continue;
            }

            DB::transaction(function () use ($ids, $keep): void {
                $releaseIds = Release::query()->whereIn('tv_episodes_id', $ids)->pluck('id')->all();
                Release::query()->whereIn('id', $releaseIds)->update(['tv_episodes_id' => $keep]);
                TvEpisode::query()->whereIn('id', $ids)->delete();
                ReleaseSearchIndexSync::forIds($releaseIds);
            });
            $this->linksChanged = true;
        }

        return $removed;
    }

    /**
     * @return Builder<Release>
     */
    private function tvReleases(): Builder
    {
        return Release::query()->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER]);
    }

    /**
     * @param  Builder<Release>  $query
     * @param  array<string, mixed>  $columns
     */
    private function updateInChunks(Builder $query, array $columns): int
    {
        return $this->eachChunk($query, ['id'], function (Collection $releases) use ($columns): void {
            Release::query()->whereIn('id', $releases->pluck('id'))->update($columns);
        });
    }

    /**
     * Walk the matching releases in id order, calling $repair on each chunk unless this is a dry run.
     *
     * @param  Builder<Release>  $query
     * @param  list<string>  $columns
     * @param  callable(Collection<int, Release>): void  $repair
     */
    private function eachChunk(Builder $query, array $columns, callable $repair): int
    {
        if ($this->dryRun) {
            return $this->capped((clone $query)->count());
        }

        $changed = 0;
        $query->select($columns)->chunkById($this->chunk, function (Collection $releases) use ($repair, &$changed): bool {
            if ($this->limit > 0) {
                $releases = $releases->take($this->limit - $changed);
            }

            $repair($releases);
            $changed += $releases->count();

            return $this->limit === 0 || $changed < $this->limit;
        });

        return $changed;
    }

    private function capped(int $count): int
    {
        return $this->limit > 0 ? min($count, $this->limit) : $count;
    }

    private function parse(string $searchname): ?ParsedTvRelease
    {
        $this->parser ??= new LocalDbProvider;

        return ParsedTvRelease::fromParseInfo($this->parser->parseInfo($searchname));
    }
}
