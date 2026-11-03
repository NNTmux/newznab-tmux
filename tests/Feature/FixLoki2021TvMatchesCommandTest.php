<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\Release;
use App\Models\TvEpisode;
use App\Models\Video;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FixLoki2021TvMatchesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('releases');
        Schema::dropIfExists('tv_episodes');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('videos', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('type')->default(false);
            $table->string('title');
            $table->dateTime('started');
            $table->unsignedInteger('tvdb')->default(0);
        });

        Schema::create('tv_episodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('videos_id');
            $table->unsignedSmallInteger('series')->default(0);
            $table->unsignedSmallInteger('episode')->default(0);
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->string('guid', 40)->unique();
            $table->char('leftguid', 1)->default('a');
            $table->unsignedInteger('groups_id')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('categories_id')->default(5070);
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->dateTime('postdate')->nullable();
        });

        Cache::flush();
    }

    public function test_dry_run_lists_only_exact_loki_2021_candidates_without_writes_or_provider_calls(): void
    {
        $familyLaw = $this->createVideo('Family Law', 385075, '2021-09-16 00:00:00');
        $familyEpisode = $this->createEpisode($familyLaw, 1, 1);
        $loki = $this->createRelease('Loki.2021.S01E01.1080p.WEB-DL', $familyLaw->id, $familyEpisode->id);
        $unrelated = $this->createRelease('Loki.Around.2021.S01E01.720p.WEB-DL', $familyLaw->id, $familyEpisode->id);

        $pipeline = Mockery::mock(TvProcessingPipeline::class);
        $pipeline->shouldNotReceive('setEchoOutput');
        $pipeline->shouldNotReceive('processRelease');
        $this->app->instance(TvProcessingPipeline::class, $pipeline);
        Search::shouldReceive('updateRelease')->never();

        $this->artisan('tv:fix-loki-2021', ['--dry-run' => true])
            ->expectsOutputToContain('Loki.2021.S01E01.1080p.WEB-DL')
            ->doesntExpectOutputToContain('Loki.Around.2021.S01E01.720p.WEB-DL')
            ->assertExitCode(0);

        $this->assertDatabaseHas('releases', [
            'id' => $loki->id,
            'videos_id' => $familyLaw->id,
            'tv_episodes_id' => $familyEpisode->id,
        ]);
        $this->assertDatabaseHas('releases', [
            'id' => $unrelated->id,
            'videos_id' => $familyLaw->id,
            'tv_episodes_id' => $familyEpisode->id,
        ]);
    }

    public function test_it_repairs_multiple_loki_variants_skips_correct_rows_and_syncs_the_index(): void
    {
        $familyLaw = $this->createVideo('Family Law', 385075, '2021-09-16 00:00:00');
        $familyEpisode = $this->createEpisode($familyLaw, 1, 1);
        $lokiVideo = $this->createVideo('Loki', 362472, '2021-06-09 00:00:00');
        $lokiEpisodeOne = $this->createEpisode($lokiVideo, 1, 1);
        $lokiEpisodeTwo = $this->createEpisode($lokiVideo, 1, 2);

        $first = $this->createRelease('Loki.2021.S01E01.1080p.WEB-DL', $familyLaw->id, $familyEpisode->id);
        $second = $this->createRelease('Loki (2021) S01E02 720p WEB-DL');
        $correct = $this->createRelease('Loki_2021_S01E01_2160p_WEB-DL', $lokiVideo->id, $lokiEpisodeOne->id);
        $unrelated = $this->createRelease('Family.Law.2021.S01E01.1080p.WEB-DL', $familyLaw->id, $familyEpisode->id);

        $pipeline = Mockery::mock(TvProcessingPipeline::class);
        $pipeline->shouldReceive('setEchoOutput')->once()->with(true)->andReturnSelf();
        $pipeline->shouldReceive('processRelease')
            ->twice()
            ->withArgs(fn (Release $release, bool $debug): bool => $debug && in_array($release->id, [$first->id, $second->id], true))
            ->andReturnUsing(function (Release $release) use ($lokiVideo, $lokiEpisodeOne, $lokiEpisodeTwo): array {
                $episodeId = str_contains($release->searchname, 'E02') ? $lokiEpisodeTwo->id : $lokiEpisodeOne->id;

                Release::query()->whereKey($release->id)->update([
                    'videos_id' => $lokiVideo->id,
                    'tv_episodes_id' => $episodeId,
                ]);

                return [
                    'matched' => true,
                    'status' => 'matched',
                    'provider' => 'TVDB',
                    'video_id' => $lokiVideo->id,
                    'episode_id' => $episodeId,
                ];
            });
        $this->app->instance(TvProcessingPipeline::class, $pipeline);

        Search::shouldReceive('updateRelease')->once()->with($first->id)->andReturnTrue();
        Search::shouldReceive('updateRelease')->once()->with($second->id)->andReturnTrue();
        Search::shouldReceive('updateRelease')->never()->with($correct->id);
        Search::shouldReceive('updateRelease')->never()->with($unrelated->id);

        $this->artisan('tv:fix-loki-2021', ['--debug' => true])
            ->expectsOutputToContain('Repair complete')
            ->assertExitCode(0);

        $this->assertDatabaseHas('releases', [
            'id' => $first->id,
            'videos_id' => $lokiVideo->id,
            'tv_episodes_id' => $lokiEpisodeOne->id,
        ]);
        $this->assertDatabaseHas('releases', [
            'id' => $second->id,
            'videos_id' => $lokiVideo->id,
            'tv_episodes_id' => $lokiEpisodeTwo->id,
        ]);
        $this->assertDatabaseHas('releases', [
            'id' => $correct->id,
            'videos_id' => $lokiVideo->id,
            'tv_episodes_id' => $lokiEpisodeOne->id,
        ]);
        $this->assertDatabaseHas('releases', [
            'id' => $unrelated->id,
            'videos_id' => $familyLaw->id,
            'tv_episodes_id' => $familyEpisode->id,
        ]);
    }

    public function test_it_clears_rejected_provider_results_and_returns_failure(): void
    {
        $familyLaw = $this->createVideo('Family Law', 385075, '2021-09-16 00:00:00');
        $familyEpisode = $this->createEpisode($familyLaw, 1, 1);
        $release = $this->createRelease('Loki.2021.S01E01.1080p.WEB-DL', $familyLaw->id, $familyEpisode->id);

        $pipeline = Mockery::mock(TvProcessingPipeline::class);
        $pipeline->shouldReceive('setEchoOutput')->once()->with(false)->andReturnSelf();
        $pipeline->shouldReceive('processRelease')->once()->andReturnUsing(function (Release $candidate) use ($familyLaw, $familyEpisode): array {
            Release::query()->whereKey($candidate->id)->update([
                'videos_id' => $familyLaw->id,
                'tv_episodes_id' => $familyEpisode->id,
            ]);

            return [
                'matched' => true,
                'status' => 'matched',
                'provider' => 'TVDB',
                'video_id' => $familyLaw->id,
                'episode_id' => $familyEpisode->id,
            ];
        });
        $this->app->instance(TvProcessingPipeline::class, $pipeline);
        Search::shouldReceive('updateRelease')->once()->with($release->id)->andReturnTrue();

        $this->artisan('tv:fix-loki-2021')
            ->expectsOutputToContain('did not resolve to TVDB 362472')
            ->assertExitCode(1);

        $this->assertDatabaseHas('releases', [
            'id' => $release->id,
            'videos_id' => 0,
            'tv_episodes_id' => 0,
        ]);
    }

    public function test_it_returns_failure_when_final_search_sync_fails(): void
    {
        $familyLaw = $this->createVideo('Family Law', 385075, '2021-09-16 00:00:00');
        $lokiVideo = $this->createVideo('Loki', 362472, '2021-06-09 00:00:00');
        $lokiEpisode = $this->createEpisode($lokiVideo, 1, 1);
        $release = $this->createRelease('Loki.2021.S01E01.1080p.WEB-DL', $familyLaw->id);

        $pipeline = Mockery::mock(TvProcessingPipeline::class);
        $pipeline->shouldReceive('setEchoOutput')->once()->with(false)->andReturnSelf();
        $pipeline->shouldReceive('processRelease')->once()->andReturnUsing(function (Release $candidate) use ($lokiVideo, $lokiEpisode): array {
            Release::query()->whereKey($candidate->id)->update([
                'videos_id' => $lokiVideo->id,
                'tv_episodes_id' => $lokiEpisode->id,
            ]);

            return [
                'matched' => true,
                'status' => 'matched',
                'provider' => 'TVDB',
                'video_id' => $lokiVideo->id,
                'episode_id' => $lokiEpisode->id,
            ];
        });
        $this->app->instance(TvProcessingPipeline::class, $pipeline);
        Search::shouldReceive('updateRelease')->once()->with($release->id)->andThrow(new \RuntimeException('Search unavailable'));

        $this->artisan('tv:fix-loki-2021')
            ->expectsOutputToContain('Search sync failures')
            ->assertExitCode(1);

        $this->assertDatabaseHas('releases', [
            'id' => $release->id,
            'videos_id' => $lokiVideo->id,
            'tv_episodes_id' => $lokiEpisode->id,
        ]);
    }

    private function createVideo(string $title, int $tvdb, string $started): Video
    {
        return Video::query()->create([
            'type' => 0,
            'title' => $title,
            'started' => $started,
            'tvdb' => $tvdb,
        ]);
    }

    private function createEpisode(Video $video, int $series, int $episode): TvEpisode
    {
        return TvEpisode::query()->create([
            'videos_id' => $video->id,
            'series' => $series,
            'episode' => $episode,
        ]);
    }

    private function createRelease(string $searchName, int $videoId = 0, int $episodeId = 0): Release
    {
        $guid = (string) Str::uuid();

        return Release::withoutEvents(fn (): Release => Release::query()->create([
            'name' => $searchName,
            'searchname' => $searchName,
            'guid' => $guid,
            'leftguid' => $guid[0],
            'groups_id' => 1,
            'size' => 2_000_000,
            'categories_id' => 5040,
            'videos_id' => $videoId,
            'tv_episodes_id' => $episodeId,
            'postdate' => '2025-01-25 09:55:00',
        ]));
    }
}
