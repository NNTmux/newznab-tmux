<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\TvProviderException;
use App\Services\OmdbClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OmdbClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['nntmux_api.omdb_api_key' => 'test-key', 'nntmux_api.omdb_retry_times' => 1]);
    }

    public function test_it_maps_the_fields_the_movie_pipeline_uses(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response([
            'Response' => 'True',
            'Title' => 'The Matrix',
            'Year' => '1999',
            'Genre' => 'Action, Sci-Fi',
            'Director' => 'Lana Wachowski, Lilly Wachowski',
            'Actors' => 'Keanu Reeves, Laurence Fishburne',
            'Plot' => 'A computer hacker discovers the truth.',
            'Language' => 'English',
            'Poster' => 'https://example.test/matrix.jpg',
            'imdbRating' => '8.7',
            'BoxOffice' => '$177,559,005',
            'Ratings' => [
                ['Source' => 'Internet Movie Database', 'Value' => '8.7/10'],
                ['Source' => 'Rotten Tomatoes', 'Value' => '83%'],
                ['Source' => 'Metacritic', 'Value' => '73/100'],
            ],
        ])]);

        $movie = (new OmdbClient)->movieByImdbId('0133093');

        $this->assertSame([
            'title' => 'The Matrix',
            'year' => '1999',
            'cover' => 'https://example.test/matrix.jpg',
            'genre' => 'Action, Sci-Fi',
            'plot' => 'A computer hacker discovers the truth.',
            'rating' => '8.7',
            'rtRating' => '83%',
            'director' => 'Lana Wachowski, Lilly Wachowski',
            'actors' => 'Keanu Reeves, Laurence Fishburne',
            'language' => 'English',
        ], $movie);
        Http::assertSent(static fn (Request $request): bool => $request['i'] === 'tt0133093' && $request['apikey'] === 'test-key');
    }

    public function test_it_blanks_values_omdb_does_not_have(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response([
            'Response' => 'True',
            'Title' => 'Obscure Film',
            'Year' => '2021',
            'Plot' => 'N/A',
            'Poster' => 'N/A',
            'Director' => 'N/A',
            'imdbRating' => 'N/A',
            'Ratings' => [['Source' => 'Internet Movie Database', 'Value' => '5.1/10']],
        ])]);

        $movie = (new OmdbClient)->movieByImdbId('1234567');

        $this->assertNotNull($movie);
        $this->assertSame('Obscure Film', $movie['title']);
        $this->assertSame('', $movie['plot']);
        $this->assertSame('', $movie['cover']);
        $this->assertSame('', $movie['director']);
        $this->assertSame('', $movie['rating']);
        $this->assertSame('', $movie['rtRating']);
        $this->assertSame('', $movie['actors']);
    }

    public function test_an_unknown_id_is_a_miss(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Error getting data.'])]);

        $this->assertNull((new OmdbClient)->movieByImdbId('9999999'));
    }

    public function test_a_spent_quota_is_a_failure_not_a_miss(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response(['Response' => 'False', 'Error' => 'Request limit reached!'], 401)]);

        try {
            (new OmdbClient)->movieByImdbId('0133093');
            $this->fail('A spent OMDb quota must not look like an unknown movie.');
        } catch (TvProviderException $e) {
            $this->assertSame('omdb', $e->provider);
            $this->assertSame(401, $e->statusCode);
            $this->assertStringContainsString('Request limit reached!', $e->getMessage());
        }
    }

    public function test_a_server_error_is_a_failure(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response('Service Unavailable', 503)]);

        $this->expectException(TvProviderException::class);

        (new OmdbClient)->movieByImdbId('0133093');
    }

    public function test_an_unreadable_body_is_a_failure(): void
    {
        Http::fake(['www.omdbapi.com/*' => Http::response('<html>maintenance</html>')]);

        $this->expectException(TvProviderException::class);

        (new OmdbClient)->movieByImdbId('0133093');
    }

    public function test_a_connection_failure_is_a_failure(): void
    {
        Http::fake(static fn () => throw new ConnectionException('timed out'));

        $this->expectException(TvProviderException::class);

        (new OmdbClient)->movieByImdbId('0133093');
    }

    public function test_it_makes_no_request_without_a_key(): void
    {
        config(['nntmux_api.omdb_api_key' => '']);
        Http::fake();

        $client = new OmdbClient;

        $this->assertFalse($client->isConfigured());
        $this->assertNull($client->movieByImdbId('0133093'));
        Http::assertNothingSent();
    }
}
