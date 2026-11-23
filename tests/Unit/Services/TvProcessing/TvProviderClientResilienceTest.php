<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TvProcessing;

use App\Exceptions\TvProviderException;
use App\Services\TmdbClient;
use App\Services\TraktService;
use App\Services\TvMazeClient;
use App\Services\TvProcessing\Providers\TvdbProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TvProviderClientResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'tmdb.api_key' => 'tmdb-key',
            'tmdb.retry_times' => 1,
            'nntmux_api.trakttv_api_key' => 'trakt-key',
            'nntmux_api.trakttv_retry_times' => 1,
            'nntmux_api.tvmaze_retry_times' => 1,
            'tvdb.api_key' => 'tvdb-key',
            'tvdb.retry_times' => 1,
        ]);
        Cache::flush();
    }

    public function test_tvmaze_rate_limit_is_reported_as_transient_with_its_retry_after(): void
    {
        Http::fake(['api.tvmaze.com/*' => Http::response(['status' => 429], 429, ['Retry-After' => '30'])]);

        try {
            (new TvMazeClient)->singleSearchAkas('Severance');
            $this->fail('Expected a transient TVMaze failure.');
        } catch (TvProviderException $e) {
            $this->assertSame(['tvmaze', 429, 30], [$e->provider, $e->statusCode, $e->retryAfterSeconds]);
        }
    }

    public function test_tvmaze_not_found_and_aka_lists_are_parsed(): void
    {
        Http::fake([
            'api.tvmaze.com/singlesearch/*' => Http::response(['status' => 404, 'name' => 'Not Found'], 404),
            'api.tvmaze.com/shows/1/akas' => Http::response([['name' => 'Haus des Geldes', 'country' => null], ['name' => 'La Casa de Papel']]),
            'api.tvmaze.com/lookup/shows*' => Http::response(['id' => 7, 'name' => 'Money Heist', 'webChannel' => ['name' => 'Netflix', 'country' => null]]),
        ]);
        $client = new TvMazeClient;

        $this->assertNull($client->singleSearchAkas('No Such Show'));
        $this->assertSame(['Haus des Geldes', 'La Casa de Papel'], $client->akas(1));
        $this->assertSame(7, $client->lookup('thetvdb', 327417)?->id);
    }

    public function test_tmdb_transient_failures_only_throw_when_the_caller_opts_in(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'Busy'], 429, ['Retry-After' => '12'])]);

        $this->assertNull((new TmdbClient)->searchTv('Severance'));

        $this->expectExceptionObject(new TvProviderException('tmdb', 'TMDB returned 429 for /search/tv', 429, 12));
        (new TmdbClient)->throwingOnTransientFailure()->searchTv('Severance');
    }

    public function test_trakt_transient_failures_only_throw_when_the_caller_opts_in(): void
    {
        Http::fake(['api.trakt.tv/*' => Http::response([], 503)]);

        $this->assertNull((new TraktService)->getShowSummary('severance'));

        $this->expectException(TvProviderException::class);
        (new TraktService)->throwingOnTransientFailure()->getShowSummary('severance');
    }

    public function test_tvdb_rate_limit_is_transient_and_not_found_is_a_miss(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/search*' => Http::sequence()
            ->push(['status' => 'failure', 'data' => null], 404)
            ->push(['status' => 'failure', 'data' => null], 429, ['Retry-After' => '30'])]);
        $provider = new TvdbProvider;

        $this->assertFalse($provider->getShowInfo('No Such Show'));

        try {
            $provider->getShowInfo('Severance');
            $this->fail('Expected a transient TVDB failure.');
        } catch (TvProviderException $e) {
            $this->assertSame(['tvdb', 429, 30], [$e->provider, $e->statusCode, $e->retryAfterSeconds]);
        }
    }

    public function test_tvdb_logs_in_again_once_when_the_token_expires(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/series/*' => Http::sequence()
            ->push(['message' => 'Unauthorized'], 401)
            ->push(['status' => 'failure', 'data' => null], 404)]);

        $this->assertNull((new TvdbProvider)->showById(371980));
        Http::assertSentCount(4);
    }

    public function test_tvdb_token_is_shared_by_every_provider_instance(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/search*' => Http::response(['status' => 'success', 'data' => []])]);

        (new TvdbProvider)->getShowInfo('Severance');
        (new TvdbProvider)->getShowInfo('Severance');

        Http::assertSentCount(3);
    }

    public function test_an_unreadable_tvdb_body_is_transient_and_not_a_miss(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/search*' => Http::response('<html>Bad gateway</html>')]);

        $this->expectException(TvProviderException::class);
        (new TvdbProvider)->getShowInfo('Severance');
    }

    public function test_blocked_or_rejected_requests_are_never_reported_as_not_found(): void
    {
        Http::fake([
            'api.tvmaze.com/*' => Http::response('<html>Cloudflare</html>', 403),
            'api.themoviedb.org/*' => Http::response(['status_message' => 'Invalid API key'], 401),
            'api.trakt.tv/*' => Http::response([], 403),
        ]);

        foreach ([
            'tvmaze' => fn () => (new TvMazeClient)->singleSearchAkas('Severance'),
            'tmdb' => fn () => (new TmdbClient)->throwingOnTransientFailure()->searchTv('Severance'),
            'trakt' => fn () => (new TraktService)->throwingOnTransientFailure()->searchShows('Severance'),
        ] as $provider => $request) {
            try {
                $request();
                $this->fail("Expected {$provider} to throw instead of returning a miss.");
            } catch (TvProviderException $e) {
                $this->assertSame($provider, $e->provider);
            }
        }

        $this->assertNull((new TmdbClient)->searchTv('Severance'), 'Optional enrichment callers still get null.');
    }

    public function test_rejected_tvdb_credentials_pause_lookups_instead_of_missing_every_show(): void
    {
        Http::fake(['api4.thetvdb.com/v4/login' => Http::response(['status' => 'failure', 'message' => 'InvalidAPIKey: apikey invalid', 'data' => null], 401)]);

        // A second provider stands in for another worker: the pause is shared.
        foreach ([new TvdbProvider, new TvdbProvider] as $provider) {
            try {
                $provider->getShowInfo('Severance');
                $this->fail('Expected a rejected login to be retried later, not a miss.');
            } catch (TvProviderException $e) {
                $this->assertSame(401, $e->statusCode);
                $this->assertGreaterThan(0, $e->retryAfterSeconds);
            }
        }

        Http::assertSentCount(1);
    }

    public function test_tvdb_forbidden_and_failed_logins_are_transient(): void
    {
        $this->fakeTvdb(['api4.thetvdb.com/v4/search*' => Http::response([], 403)]);

        $this->expectException(TvProviderException::class);
        (new TvdbProvider)->getShowInfo('Severance');
    }

    public function test_tvdb_login_server_error_is_transient(): void
    {
        Http::fake(['api4.thetvdb.com/v4/login' => Http::response([], 503)]);

        $this->expectException(TvProviderException::class);
        (new TvdbProvider)->getShowInfo('Severance');
    }

    /**
     * @param  array<string, mixed>  $responses
     */
    private function fakeTvdb(array $responses): void
    {
        Http::fake(['api4.thetvdb.com/v4/login' => Http::response(['status' => 'success', 'data' => ['token' => 'token']])] + $responses);
    }
}
