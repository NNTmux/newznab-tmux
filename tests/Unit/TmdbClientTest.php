<?php

namespace Tests\Unit;

use App\Services\TmdbClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbClientTest extends TestCase
{
    protected TmdbClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tmdb.api_key' => 'test_api_key']);
        config(['tmdb.timeout' => 30]);
        config(['tmdb.retry_times' => 3]);
        config(['tmdb.retry_delay' => 100]);
        config(['cache.default' => 'array']);
        $this->client = new TmdbClient;
    }

    // =========================================================================
    // CONFIGURATION TESTS
    // =========================================================================

    public function test_is_configured_returns_true_when_api_key_set(): void
    {
        $this->assertTrue($this->client->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_empty(): void
    {
        config(['tmdb.api_key' => '']);
        $client = new TmdbClient;
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_null(): void
    {
        // When config returns null, the (string) cast converts it to empty string
        config(['tmdb.api_key' => null]);
        $client = new TmdbClient;
        // (string) null === '', so isConfigured returns false
        $this->assertFalse($client->isConfigured());
    }

    // =========================================================================
    // IMAGE URL TESTS
    // =========================================================================

    public function test_get_image_url_returns_full_url(): void
    {
        $url = $this->client->getImageUrl('/abc123.jpg', 'w500');
        $this->assertSame('https://image.tmdb.org/t/p/w500/abc123.jpg', $url);
    }

    public function test_get_image_url_returns_empty_for_null_path(): void
    {
        $url = $this->client->getImageUrl(null);
        $this->assertSame('', $url);
    }

    public function test_get_image_url_returns_empty_for_empty_path(): void
    {
        $url = $this->client->getImageUrl('');
        $this->assertSame('', $url);
    }

    public function test_get_image_url_uses_default_size(): void
    {
        $url = $this->client->getImageUrl('/test.jpg');
        $this->assertSame('https://image.tmdb.org/t/p/w500/test.jpg', $url);
    }

    public function test_get_image_url_with_original_size(): void
    {
        $url = $this->client->getImageUrl('/test.jpg', 'original');
        $this->assertSame('https://image.tmdb.org/t/p/original/test.jpg', $url);
    }

    // =========================================================================
    // MOVIE SEARCH TESTS
    // =========================================================================

    public function test_search_movies_makes_correct_api_call(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'page' => 1,
                'results' => [
                    ['id' => 123, 'title' => 'Test Movie', 'release_date' => '2024-01-15'],
                ],
                'total_results' => 1,
                'total_pages' => 1,
            ]),
        ]);

        $result = $this->client->searchMovies('Test Movie');

        $this->assertNotNull($result);
        $this->assertSame(1, $result['total_results']);
        $this->assertCount(1, $result['results']);
        $this->assertSame('Test Movie', $result['results'][0]['title']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.themoviedb.org/3/search/movie')
                && $request['query'] === 'Test Movie'
                && $request['api_key'] === 'test_api_key';
        });
    }

    public function test_search_movies_with_year_filter(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'page' => 1,
                'results' => [],
                'total_results' => 0,
            ]),
        ]);

        $this->client->searchMovies('Test', 1, '2024');

        Http::assertSent(function ($request) {
            return $request['year'] === '2024';
        });
    }

    public function test_search_movies_returns_null_on_api_failure(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([], 500),
        ]);

        $result = $this->client->searchMovies('Test');
        $this->assertNull($result);
    }

    public function test_search_movies_returns_null_when_not_configured(): void
    {
        config(['tmdb.api_key' => '']);
        $client = new TmdbClient;

        $result = $client->searchMovies('Test');
        $this->assertNull($result);
    }

    // =========================================================================
    // GET MOVIE TESTS
    // =========================================================================

    public function test_get_movie_returns_movie_data(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/550*' => Http::response([
                'id' => 550,
                'title' => 'Fight Club',
                'overview' => 'A ticking-Loss time bomb of a movie...',
                'release_date' => '1999-10-15',
                'vote_average' => 8.4,
                'poster_path' => '/poster.jpg',
                'backdrop_path' => '/backdrop.jpg',
                'imdb_id' => 'tt0137523',
            ]),
        ]);

        $result = $this->client->getMovie(550);

        $this->assertNotNull($result);
        $this->assertSame(550, $result['id']);
        $this->assertSame('Fight Club', $result['title']);
        $this->assertSame('tt0137523', $result['imdb_id']);
    }

    public function test_get_movie_with_append_to_response(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/550*' => Http::response([
                'id' => 550,
                'title' => 'Fight Club',
                'credits' => [
                    'cast' => [['name' => 'Brad Pitt']],
                    'crew' => [['name' => 'David Fincher', 'job' => 'Director']],
                ],
            ]),
        ]);

        $result = $this->client->getMovie(550, ['credits']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'append_to_response=credits');
        });

        $this->assertNotNull($result);
        $this->assertArrayHasKey('credits', $result);
    }

    public function test_get_movie_with_imdb_id(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/tt0137523*' => Http::response([
                'id' => 550,
                'title' => 'Fight Club',
            ]),
        ]);

        $result = $this->client->getMovie('tt0137523');
        $this->assertNotNull($result);
    }

    public function test_get_movie_returns_null_on_404(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/*' => Http::response([], 404),
        ]);

        $result = $this->client->getMovie(999999999);
        $this->assertNull($result);
    }

    // =========================================================================
    // TV SEARCH TESTS
    // =========================================================================

    public function test_search_tv_returns_results(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/tv*' => Http::response([
                'page' => 1,
                'results' => [
                    ['id' => 1396, 'name' => 'Breaking Bad', 'first_air_date' => '2008-01-20'],
                ],
                'total_results' => 1,
            ]),
        ]);

        $result = $this->client->searchTv('Breaking Bad');

        $this->assertNotNull($result);
        $this->assertCount(1, $result['results']);
        $this->assertSame('Breaking Bad', $result['results'][0]['name']);
    }

    public function test_search_tv_with_first_air_date_year(): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/tv*' => Http::response([
                'results' => [],
                'total_results' => 0,
            ]),
        ]);

        $this->client->searchTv('Test Show', 1, 2020);

        Http::assertSent(function ($request) {
            return $request['first_air_date_year'] === 2020;
        });
    }

    // =========================================================================
    // TV SHOW DETAILS TESTS
    // =========================================================================

    public function test_get_tv_show_returns_show_data(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396*' => Http::response([
                'id' => 1396,
                'name' => 'Breaking Bad',
                'overview' => 'A high school chemistry teacher...',
                'first_air_date' => '2008-01-20',
                'seasons' => [
                    ['season_number' => 1, 'episode_count' => 7],
                ],
            ]),
        ]);

        $result = $this->client->getTvShow(1396);

        $this->assertNotNull($result);
        $this->assertSame(1396, $result['id']);
        $this->assertSame('Breaking Bad', $result['name']);
    }

    public function test_get_tv_external_ids(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396/external_ids*' => Http::response([
                'imdb_id' => 'tt0903747',
                'tvdb_id' => 81189,
                'tvrage_id' => 18164,
            ]),
        ]);

        $result = $this->client->getTvExternalIds(1396);

        $this->assertNotNull($result);
        $this->assertSame('tt0903747', $result['imdb_id']);
        $this->assertSame(81189, $result['tvdb_id']);
    }

    public function test_get_tv_alternative_titles(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396/alternative_titles*' => Http::response([
                'id' => 1396,
                'results' => [
                    ['title' => 'Breaking Bad - Reazioni collaterali', 'iso_3166_1' => 'IT'],
                ],
            ]),
        ]);

        $result = $this->client->getTvAlternativeTitles(1396);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['results']);
    }

    // =========================================================================
    // TV SEASON AND EPISODE TESTS
    // =========================================================================

    public function test_get_tv_season_returns_season_data(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396/season/1*' => Http::response([
                'id' => 3572,
                'season_number' => 1,
                'episodes' => [
                    [
                        'id' => 62085,
                        'name' => 'Pilot',
                        'episode_number' => 1,
                        'season_number' => 1,
                        'air_date' => '2008-01-20',
                        'overview' => 'When an unassuming high school chemistry teacher...',
                    ],
                ],
            ]),
        ]);

        $result = $this->client->getTvSeason(1396, 1);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['season_number']);
        $this->assertCount(1, $result['episodes']);
    }

    public function test_get_tv_episode_returns_episode_data(): void
    {
        Http::fake([
            'api.themoviedb.org/3/tv/1396/season/1/episode/1*' => Http::response([
                'id' => 62085,
                'name' => 'Pilot',
                'episode_number' => 1,
                'season_number' => 1,
                'air_date' => '2008-01-20',
                'overview' => 'When an unassuming high school chemistry teacher...',
            ]),
        ]);

        $result = $this->client->getTvEpisode(1396, 1, 1);

        $this->assertNotNull($result);
        $this->assertSame('Pilot', $result['name']);
        $this->assertSame(1, $result['episode_number']);
        $this->assertSame(1, $result['season_number']);
    }

    // =========================================================================
    // HELPER METHOD TESTS
    // =========================================================================

    public function test_get_string_returns_string_value(): void
    {
        $data = ['title' => 'Test Movie', 'count' => 5];
        $this->assertSame('Test Movie', TmdbClient::getString($data, 'title'));
    }

    public function test_get_string_returns_default_for_missing_key(): void
    {
        $data = ['title' => 'Test'];
        $this->assertSame('', TmdbClient::getString($data, 'missing'));
        $this->assertSame('default', TmdbClient::getString($data, 'missing', 'default'));
    }

    public function test_get_string_returns_default_for_non_string_value(): void
    {
        $data = ['count' => 123, 'list' => ['a', 'b']];
        $this->assertSame('', TmdbClient::getString($data, 'count'));
        $this->assertSame('', TmdbClient::getString($data, 'list'));
    }

    public function test_get_int_returns_integer_value(): void
    {
        $data = ['id' => 550, 'score' => '85', 'rating' => 7.5];
        $this->assertSame(550, TmdbClient::getInt($data, 'id'));
        $this->assertSame(85, TmdbClient::getInt($data, 'score'));
        $this->assertSame(7, TmdbClient::getInt($data, 'rating'));
    }

    public function test_get_int_returns_default_for_missing_key(): void
    {
        $data = ['id' => 550];
        $this->assertSame(0, TmdbClient::getInt($data, 'missing'));
        $this->assertSame(99, TmdbClient::getInt($data, 'missing', 99));
    }

    public function test_get_int_returns_default_for_non_numeric_value(): void
    {
        $data = ['title' => 'Test', 'list' => [1, 2]];
        $this->assertSame(0, TmdbClient::getInt($data, 'title'));
        $this->assertSame(0, TmdbClient::getInt($data, 'list'));
    }

    public function test_get_float_returns_float_value(): void
    {
        $data = ['rating' => 7.5, 'score' => '8.3', 'count' => 100];
        $this->assertSame(7.5, TmdbClient::getFloat($data, 'rating'));
        $this->assertSame(8.3, TmdbClient::getFloat($data, 'score'));
        $this->assertSame(100.0, TmdbClient::getFloat($data, 'count'));
    }

    public function test_get_float_returns_default_for_missing_key(): void
    {
        $data = ['rating' => 7.5];
        $this->assertSame(0.0, TmdbClient::getFloat($data, 'missing'));
        $this->assertSame(5.5, TmdbClient::getFloat($data, 'missing', 5.5));
    }

    public function test_get_array_returns_array_value(): void
    {
        $data = ['genres' => [['name' => 'Action'], ['name' => 'Drama']]];
        $result = TmdbClient::getArray($data, 'genres');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_get_array_returns_default_for_missing_key(): void
    {
        $data = ['title' => 'Test'];
        $this->assertSame([], TmdbClient::getArray($data, 'genres'));
        $this->assertSame(['default'], TmdbClient::getArray($data, 'genres', ['default']));
    }

    public function test_get_array_returns_default_for_non_array_value(): void
    {
        $data = ['title' => 'Test', 'count' => 5];
        $this->assertSame([], TmdbClient::getArray($data, 'title'));
        $this->assertSame([], TmdbClient::getArray($data, 'count'));
    }

    public function test_get_nested_returns_nested_value(): void
    {
        $data = [
            'credits' => [
                'cast' => [
                    ['name' => 'Actor 1'],
                    ['name' => 'Actor 2'],
                ],
            ],
        ];

        $cast = TmdbClient::getNested($data, 'credits.cast');
        $this->assertIsArray($cast);
        $this->assertCount(2, $cast);
    }

    public function test_get_nested_returns_default_for_missing_path(): void
    {
        $data = ['title' => 'Test'];
        $this->assertNull(TmdbClient::getNested($data, 'credits.cast'));
        $this->assertSame([], TmdbClient::getNested($data, 'credits.cast', []));
    }

    public function test_get_nested_handles_deep_paths(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $this->assertSame('deep', TmdbClient::getNested($data, 'level1.level2.level3.value'));
    }

    public function test_get_nested_returns_default_when_path_breaks(): void
    {
        $data = [
            'level1' => [
                'level2' => 'not an array',
            ],
        ];

        $this->assertSame('default', TmdbClient::getNested($data, 'level1.level2.level3', 'default'));
    }

    // =========================================================================
    // ERROR HANDLING TESTS
    // =========================================================================

    public function test_handles_connection_timeout(): void
    {
        Http::fake([
            'api.themoviedb.org/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->client->searchMovies('Test');
        $this->assertNull($result);
    }

    public function test_handles_invalid_json_response(): void
    {
        Http::fake([
            'api.themoviedb.org/*' => Http::response('invalid json', 200),
        ]);

        $result = $this->client->searchMovies('Test');
        // Laravel's Http client returns null for invalid JSON
        $this->assertNull($result);
    }

    public function test_handles_rate_limiting_response(): void
    {
        Http::fake([
            'api.themoviedb.org/*' => Http::response([
                'status_code' => 25,
                'status_message' => 'Your request count is over the allowed limit.',
            ], 429),
        ]);

        $result = $this->client->searchMovies('Test');
        $this->assertNull($result);
    }
}

