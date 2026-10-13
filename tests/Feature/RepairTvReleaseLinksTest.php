<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TvLookupStatus;
use App\Facades\Search;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class RepairTvReleaseLinksTest extends TestCase
{
    use TvProcessingSchema;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->createTvProcessingSchema();
    }

    public function test_dry_run_counts_without_changing_anything(): void
    {
        $releaseId = $this->insertTvRelease('Legacy.S01E01', ['tv_episodes_id' => -2]);

        $this->artisan('tv:repair-links', ['--legacy' => true, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(-2, (int) DB::table('releases')->where('id', $releaseId)->value('tv_episodes_id'));
    }

    public function test_legacy_statuses_are_moved_to_the_current_states(): void
    {
        $waiting = $this->insertTvRelease('Waiting.S01E01', ['tv_episodes_id' => -3]);
        $exhausted = $this->insertTvRelease('Exhausted.S01E01', ['tv_episodes_id' => -4]);

        $this->artisan('tv:repair-links', ['--legacy' => true])->assertSuccessful();

        $this->assertSame(TvLookupStatus::Pending->value, (int) DB::table('releases')->where('id', $waiting)->value('tv_episodes_id'));
        $this->assertSame(TvLookupStatus::ShowNotFound->value, (int) DB::table('releases')->where('id', $exhausted)->value('tv_episodes_id'));
    }

    public function test_stuck_releases_with_a_show_are_requeued_or_marked_as_season_packs(): void
    {
        $videoId = $this->insertVideo('The Expanse');
        $episode = $this->insertTvRelease('The.Expanse.S02E03.720p.HDTV.x264-GRP', ['videos_id' => $videoId, 'tv_episodes_id' => -2]);
        $pack = $this->insertTvRelease('The.Expanse.S02.1080p.BluRay.x264-GRP', ['videos_id' => $videoId]);

        $this->artisan('tv:repair-links', ['--stuck' => true])->assertSuccessful();

        $requeued = DB::table('releases')->find($episode);
        $this->assertSame(TvLookupStatus::EpisodeMissing->value, (int) $requeued->tv_episodes_id);
        $this->assertNotNull($requeued->tv_lookup_retry_at);
        $this->assertSame(TvLookupStatus::SeasonPack->value, (int) DB::table('releases')->where('id', $pack)->value('tv_episodes_id'));
    }

    public function test_links_to_another_shows_episode_or_a_deleted_show_are_removed(): void
    {
        $expanse = $this->insertVideo('The Expanse');
        $other = $this->insertVideo('Other Show');
        $episodeOne = $this->insertEpisode($other, 1, 1);
        $this->assertSame(1, $episodeOne);
        $correct = $this->insertEpisode($expanse, 1, 2);

        $foreign = $this->insertTvRelease('The.Expanse.S01E05.720p.HDTV.x264-GRP', ['videos_id' => $expanse, 'tv_episodes_id' => $episodeOne]);
        $sameShowWrong = $this->insertTvRelease('Other.Show.S04E09.720p.HDTV.x264-GRP', ['videos_id' => $other, 'tv_episodes_id' => $episodeOne]);
        $sameShowRight = $this->insertTvRelease('Other.Show.S01E01.720p.HDTV.x264-GRP', ['videos_id' => $other, 'tv_episodes_id' => $episodeOne]);
        $fine = $this->insertTvRelease('The.Expanse.S01E02.720p.HDTV.x264-GRP', ['videos_id' => $expanse, 'tv_episodes_id' => $correct]);
        $orphan = $this->insertTvRelease('Deleted.Show.S01E01.720p.HDTV.x264-GRP', ['videos_id' => 999, 'tv_episodes_id' => 0]);

        $this->artisan('tv:repair-links', ['--wrong-episode' => true, '--orphans' => true])->assertSuccessful();

        foreach ([$foreign, $sameShowWrong, $orphan] as $reindexed) {
            Search::getFacadeRoot()->shouldHaveReceived('updateRelease')->with($reindexed)->once();
        }
        Search::getFacadeRoot()->shouldNotHaveReceived('updateRelease', [$fine]);

        $states = DB::table('releases')->pluck('tv_episodes_id', 'id')->map(fn ($state): int => (int) $state);
        $this->assertSame(TvLookupStatus::EpisodeMissing->value, $states[$foreign]);
        $this->assertSame(TvLookupStatus::EpisodeMissing->value, $states[$sameShowWrong]);
        $this->assertSame($episodeOne, $states[$sameShowRight]);
        $this->assertSame($correct, $states[$fine]);
        $this->assertSame(0, (int) DB::table('releases')->where('id', $orphan)->value('videos_id'));
    }

    public function test_duplicate_episodes_are_merged_into_the_dated_row_and_a_second_run_changes_nothing(): void
    {
        $videoId = $this->insertVideo('The Expanse');
        $undated = $this->insertEpisode($videoId, 1, 2);
        $dated = $this->insertEpisode($videoId, 1, 2, '2015-12-14');
        $releaseId = $this->insertTvRelease('The.Expanse.S01E02', ['videos_id' => $videoId, 'tv_episodes_id' => $undated]);
        DB::table('tv_info')->where('videos_id', $videoId)->update(['localzone' => "''"]);

        $this->artisan('tv:repair-links', ['--all' => true])->assertSuccessful();
        $this->artisan('tv:repair-links', ['--all' => true, '--dry-run' => true])
            ->expectsTable(['Repair', 'Would change'], [
                ['Legacy statuses normalized', '0'],
                ['Stuck releases requeued', '0'],
                ['Releases of deleted shows reset', '0'],
                ['Wrong episode links removed', '0'],
                ['Literal \'\' timezones cleared', '0'],
                ['Duplicate episodes merged', '0'],
            ])
            ->assertSuccessful();

        $this->assertSame([$dated], DB::table('tv_episodes')->where('videos_id', $videoId)->pluck('id')->all());
        $this->assertSame($dated, (int) DB::table('releases')->where('id', $releaseId)->value('tv_episodes_id'));
        $this->assertSame('', DB::table('tv_info')->where('videos_id', $videoId)->value('localzone'));
    }
}
