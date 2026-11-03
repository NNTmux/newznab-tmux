<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Enums\TvLookupStatus;
use App\Exceptions\TvProviderException;
use App\Models\Category;
use App\Services\TvProcessing\Pipes\LocalDbPipe;
use App\Services\TvProcessing\Pipes\ParseInfoPipe;
use App\Services\TvProcessing\Pipes\TvdbPipe;
use App\Services\TvProcessing\Pipes\TvMazePipe;
use App\Services\TvProcessing\TvProcessingCandidateQuery;
use App\Services\TvProcessing\TvProcessingPassable;
use App\Services\TvProcessing\TvProcessingPipeline;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ConfigurationTestBuilder;
use Tests\Support\Tv\FakeTvProvider;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class TvProcessingPipelineTest extends TestCase
{
    use TvProcessingSchema;

    private FakeTvProvider $tvdb;

    private FakeTvProvider $tvmaze;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tvdb.api_key' => 'test-key', 'nntmux_api.fanarttv_api_key' => '', 'tmdb.api_key' => '']);
        Cache::flush();
        $this->createTvProcessingSchema();

        $this->tvdb = new FakeTvProvider;
        $this->tvmaze = new FakeTvProvider;
        $this->app->bind(TvProcessingPipeline::class, fn (): TvProcessingPipeline => $this->pipeline());
    }

    public function test_new_show_is_stored_and_the_release_linked_to_its_own_episode(): void
    {
        // Occupies tv_episodes.id = 1, where the old addEpisode() sent every new episode.
        $this->insertEpisode($this->insertVideo('Unrelated'), 1, 1);
        $this->tvdb->shows['the expanse'] = ['title' => 'The Expanse', 'tvdb' => 280619];
        $this->tvdb->episodes[280619] = [['series' => 1, 'episode' => 2, 'firstaired' => '2015-12-14']];
        $releaseId = $this->insertTvRelease('The.Expanse.S01E02.720p.HDTV.x264-GRP');

        $this->pipeline()->process();

        $videoId = (int) DB::table('videos')->where('title', 'The Expanse')->value('id');
        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'series' => 1, 'episode' => 2])->value('id');
        $this->assertRelease($releaseId, $videoId, $episodeId);
        $this->assertGreaterThan(1, $episodeId);
    }

    public function test_known_show_fetches_an_episode_it_does_not_have_yet(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980]);
        $this->insertEpisode($videoId, 1, 1, '2022-02-18');
        $this->tvdb->episodes[371980] = [['series' => 2, 'episode' => 1, 'firstaired' => '2025-01-17']];
        $releaseId = $this->insertTvRelease('Severance.S02E01.1080p.WEB.h264-GRP');

        $this->pipeline()->process();

        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'series' => 2])->value('id');
        $this->assertRelease($releaseId, $videoId, $episodeId);
        $this->assertSame(['search' => 0, 'all' => 0, 'episode' => 1, 'airdate' => 0, 'resolve' => 0], $this->tvdb->calls);
    }

    public function test_missing_episode_keeps_the_show_and_is_retried_until_it_airs(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980]);
        $this->insertEpisode($videoId, 1, 1);
        $releaseId = $this->insertTvRelease('Severance.S03E01.1080p.WEB.h264-GRP');

        $this->pipeline()->process();

        $release = DB::table('releases')->find($releaseId);
        $this->assertRelease($releaseId, $videoId, TvLookupStatus::EpisodeMissing->value, attempts: 1);
        $this->assertGreaterThanOrEqual(now()->addMinutes(59)->toDateTimeString(), $release->tv_lookup_retry_at);
        $this->assertSame(0, DB::table('releases')->whereRaw(TvProcessingCandidateQuery::episodeRetryCondition())->count());

        DB::table('releases')->where('id', $releaseId)->update(['tv_lookup_retry_at' => now()->subMinute()]);
        $this->tvdb->episodes[371980] = [['series' => 3, 'episode' => 1, 'firstaired' => '2026-10-01']];
        $this->artisan('tv:reprocess-missing-episodes')->assertSuccessful();

        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'series' => 3])->value('id');
        $this->assertRelease($releaseId, $videoId, $episodeId);
    }

    public function test_a_provider_without_an_id_for_a_known_show_looks_it_up_through_the_other_ids(): void
    {
        // Production: most shows found on TVDB carry no TVMaze id, so TVMaze was skipped.
        $videoId = $this->insertVideo('The Traitors: New Blood', ['tvdb' => 466894]);
        $this->tvmaze->siteIdsByTvdb[466894] = 81234;
        $this->tvmaze->episodes[81234] = [['series' => 1, 'episode' => 7, 'firstaired' => '2026-10-31']];
        $first = $this->insertTvRelease('The.Traitors.New.Blood.S01E07.1080p.WEB.h264-EDITH');
        $second = $this->insertTvRelease('The.Traitors.New.Blood.S01E07.720p.WEB.h264-GRP');

        $this->pipeline()->process();

        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'series' => 1, 'episode' => 7])->value('id');
        $this->assertRelease($first, $videoId, $episodeId);
        $this->assertRelease($second, $videoId, $episodeId);
        $this->assertSame(81234, (int) DB::table('videos')->where('id', $videoId)->value('tvmaze'));
        $this->assertSame(1, $this->tvmaze->calls['resolve'], 'The id is stored, so the second release reuses it.');
    }

    public function test_a_show_unknown_to_a_provider_is_not_cross_referenced_again_for_a_day(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980]);
        $this->insertTvRelease('Severance.S03E01.1080p.WEB.h264-GRP');
        $this->insertTvRelease('Severance.S03E02.1080p.WEB.h264-GRP');

        $this->pipeline()->process();

        $this->assertSame(1, $this->tvmaze->calls['resolve']);
        $this->assertSame(0, (int) DB::table('videos')->where('id', $videoId)->value('tvmaze'));
    }

    public function test_a_provider_outage_never_spends_the_last_episode_attempt(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980]);
        $this->insertEpisode($videoId, 1, 1);
        $releaseId = $this->insertTvRelease('Severance.S03E01.1080p.WEB.h264-GRP', [
            'videos_id' => $videoId,
            'tv_episodes_id' => TvLookupStatus::EpisodeMissing->value,
            'tv_lookup_attempts' => TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS - 1,
            'tv_lookup_retry_at' => now()->subMinute(),
        ]);
        $this->tvdb->failWith = new TvProviderException('tvdb', 'TVDB returned 503', 503);
        $this->tvmaze->failWith = new TvProviderException('tvmaze', 'TVMaze returned 503', 503);

        $this->artisan('tv:reprocess-missing-episodes')->assertSuccessful();

        $this->assertRelease($releaseId, $videoId, TvLookupStatus::EpisodeMissing->value, attempts: TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS - 1);
        $this->assertGreaterThan(now()->toDateTimeString(), DB::table('releases')->where('id', $releaseId)->value('tv_lookup_retry_at'));
    }

    public function test_a_country_qualified_release_falls_back_to_the_title_without_the_country(): void
    {
        $this->tvdb->shows['big brother'] = ['title' => 'Big Brother (AU)', 'country' => 'AU', 'tvdb' => 76524];
        $this->tvdb->episodes[76524] = [['series' => 2026, 'episode' => 5, 'firstaired' => '2026-10-30']];
        $releaseId = $this->insertTvRelease('Big.Brother.Australia.S2026E05.1080p.WEB');

        $this->pipeline()->process();

        $videoId = (int) DB::table('videos')->where('tvdb', 76524)->value('id');
        $this->assertGreaterThan(0, $videoId);
        $this->assertSame(2, $this->tvdb->calls['search'], 'Full title first, then without the country.');
        $this->assertRelease($releaseId, $videoId, (int) DB::table('tv_episodes')->where('videos_id', $videoId)->value('id'));
    }

    public function test_repaired_legacy_backlog_gets_a_single_retry(): void
    {
        $videoId = $this->insertVideo('Severance', ['tvdb' => 371980]);
        $this->insertEpisode($videoId, 1, 1);
        $releaseId = $this->insertTvRelease('Severance.S01E05.720p.WEB.h264-GRP', ['videos_id' => $videoId]);

        $this->artisan('tv:repair-links', ['--stuck' => true])->assertSuccessful();
        $this->artisan('tv:reprocess-missing-episodes')->assertSuccessful();

        $this->assertRelease($releaseId, $videoId, TvLookupStatus::EpisodeMissing->value, attempts: TvProcessingCandidateQuery::MAX_EPISODE_ATTEMPTS);
        $this->assertNull(DB::table('releases')->where('id', $releaseId)->value('tv_lookup_retry_at'));
        $this->assertSame(0, DB::table('releases')->whereRaw(TvProcessingCandidateQuery::episodeRetryCondition())->count());
    }

    public function test_episode_retries_take_the_newest_releases_first(): void
    {
        $oldShow = $this->insertVideo('Old Show', ['tvdb' => 1]);
        $newShow = $this->insertVideo('New Show', ['tvdb' => 2]);
        $due = ['tv_episodes_id' => TvLookupStatus::EpisodeMissing->value, 'tv_lookup_retry_at' => now()->subMinute()];
        $old = $this->insertTvRelease('Old.Show.S01E01', ['videos_id' => $oldShow, 'postdate' => now()->subYears(3), ...$due]);
        $new = $this->insertTvRelease('New.Show.S01E01', ['videos_id' => $newShow, 'postdate' => now()->subHour(), ...$due]);

        $this->artisan('tv:reprocess-missing-episodes', ['--limit' => 1])->assertSuccessful();

        $this->assertSame(1, (int) DB::table('releases')->where('id', $new)->value('tv_lookup_attempts'));
        $this->assertSame(0, (int) DB::table('releases')->where('id', $old)->value('tv_lookup_attempts'));
    }

    public function test_show_no_provider_knows_is_given_up_without_retries(): void
    {
        $releaseId = $this->insertTvRelease('Nobody.Knows.This.Show.S01E01.720p.HDTV.x264-GRP');

        $this->pipeline()->process();

        $this->assertRelease($releaseId, 0, TvLookupStatus::ShowNotFound->value);
    }

    public function test_unparseable_name_is_marked_parse_failed(): void
    {
        $releaseId = $this->insertTvRelease('a3f8e2c19b7d4e6f8a1b2c3d4e5f6a7b');

        $this->pipeline()->process();

        $this->assertRelease($releaseId, 0, TvLookupStatus::ParseFailed->value);
        $this->assertSame(0, $this->tvdb->calls['search']);
    }

    public function test_season_pack_is_linked_to_its_show(): void
    {
        $videoId = $this->insertVideo('The Expanse', ['tvdb' => 280619]);
        $releaseId = $this->insertTvRelease('The.Expanse.S01.1080p.BluRay.x264-GRP');

        $this->pipeline()->process();

        $this->assertRelease($releaseId, $videoId, TvLookupStatus::SeasonPack->value);
    }

    public function test_rate_limited_provider_defers_the_release_instead_of_giving_up(): void
    {
        $this->tvdb->failWith = new TvProviderException('tvdb', 'Too many requests', 429, 7200);
        $releaseId = $this->insertTvRelease('Rate.Limited.Show.S01E01.720p.HDTV.x264-GRP');

        $this->pipeline()->process();
        $this->pipeline()->process();

        $release = DB::table('releases')->find($releaseId);
        $this->assertRelease($releaseId, 0, TvLookupStatus::Pending->value, attempts: 1);
        $this->assertGreaterThanOrEqual(now()->addSeconds(7190)->toDateTimeString(), $release->tv_lookup_retry_at);
        $this->assertSame(1, $this->tvdb->calls['search'], 'A deferred release must not be picked up before it is due.');
    }

    public function test_repeated_transient_failures_eventually_give_up(): void
    {
        $this->tvdb->failWith = new TvProviderException('tvdb', 'Service unavailable', 503);
        $releaseId = $this->insertTvRelease('Flaky.Show.S01E01.720p.HDTV.x264-GRP', [
            'tv_lookup_attempts' => TvProcessingCandidateQuery::MAX_TRANSIENT_ATTEMPTS - 1,
            'tv_lookup_retry_at' => now()->subMinute(),
        ]);

        $this->pipeline()->process();

        $this->assertRelease($releaseId, 0, TvLookupStatus::ShowNotFound->value);
    }

    public function test_provider_is_skipped_for_the_rest_of_the_run_after_repeated_transient_failures(): void
    {
        $this->tvdb->failWith = new TvProviderException('tvdb', 'Service unavailable', 503);
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'] as $index => $show) {
            $this->insertTvRelease($show.'.Show.S01E01.720p.HDTV.x264-GRP', ['postdate' => now()->subMinutes($index)]);
        }

        $this->pipeline()->process();

        $this->assertSame(3, $this->tvdb->calls['search']);
        $this->assertSame(5, DB::table('releases')->where(['tv_episodes_id' => 0, 'tv_lookup_attempts' => 1])->count());
    }

    public function test_one_broken_release_does_not_stop_the_rest_of_the_batch(): void
    {
        $this->tvdb->crashTitles = ['broken show'];
        $this->tvdb->shows['the expanse'] = ['title' => 'The Expanse', 'tvdb' => 280619];
        $this->tvdb->episodes[280619] = [['series' => 1, 'episode' => 2, 'firstaired' => '2015-12-14']];
        $broken = $this->insertTvRelease('Broken.Show.S01E01.720p.HDTV.x264-GRP', ['postdate' => now()]);
        $working = $this->insertTvRelease('The.Expanse.S01E02.720p.HDTV.x264-GRP', ['postdate' => now()->subDay()]);

        $this->pipeline()->process();

        $this->assertRelease($broken, 0, TvLookupStatus::Pending->value, attempts: 1);
        $this->assertGreaterThan(0, (int) DB::table('releases')->where('id', $working)->value('tv_episodes_id'));
    }

    public function test_specials_in_season_zero_are_matched(): void
    {
        $videoId = $this->insertVideo('Doctor Who', ['tvdb' => 78804]);
        $this->insertEpisode($videoId, 1, 1);
        $this->tvdb->episodes[78804] = [['series' => 0, 'episode' => 5, 'firstaired' => '2010-12-25']];
        $releaseId = $this->insertTvRelease('Doctor.Who.S00E05.A.Christmas.Carol.720p.HDTV.x264-GRP');

        $this->pipeline()->process();

        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'series' => 0, 'episode' => 5])->value('id');
        $this->assertRelease($releaseId, $videoId, $episodeId);
    }

    public function test_daily_show_is_matched_by_airdate(): void
    {
        $videoId = $this->insertVideo('The Daily Show', ['tvmaze' => 249]);
        $this->insertEpisode($videoId, 29, 1, '2024-01-08');
        $this->tvmaze->airdateLookup = true;
        $this->tvmaze->episodes[249] = [['series' => 29, 'episode' => 40, 'firstaired' => '2024-03-05']];
        $releaseId = $this->insertTvRelease('The.Daily.Show.2024.03.05.Guest.720p.WEB.h264-GRP');

        $this->pipeline()->process();

        $episodeId = (int) DB::table('tv_episodes')->where(['videos_id' => $videoId, 'episode' => 40])->value('id');
        $this->assertRelease($releaseId, $videoId, $episodeId);
        $this->assertSame(1, $this->tvmaze->calls['airdate']);
    }

    public function test_releases_of_one_new_show_share_a_single_search_and_episode_download(): void
    {
        $this->tvdb->shows['the expanse'] = ['title' => 'The Expanse', 'tvdb' => 280619];
        $this->tvdb->episodes[280619] = [
            ['series' => 1, 'episode' => 1, 'firstaired' => '2015-12-14'],
            ['series' => 1, 'episode' => 5, 'firstaired' => '2016-01-05'],
        ];
        $this->insertTvRelease('The.Expanse.S01E01.720p.HDTV.x264-GRP', ['postdate' => now()]);
        $this->insertTvRelease('The.Expanse.S01E05.720p.HDTV.x264-GRP', ['postdate' => now()->subHour()]);

        $this->pipeline()->process();

        $this->assertSame(2, DB::table('releases')->where('tv_episodes_id', '>', 0)->count());
        $this->assertSame(['search' => 1, 'all' => 1, 'episode' => 0, 'airdate' => 0, 'resolve' => 0], $this->tvdb->calls);
    }

    public function test_renamed_only_lookup_mode_applies_even_when_a_worker_passes_mode_one(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['tv_lookup' => 2]);
        $releaseId = $this->insertTvRelease('Not.Renamed.Show.S01E01.720p.HDTV.x264-GRP', ['isrenamed' => 0]);

        $this->pipeline()->process('', '', 1);

        $this->assertRelease($releaseId, 0, TvLookupStatus::Pending->value);
        $this->assertSame(0, $this->tvdb->calls['search']);
    }

    public function test_work_condition_only_selects_due_tv_releases_with_no_show(): void
    {
        $due = $this->insertTvRelease('Due.S01E01');
        $this->insertTvRelease('Deferred.S01E01', ['tv_lookup_retry_at' => now()->addHour()]);
        $this->insertTvRelease('Anime.S01E01', ['categories_id' => Category::TV_ANIME]);
        $this->insertTvRelease('Tiny.S01E01', ['size' => 1000]);
        $this->insertTvRelease('Linked.S01E01', ['videos_id' => 5, 'tv_episodes_id' => 7]);
        $this->insertTvRelease('Legacy.S01E01', ['tv_episodes_id' => -2]);

        $this->assertSame([$due], DB::table('releases')->whereRaw(TvProcessingCandidateQuery::workCondition(1))->pluck('id')->all());
        $this->assertSame(0, DB::table('releases')->whereRaw(TvProcessingCandidateQuery::workCondition(0))->count());
    }

    public function test_output_setting_is_applied_to_subsequent_processing_calls(): void
    {
        $releaseId = $this->insertTvRelease('Unparseable');
        $release = DB::table('releases')->find($releaseId);
        $pipe = Mockery::mock(ParseInfoPipe::class)->makePartial();
        $pipe->shouldReceive('setEchoOutput')->once()->with(true)->ordered()->passthru();
        $pipe->shouldReceive('setEchoOutput')->once()->with(false)->ordered()->passthru();
        $pipe->shouldReceive('handle')->twice()->andReturnUsing(
            static function (TvProcessingPassable $passable, Closure $next): TvProcessingPassable {
                $passable->markParseFailed();

                return $passable;
            }
        );
        $pipeline = new TvProcessingPipeline([$pipe], false);
        $runState = $pipeline->newRunState();

        $this->assertSame($pipeline, $pipeline->setEchoOutput(true));
        $pipeline->processRelease($release, false, $runState);
        $this->assertSame($pipeline, $pipeline->setEchoOutput(false));
        $pipeline->processRelease($release, false, $runState);

        $this->assertRelease($releaseId, 0, TvLookupStatus::ParseFailed->value);
    }

    private function pipeline(): TvProcessingPipeline
    {
        return new TvProcessingPipeline([
            new ParseInfoPipe,
            new LocalDbPipe,
            new TvdbPipe($this->tvdb),
            new TvMazePipe($this->tvmaze),
        ], false);
    }

    private function assertRelease(int $releaseId, int $videoId, int $episodeState, int $attempts = 0): void
    {
        $release = DB::table('releases')->find($releaseId);

        $this->assertSame(
            ['videos_id' => $videoId, 'tv_episodes_id' => $episodeState, 'tv_lookup_attempts' => $attempts],
            ['videos_id' => (int) $release->videos_id, 'tv_episodes_id' => (int) $release->tv_episodes_id, 'tv_lookup_attempts' => (int) $release->tv_lookup_attempts],
        );
    }
}
