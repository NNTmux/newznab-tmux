<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TraktService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TraktServiceTest extends TestCase
{
    private TraktService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nntmux_api.trakttv_api_key' => 'test_trakt_key',
            'nntmux_api.trakttv_timeout' => 30,
            'nntmux_api.trakttv_retry_times' => 1,
            'nntmux_api.trakttv_retry_delay' => 1,
            'cache.default' => 'array',
        ]);

        Cache::flush();
        $this->service = new TraktService;
    }

    public function test_get_show_summary_accepts_numeric_trakt_id(): void
    {
        Http::fake([
            'https://api.trakt.tv/shows/1390*' => Http::response([
                'title' => 'Breaking Bad',
                'ids' => [
                    'trakt' => 1390,
                ],
            ], 200),
        ]);

        $summary = $this->service->getShowSummary(1390, 'full');

        $this->assertNotNull($summary);
        $this->assertSame('Breaking Bad', $summary['title']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/shows/1390')
                && $request['extended'] === 'full';
        });
    }

    public function test_public_metadata_requests_use_api_key_headers_without_oauth(): void
    {
        Http::fake([
            'https://api.trakt.tv/movies/tt0137523*' => Http::response([
                'title' => 'Fight Club',
                'ids' => [
                    'imdb' => 'tt0137523',
                ],
            ], 200),
        ]);

        $summary = $this->service->getMovieSummary('tt0137523', 'full');

        $this->assertNotNull($summary);

        Http::assertSent(function ($request) {
            $headers = array_change_key_case($request->headers(), CASE_LOWER);

            return ($headers['trakt-api-key'][0] ?? null) === 'test_trakt_key'
                && ($headers['trakt-api-version'][0] ?? null) === '2'
                && ! array_key_exists('authorization', $headers);
        });
    }

    public function test_search_by_id_formats_imdb_id_without_padding(): void
    {
        Http::fake([
            'https://api.trakt.tv/search/imdb/tt0137523*' => Http::response([
                [
                    'type' => 'movie',
                    'movie' => [
                        'title' => 'Fight Club',
                    ],
                ],
            ], 200),
        ]);

        $results = $this->service->searchById('0137523', 'imdb', 'movie');

        $this->assertIsArray($results);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/search/imdb/tt0137523')
                && ! str_contains($request->url(), 'tt00137523')
                && $request['type'] === 'movie';
        });
    }

    public function test_episode_summary_with_imdb_id_preserves_leading_zeroes(): void
    {
        Http::fake([
            'https://api.trakt.tv/shows/tt0137523/seasons/1/episodes/1*' => Http::response([
                'title' => 'Pilot',
                'season' => 1,
                'number' => 1,
            ], 200),
        ]);

        $episode = $this->service->getEpisodeSummary('0137523', 1, 1, 'full', 'imdb');

        $this->assertIsArray($episode);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/shows/tt0137523/seasons/1/episodes/1')
                && ! str_contains($request->url(), 'tt00137523')
                && $request['extended'] === 'full';
        });
    }

    public function test_episode_summary_fallback_accepts_tt_prefixed_imdb_id(): void
    {
        Http::fake([
            'https://api.trakt.tv/shows/tt0137523/seasons/1/episodes/1*' => Http::response([
                'title' => 'Pilot',
                'season' => 1,
                'number' => 1,
            ], 200),
        ]);

        $episode = $this->service->getEpisodeSummaryWithFallback(['imdb' => 'tt0137523'], 1, 1, 'full');

        $this->assertIsArray($episode);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/shows/tt0137523/seasons/1/episodes/1')
                && $request['extended'] === 'full';
        });
    }

    public function test_search_by_id_omits_empty_type_query_parameter(): void
    {
        Http::fake([
            'https://api.trakt.tv/search/trakt/1390*' => Http::response([], 200),
        ]);

        $this->service->searchById(1390, 'trakt');

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return str_contains($request->url(), 'https://api.trakt.tv/search/trakt/1390')
                && ! array_key_exists('type', $query);
        });
    }

    public function test_get_show_seasons_supports_episode_extended_public_endpoint(): void
    {
        Http::fake([
            'https://api.trakt.tv/shows/1390/seasons*' => Http::response([
                [
                    'number' => 1,
                    'episodes' => [],
                ],
            ], 200),
        ]);

        $seasons = $this->service->getShowSeasons('1390', 'episodes');

        $this->assertIsArray($seasons);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.trakt.tv/shows/1390/seasons')
                && $request['extended'] === 'episodes';
        });
    }
}
