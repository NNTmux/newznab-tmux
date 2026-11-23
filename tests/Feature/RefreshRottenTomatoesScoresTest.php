<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MovieInfo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefreshRottenTomatoesScoresTest extends TestCase
{
    private const string LAST_REFRESHED = '2026-01-01 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'nntmux_api.mdblist_api_key' => 'test-key',
            'nntmux_api.mdblist_retry_times' => 1,
            'nntmux_api.mdblist_batch_size' => 2,
        ]);
        Cache::flush();
        Log::spy();

        Schema::dropIfExists('movieinfo');
        Schema::create('movieinfo', function (Blueprint $table): void {
            $table->id();
            $table->string('imdbid')->unique();
            $table->string('title')->default('');
            $table->string('rtrating', 10)->default('');
            $table->timestamps();
        });

        foreach (['0000001' => '', '0000002' => 'N/A', '0000003' => '55%', '0000004' => ''] as $imdbId => $score) {
            MovieInfo::query()->insert([
                'imdbid' => $imdbId,
                'title' => 'Movie '.$imdbId,
                'rtrating' => $score,
                'created_at' => self::LAST_REFRESHED,
                'updated_at' => self::LAST_REFRESHED,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('movieinfo');

        parent::tearDown();
    }

    public function test_it_stores_found_scores_and_leaves_everything_else_alone(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::sequence()
            ->push(['ratings' => [['id' => 'tt0000001', 'rating' => 83]]])
            ->push(['ratings' => []])]);

        $this->artisan('movie:refresh-rt-scores')
            ->expectsOutputToContain('Updated 1 of 3 movies asked about; 0 left for the next run.')
            ->assertSuccessful();

        $this->assertSame(
            ['0000001' => '83%', '0000002' => 'N/A', '0000003' => '55%', '0000004' => ''],
            MovieInfo::query()->orderBy('imdbid')->pluck('rtrating', 'imdbid')->all(),
        );
        // The metadata refresh is due by age, so a score-only change must not make the row look fresh.
        $this->assertSame(self::LAST_REFRESHED, MovieInfo::query()->where('imdbid', '0000001')->value('updated_at')->format('Y-m-d H:i:s'));
        Http::assertSent(static fn (Request $request): bool => $request['ids'] === ['tt0000001', 'tt0000002']);
        Http::assertSent(static fn (Request $request): bool => $request['ids'] === ['tt0000004']);
    }

    public function test_a_limited_run_continues_where_the_previous_one_stopped(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(['ratings' => []])]);

        $this->artisan('movie:refresh-rt-scores', ['--limit' => 2])
            ->expectsOutputToContain('1 left for the next run.')
            ->assertSuccessful();
        $this->artisan('movie:refresh-rt-scores', ['--limit' => 2])
            ->expectsOutputToContain('0 left for the next run.')
            ->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(static fn (Request $request): bool => $request['ids'] === ['tt0000004']);
    }

    public function test_a_dry_run_stores_nothing(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(['ratings' => [['id' => 'tt0000001', 'rating' => 83]]])]);

        $this->artisan('movie:refresh-rt-scores', ['--dry-run' => true])
            ->expectsOutputToContain('tt0000001: none -> 83%')
            ->assertSuccessful();

        $this->assertSame('', MovieInfo::query()->where('imdbid', '0000001')->value('rtrating'));
    }

    public function test_it_refreshes_existing_scores_only_when_asked_to(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(['ratings' => [['id' => 'tt0000003', 'rating' => 60]]])]);

        $this->artisan('movie:refresh-rt-scores', ['--all' => true])->assertSuccessful();

        $this->assertSame('60%', MovieInfo::query()->where('imdbid', '0000003')->value('rtrating'));
    }

    public function test_it_stops_when_mdblist_refuses_and_keeps_its_place(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::sequence()
            ->push(['ratings' => [['id' => 'tt0000001', 'rating' => 83]]])
            ->push(['error' => 'Daily API limit exceeded!'], 429, ['Retry-After' => '3600'])]);

        $this->artisan('movie:refresh-rt-scores')
            ->expectsOutputToContain('Stopped: MDBList returned 429')
            ->expectsOutputToContain('1 left for the next run.')
            ->assertSuccessful();

        $this->assertSame('83%', MovieInfo::query()->where('imdbid', '0000001')->value('rtrating'));
        $this->assertSame('', MovieInfo::query()->where('imdbid', '0000004')->value('rtrating'));
    }

    public function test_it_fails_without_a_key(): void
    {
        config(['nntmux_api.mdblist_api_key' => '']);
        Http::fake();

        $this->artisan('movie:refresh-rt-scores')->assertFailed();

        Http::assertNothingSent();
    }
}
