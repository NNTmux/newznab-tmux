<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TvProcessing;

use App\Exceptions\TvProviderException;
use App\Services\TmdbClient;
use App\Services\TraktService;
use App\Services\TvMazeClient;
use App\Services\TvProcessing\Providers\TvdbProvider;
use CanIHaveSomeCoffee\TheTVDbAPI\Route\RouteFactory;
use CanIHaveSomeCoffee\TheTVDbAPI\TheTVDbAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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
        $login = new Response(200, [], (string) json_encode(['data' => ['token' => 'token']]));
        $provider = $this->tvdbProvider(new MockHandler([$login, new Response(404, [], '{}'), new Response(429, [], '{}')]));

        $this->assertFalse($provider->getShowInfo('No Such Show'));

        $this->expectException(TvProviderException::class);
        $provider->getShowInfo('Severance');
    }

    public function test_tvdb_logs_in_again_once_when_the_token_expires(): void
    {
        $token = static fn (): Response => new Response(200, [], (string) json_encode(['data' => ['token' => 'token']]));
        $handler = new MockHandler([$token(), new Response(401, [], '{}'), $token(), new Response(404, [], '{}')]);

        $this->assertNull($this->tvdbProvider($handler)->seriesExtended(371980));
        $this->assertSame(0, $handler->count(), 'Expected login, 401, a second login and the retried request.');
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
        $handler = new MockHandler([new Response(401, [], '{}')]);
        $provider = $this->tvdbProvider($handler);

        foreach ([1, 2] as $attempt) {
            try {
                $provider->getShowInfo('Severance');
                $this->fail('Expected a rejected login to be retried later, not a miss.');
            } catch (TvProviderException $e) {
                $this->assertSame(401, $e->statusCode);
                $this->assertGreaterThan(0, $e->retryAfterSeconds);
            }
        }

        $this->assertSame(0, $handler->count(), 'The second lookup must not log in again during the pause.');
    }

    public function test_tvdb_forbidden_and_failed_logins_are_transient(): void
    {
        $login = new Response(200, [], (string) json_encode(['data' => ['token' => 'token']]));

        $this->expectException(TvProviderException::class);
        $this->tvdbProvider(new MockHandler([$login, new Response(403, [], '{}')]))->getShowInfo('Severance');
    }

    public function test_tvdb_login_server_error_is_transient(): void
    {
        $this->expectException(TvProviderException::class);
        $this->tvdbProvider(new MockHandler([new Response(503, [], '{}')]))->getShowInfo('Severance');
    }

    private function tvdbProvider(MockHandler $handler): TvdbProvider
    {
        // The library caches route objects per process; drop them so they bind to this client.
        (new \ReflectionProperty(RouteFactory::class, 'routeInstances'))->setValue(null, []);

        $client = new Client([
            'base_uri' => TheTVDbAPI::API_BASE_URI,
            'handler' => HandlerStack::create($handler),
        ]);

        return new TvdbProvider(new TheTVDbAPI('eng', '', $client));
    }
}
