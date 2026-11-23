<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\TvProviderException;
use App\Services\MdbListClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MdbListClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'nntmux_api.mdblist_api_key' => 'test-key',
            'nntmux_api.mdblist_retry_times' => 1,
            'nntmux_api.mdblist_batch_size' => 10,
        ]);
        Cache::flush();
        Log::spy();
    }

    public function test_it_returns_scores_by_imdb_id_and_leaves_unscored_movies_out(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(self::fixture('ratings_tomatoes'))]);

        $scores = (new MdbListClient)->tomatometerScores(['0133093', '3148348', '9999999']);

        $this->assertSame(['0133093' => 83, '3148348' => 79], $scores);
        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/rating/movie/tomatoes?apikey=test-key')
            && $request['provider'] === 'imdb'
            && $request['ids'] === ['tt0133093', 'tt3148348', 'tt9999999']);
    }

    public function test_it_sends_one_request_per_batch(): void
    {
        config(['nntmux_api.mdblist_batch_size' => 2]);
        Http::fake(['api.mdblist.com/*' => Http::sequence()
            ->push(['ratings' => [['id' => 'tt0000001', 'rating' => 10], ['id' => 'tt0000002', 'rating' => 0]]])
            ->push(['ratings' => [['id' => 'tt0000003', 'rating' => 100]]])]);

        $scores = (new MdbListClient)->tomatometerScores(['0000001', '0000002', '0000003']);

        $this->assertSame(['0000001' => 10, '0000002' => 0, '0000003' => 100], $scores);
        Http::assertSentCount(2);
    }

    public function test_a_spent_limit_pauses_every_later_lookup(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(['error' => 'Daily API limit exceeded!'], 429, ['Retry-After' => '3600'])]);

        foreach ([new MdbListClient, new MdbListClient] as $client) {
            try {
                $client->tomatometerScores(['0133093']);
                $this->fail('A spent MDBList limit must not look like a movie without a score.');
            } catch (TvProviderException $e) {
                $this->assertSame(['mdblist', 429], [$e->provider, $e->statusCode]);
                $this->assertGreaterThan(3500, $e->retryAfterSeconds);
            }
        }

        Http::assertSentCount(1);
    }

    public function test_a_rejected_key_is_a_failure(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response(['error' => 'Invalid API key'], 401)]);

        $this->expectException(TvProviderException::class);

        (new MdbListClient)->tomatometerScores(['0133093']);
    }

    public function test_a_server_error_is_a_failure_and_does_not_pause_lookups(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::sequence()
            ->push('Bad gateway', 502)
            ->push(self::fixture('ratings_tomatoes'))]);
        $client = new MdbListClient;

        try {
            $client->tomatometerScores(['0133093']);
            $this->fail('A server error must not look like a movie without a score.');
        } catch (TvProviderException $e) {
            $this->assertSame(502, $e->statusCode);
        }

        $this->assertSame(['0133093' => 83], $client->tomatometerScores(['0133093']));
    }

    public function test_an_unreadable_body_is_a_failure(): void
    {
        Http::fake(['api.mdblist.com/*' => Http::response('<html>maintenance</html>')]);

        $this->expectException(TvProviderException::class);

        (new MdbListClient)->tomatometerScores(['0133093']);
    }

    public function test_it_makes_no_request_without_a_key(): void
    {
        config(['nntmux_api.mdblist_api_key' => '']);
        Http::fake();
        $client = new MdbListClient;

        $this->assertFalse($client->isConfigured());
        $this->assertSame([], $client->tomatometerScores(['0133093']));
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/mdblist/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
