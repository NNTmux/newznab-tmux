<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImdbScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ImdbScraperTest extends ImdbScraperTestCase
{
    public function test_fetch_by_id_parses_title_page_from_fixture(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/title_jsonld.html')) ?: ''),
        ]);

        $data = $scraper->fetchById('1234567');

        $this->assertIsArray($data);
        $this->assertSame('1234567', $data['imdbid']);
        $this->assertSame('Example Movie', $data['title']);
        $this->assertSame('2024', $data['year']);
        $this->assertSame('An example plot goes here.', $data['plot']);
        $this->assertSame('7.3', $data['rating']);
        $this->assertSame('https://example.com/poster_from_jsonld.jpg', $data['cover']);
        $this->assertSame(['Action', 'Adventure'], $data['genre']);
        $this->assertSame(['Famous Director'], $data['director']);
        $this->assertSame(['First Actor', 'Second Actor'], $data['actors']);
        $this->assertSame('English, Spanish', $data['language']);
        $this->assertSame('movie', $data['type']);
    }

    public function test_fetch_by_id_detects_waf_challenge_and_marks_temporary_block(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
            new Response(404, ['Content-Type' => 'application/json; charset=UTF-8'], '{"code":5,"message":"NOT_FOUND"}'),
        ]);

        $data = $scraper->fetchById('1234567');

        $this->assertFalse($data);
        $this->assertTrue($scraper->wasBlockedByWaf());
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_http_failure', $scraper->getLastFallbackFailureReason());
        $this->assertNull($scraper->getLastFetchSource());
    }

    public function test_fetch_by_id_falls_back_to_imdbapi_dev_when_title_page_is_blocked(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], json_encode([
                'id' => 'tt1234567',
                'type' => 'movie',
                'primaryTitle' => 'API Dev Movie',
                'startYear' => 2026,
                'genres' => ['Horror', 'Thriller'],
                'plot' => 'Fallback plot from imdbapi.dev.',
                'rating' => [
                    'aggregateRating' => 7.4,
                    'voteCount' => 123,
                ],
                'primaryImage' => [
                    'url' => 'https://example.com/api-dev-poster.jpg',
                ],
                'directors' => [
                    ['displayName' => 'API Director'],
                ],
                'stars' => [
                    ['displayName' => 'API Star One'],
                    ['displayName' => 'API Star Two'],
                ],
                'spokenLanguages' => [
                    ['name' => 'English'],
                    ['name' => 'Spanish'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $data = $scraper->fetchById('1234567');

        $this->assertIsArray($data);
        $this->assertTrue($scraper->wasBlockedByWaf());
        $this->assertSame('imdbapi_dev', $scraper->getLastFetchSource());
        $this->assertNull($scraper->getLastFailureReason());
        $this->assertNull($scraper->getLastFallbackFailureReason());
        $this->assertSame('1234567', $data['imdbid']);
        $this->assertSame('API Dev Movie', $data['title']);
        $this->assertSame('2026', $data['year']);
        $this->assertSame('Fallback plot from imdbapi.dev.', $data['plot']);
        $this->assertSame('7.4', $data['rating']);
        $this->assertSame('https://example.com/api-dev-poster.jpg', $data['cover']);
        $this->assertSame(['Horror', 'Thriller'], $data['genre']);
        $this->assertSame(['API Director'], $data['director']);
        $this->assertSame(['API Star One', 'API Star Two'], $data['actors']);
        $this->assertSame('English, Spanish', $data['language']);
        $this->assertSame('movie', $data['type']);
    }

    public function test_fetch_by_id_returns_false_when_imdbapi_dev_payload_lacks_title(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], json_encode([
                'id' => 'tt1234567',
                'startYear' => 2026,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->assertFalse($scraper->fetchById('1234567'));
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_invalid_payload', $scraper->getLastFallbackFailureReason());
        $this->assertNull($scraper->getLastFetchSource());
    }

    public function test_fetch_by_id_skips_imdbapi_dev_when_minimum_interval_is_active(): void
    {
        config([
            'nntmux_api.imdbapi_dev_min_interval_seconds' => 60,
            'nntmux_api.imdbapi_dev_cooldown_seconds' => 300,
        ]);

        $scraper = $this->makeScraperWithResponses([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], json_encode([
                'id' => 'tt1234567',
                'type' => 'movie',
                'primaryTitle' => 'First Fallback Movie',
                'startYear' => 2026,
            ], JSON_THROW_ON_ERROR)),
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
        ]);

        $first = $scraper->fetchById('1234567');
        $second = $scraper->fetchById('2345678');

        $this->assertIsArray($first);
        $this->assertFalse($second);
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_min_interval_active', $scraper->getLastFallbackFailureReason());
        $this->assertNull($scraper->getLastFetchSource());
    }

    public function test_fetch_by_id_skips_imdbapi_dev_when_cooldown_is_active_after_rate_limit(): void
    {
        config([
            'nntmux_api.imdbapi_dev_min_interval_seconds' => 0,
            'nntmux_api.imdbapi_dev_cooldown_seconds' => 300,
        ]);

        $scraper = $this->makeScraperWithResponses([
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
            new Response(429, ['Content-Type' => 'application/json; charset=UTF-8'], '{"code":8,"message":"RATE_LIMITED"}'),
            new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>'),
        ]);

        $first = $scraper->fetchById('1234567');
        $this->assertFalse($first);
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_rate_limited', $scraper->getLastFallbackFailureReason());

        $second = $scraper->fetchById('2345678');
        $this->assertFalse($second);
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_cooldown_active', $scraper->getLastFallbackFailureReason());
        $this->assertNull($scraper->getLastFetchSource());
    }

    public function test_search_parses_suggestion_json_results(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/search_inception.json')) ?: '{}'),
        ]);

        $results = $scraper->search('Inception');

        $this->assertNotEmpty($results);
        $this->assertSame('1375666', $results[0]['imdbid']);
        $this->assertSame('Inception', $results[0]['title']);
        $this->assertSame('2010', $results[0]['year']);
    }

    public function test_search_empty_returns_empty_array(): void
    {
        $scraper = new ImdbScraper;
        $this->assertSame([], $scraper->search(''));
    }

    /**
     * @param  array<int, Response>  $responses
     */
    private function makeScraperWithResponses(array $responses): ImdbScraper
    {
        $mock = new MockHandler($responses);
        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
        ]);

        return new ImdbScraper($client);
    }
}
