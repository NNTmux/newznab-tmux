<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImdbScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImdbScraperTest extends ImdbScraperTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The imdbapi.dev fallback is off by default; most tests here cover it, so switch it on.
        config(['nntmux_api.imdbapi_dev_enabled' => true]);
    }

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
        ]);

        $first = $scraper->fetchById('1234567');
        $second = $scraper->fetchById('2345678');

        $this->assertIsArray($first);
        $this->assertFalse($second);
        $this->assertTrue($scraper->wasSkipped());
        $this->assertSame('waf_backoff', $scraper->getLastFailureReason());
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
        ]);

        $first = $scraper->fetchById('1234567');
        $this->assertFalse($first);
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertSame('fallback_rate_limited', $scraper->getLastFallbackFailureReason());

        $second = $scraper->fetchById('2345678');
        $this->assertFalse($second);
        $this->assertTrue($scraper->wasSkipped());
        $this->assertSame('waf_backoff', $scraper->getLastFailureReason());
        $this->assertSame('fallback_cooldown_active', $scraper->getLastFallbackFailureReason());
        $this->assertNull($scraper->getLastFetchSource());
    }

    public function test_search_parses_suggestion_json_results(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/search_inception.json')) ?: '{}'),
        ]);

        $results = $scraper->search('Inception');

        $this->assertCount(1, $results, 'Shorts and TV series are not movie candidates.');
        $this->assertSame('1375666', $results[0]['imdbid']);
        $this->assertSame('Inception', $results[0]['title']);
        $this->assertSame('2010', $results[0]['year']);
        $this->assertSame('movie', $results[0]['type']);
        $this->assertSame('https://m.media-amazon.com/images/M/MV5BMjAxMzY3NjcxNF5BMl5BanBnXkFtZTcwNTI5OTM0Mw@@.jpg', $results[0]['cover']);
    }

    public function test_search_transliterates_the_query_into_the_suggestion_slug(): void
    {
        [$client, , $history] = $this->makeClientWithHistory([
            new Response(200, ['Content-Type' => 'application/json'], '{"d":[]}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"d":[]}'),
        ]);
        $scraper = new ImdbScraper($client);

        $scraper->search('Amélie');
        $scraper->search("Spider-Man: Schindler's List");

        $this->assertSame('/suggestion/a/amelie.json', $history[0]['request']->getUri()->getPath());
        $this->assertSame('/suggestion/s/spider_man_schindlers_list.json', $history[1]['request']->getUri()->getPath());
    }

    public function test_search_does_not_load_the_find_page_when_suggestions_are_empty(): void
    {
        [$client, $mock] = $this->makeClient([
            new Response(200, ['Content-Type' => 'application/json'], '{"d":[]}'),
            new Response(200, ['Content-Type' => 'text/html'], file_get_contents(base_path('tests/Fixtures/imdb/find_results.html')) ?: ''),
        ]);

        $this->assertSame([], (new ImdbScraper($client))->search('Nothing Here'));
        $this->assertCount(1, $mock);
    }

    public function test_search_find_page_fallback_reads_each_result_year_from_its_own_row(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new Response(503, ['Content-Type' => 'application/json'], ''),
            new Response(200, ['Content-Type' => 'text/html'], file_get_contents(base_path('tests/Fixtures/imdb/find_results.html')) ?: ''),
        ]);

        $results = $scraper->search('Life Is Beautiful');

        $this->assertSame(['0118799', '0123456', '7654321'], array_column($results, 'imdbid'));
        $this->assertSame(['1997', '', '2019'], array_column($results, 'year'));
    }

    public function test_failed_search_is_only_cached_briefly(): void
    {
        $scraper = $this->makeScraperWithResponses([
            new ConnectException('timeout', new Request('GET', 'https://v2.sg.media-imdb.com/')),
            new Response(503, ['Content-Type' => 'text/html'], ''),
        ]);

        $this->assertSame([], $scraper->search('Inception'));
        $this->assertTrue(Cache::has('imdb_search_'.md5('inception')));

        $this->travel(31)->minutes();
        $this->assertFalse(Cache::has('imdb_search_'.md5('inception')));
    }

    public function test_disabled_scraper_and_fallback_make_no_requests(): void
    {
        config([
            'nntmux_api.imdb_scraper_enabled' => false,
            'nntmux_api.imdbapi_dev_enabled' => false,
        ]);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/search_inception.json')) ?: '{}'),
        ]);
        $scraper = new ImdbScraper(new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]));

        $this->assertFalse($scraper->isEnabled());
        $this->assertFalse($scraper->fetchById('1234567'));
        $this->assertTrue($scraper->wasSkipped());
        $this->assertSame('scraper_disabled', $scraper->getLastFailureReason());
        $this->assertSame([], $scraper->search('Inception'));
        $this->assertCount(1, $mock);
    }

    public function test_disabled_scraper_still_uses_imdbapi_dev(): void
    {
        config([
            'nntmux_api.imdb_scraper_enabled' => false,
            'nntmux_api.imdbapi_dev_min_interval_seconds' => 0,
        ]);
        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'application/json; charset=UTF-8'], json_encode([
                'id' => 'tt1234567',
                'type' => 'movie',
                'primaryTitle' => 'API Dev Movie',
                'startYear' => 2026,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $data = $scraper->fetchById('1234567');

        $this->assertIsArray($data);
        $this->assertSame('API Dev Movie', $data['title']);
        $this->assertSame('imdbapi_dev', $scraper->getLastFetchSource());
    }

    public function test_waf_block_pauses_scraping_for_all_titles_and_logs_once(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        Log::spy();
        [$client, $mock] = $this->makeClient([
            $this->wafResponse(),
            $this->titleResponse(),
        ]);

        $first = new ImdbScraper($client);
        $this->assertFalse($first->fetchById('1234567'));
        $this->assertTrue($first->wasBlockedByWaf());
        $this->assertFalse($first->wasSkipped());
        $this->assertTrue($first->isWafBackoffActive());

        foreach (['2345678', '3456789'] as $id) {
            $other = new ImdbScraper($client);
            $this->assertFalse($other->fetchById($id));
            $this->assertTrue($other->wasSkipped());
            $this->assertSame('waf_backoff', $other->getLastFailureReason());
        }

        $this->travel(59)->minutes();
        $this->assertFalse((new ImdbScraper($client))->fetchById('4567890'));

        $this->assertCount(1, $mock);
        Log::shouldHaveReceived('warning')
            ->with('IMDb scraping blocked by WAF, pausing IMDb scraping for 60 minutes')
            ->once();
    }

    public function test_back_off_interval_is_configurable(): void
    {
        config([
            'nntmux_api.imdbapi_dev_enabled' => false,
            'nntmux_api.imdb_scraper_block_backoff_minutes' => 5,
        ]);
        [$client, $mock] = $this->makeClient([$this->wafResponse(), $this->titleResponse()]);

        $this->assertFalse((new ImdbScraper($client))->fetchById('1234567'));
        $this->travel(6)->minutes();

        $this->assertIsArray((new ImdbScraper($client))->fetchById('2345678'));
        $this->assertCount(0, $mock);
    }

    public function test_only_one_probe_runs_after_back_off_expires(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        [$client, $mock] = $this->makeClient([$this->wafResponse(), $this->titleResponse()]);

        $this->assertFalse((new ImdbScraper($client))->fetchById('1234567'));
        $this->travel(61)->minutes();

        Cache::put('imdb_scraper:waf_probe', true, 120);
        $waiting = new ImdbScraper($client);
        $this->assertFalse($waiting->fetchById('2345678'));
        $this->assertTrue($waiting->wasSkipped());
        $this->assertCount(1, $mock);

        Cache::forget('imdb_scraper:waf_probe');
        $this->assertIsArray((new ImdbScraper($client))->fetchById('3456789'));
        $this->assertCount(0, $mock);
    }

    public function test_blocked_probe_restarts_back_off_without_new_warning(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        Log::spy();
        [$client, $mock] = $this->makeClient([
            $this->wafResponse(),
            $this->wafResponse(),
            $this->titleResponse(),
        ]);

        $this->assertFalse((new ImdbScraper($client))->fetchById('1234567'));
        $this->travel(61)->minutes();

        $probe = new ImdbScraper($client);
        $this->assertFalse($probe->fetchById('2345678'));
        $this->assertTrue($probe->wasBlockedByWaf());
        $this->assertFalse($probe->wasSkipped());

        $this->travel(30)->minutes();
        $this->assertTrue((new ImdbScraper($client))->isWafBackoffActive());
        $skipped = new ImdbScraper($client);
        $this->assertFalse($skipped->fetchById('3456789'));
        $this->assertTrue($skipped->wasSkipped());

        $this->assertCount(1, $mock);
        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('info');
    }

    public function test_successful_probe_clears_back_off_and_logs_resume_once(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        Log::spy();
        [$client, $mock] = $this->makeClient([
            $this->wafResponse(),
            $this->titleResponse(),
            $this->titleResponse(),
        ]);

        $this->assertFalse((new ImdbScraper($client))->fetchById('1234567'));
        $this->travel(61)->minutes();

        $probe = new ImdbScraper($client);
        $this->assertIsArray($probe->fetchById('2345678'));
        $this->assertFalse($probe->isWafBackoffActive());
        $this->assertIsArray((new ImdbScraper($client))->fetchById('3456789'));

        $this->assertCount(0, $mock);
        Log::shouldHaveReceived('info')
            ->with('IMDb scraping no longer blocked by WAF, lookups resumed')
            ->once();
    }

    public function test_title_page_json_ld_with_escaped_quotes_is_parsed_with_alternate_titles(): void
    {
        $html = <<<'HTML'
<html><head><script type="application/ld+json">{"@type":"Movie","name":"Schindler&apos;s List","alternateName":"La lista di Schindler","description":"A &quot;list&quot; of names.","datePublished":"1994-03-03","genre":["Drama"]}</script></head><body></body></html>
HTML;
        $scraper = $this->makeScraperWithResponses([new Response(200, ['Content-Type' => 'text/html'], $html)]);

        $data = $scraper->fetchById('0108052');

        $this->assertIsArray($data);
        $this->assertSame("Schindler's List", $data['title']);
        $this->assertSame(["Schindler's List", 'La lista di Schindler'], $data['known_titles']);
        $this->assertSame('A "list" of names.', $data['plot']);
        $this->assertSame(['Drama'], $data['genre']);
    }

    public function test_forbidden_title_page_starts_the_waf_back_off(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        $scraper = $this->makeScraperWithResponses([new Response(403, ['Content-Type' => 'text/html'], '<html>Forbidden</html>')]);

        $this->assertFalse($scraper->fetchById('1234567'));
        $this->assertSame('waf_block', $scraper->getLastFailureReason());
        $this->assertTrue($scraper->isWafBackoffActive());
    }

    public function test_title_page_mentioning_captcha_is_not_treated_as_a_block(): void
    {
        $html = str_replace('</body>', '<script src="/static/captcha-helper.js"></script></body>', file_get_contents(base_path('tests/Fixtures/imdb/title_jsonld.html')) ?: '');
        $scraper = $this->makeScraperWithResponses([new Response(200, ['Content-Type' => 'text/html'], $html)]);

        $this->assertIsArray($scraper->fetchById('1234567'));
        $this->assertFalse($scraper->isWafBackoffActive());
    }

    public function test_missing_title_is_a_miss_without_a_fallback_request(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => true, 'nntmux_api.imdbapi_dev_min_interval_seconds' => 0]);
        [$client, $mock] = $this->makeClient([
            new Response(404, ['Content-Type' => 'text/html'], '<html>Not found</html>'),
            new Response(200, ['Content-Type' => 'application/json'], '{"primaryTitle":"Should Not Load"}'),
        ]);
        $scraper = new ImdbScraper($client);

        $this->assertFalse($scraper->fetchById('9999999'));
        $this->assertSame('not_found', $scraper->getLastFailureReason());
        $this->assertFalse($scraper->wasTransientFailure());
        $this->assertCount(1, $mock);

        $this->travel(5)->hours();
        $this->assertTrue(Cache::has('imdb_scrape_id_9999999'));
    }

    public function test_title_page_timeout_is_only_cached_briefly(): void
    {
        config(['nntmux_api.imdbapi_dev_enabled' => false]);
        $scraper = $this->makeScraperWithResponses([
            new ConnectException('timeout', new Request('GET', 'https://www.imdb.com/title/tt1234567/')),
        ]);

        $this->assertFalse($scraper->fetchById('1234567'));
        $this->assertSame('html_exception', $scraper->getLastFailureReason());
        $this->assertTrue($scraper->wasTransientFailure());

        $this->travel(31)->minutes();
        $this->assertFalse(Cache::has('imdb_scrape_id_1234567'));
    }

    public function test_short_ids_are_padded_to_seven_digits(): void
    {
        [$client, , $history] = $this->makeClientWithHistory([$this->titleResponse()]);

        $data = (new ImdbScraper($client))->fetchById('211915');

        $this->assertIsArray($data);
        $this->assertSame('0211915', $data['imdbid']);
        $this->assertSame('/title/tt0211915/', $history[0]['request']->getUri()->getPath());
    }

    public function test_imdbapi_dev_tv_movie_is_stored_as_a_movie_with_its_original_title(): void
    {
        config(['nntmux_api.imdb_scraper_enabled' => false, 'nntmux_api.imdbapi_dev_enabled' => true, 'nntmux_api.imdbapi_dev_min_interval_seconds' => 0]);
        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'type' => 'tvMovie',
                'primaryTitle' => 'The Day After',
                'originalTitle' => 'Der Tag danach',
                'startYear' => 1983,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $data = $scraper->fetchById('0085404');

        $this->assertIsArray($data);
        $this->assertSame('movie', $data['type']);
        $this->assertSame(['The Day After', 'Der Tag danach'], $data['known_titles']);
    }

    public function test_unreachable_imdbapi_dev_pauses_the_fallback_for_hours_and_warns_once(): void
    {
        config(['nntmux_api.imdb_scraper_enabled' => false, 'nntmux_api.imdbapi_dev_enabled' => true, 'nntmux_api.imdbapi_dev_min_interval_seconds' => 0]);
        Log::spy();
        [$client, $mock] = $this->makeClient([
            new ConnectException('Could not resolve host: api.imdbapi.dev', new Request('GET', 'https://api.imdbapi.dev/titles/tt1234567')),
        ]);

        $first = new ImdbScraper($client);
        $this->assertFalse($first->fetchById('1234567'));
        $this->assertSame('fallback_unreachable', $first->getLastFallbackFailureReason());

        $this->travel(2)->hours();
        $later = new ImdbScraper($client);
        $this->assertFalse($later->fetchById('2345678'));
        $this->assertTrue($later->wasSkipped());
        $this->assertSame('fallback_cooldown_active', $later->getLastFallbackFailureReason());

        $this->assertCount(0, $mock);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_search_empty_returns_empty_array(): void
    {
        $scraper = new ImdbScraper;
        $this->assertSame([], $scraper->search(''));
    }

    /**
     * @param  array<int, Response>  $responses
     * @return array{Client, MockHandler}
     */
    private function makeClient(array $responses): array
    {
        $mock = new MockHandler($responses);

        return [new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]), $mock];
    }

    /**
     * @param  array<int, Response|\Throwable>  $responses
     * @return array{Client, MockHandler, \ArrayObject<int, array{request: Request}>}
     */
    private function makeClientWithHistory(array $responses): array
    {
        $mock = new MockHandler($responses);
        $history = new \ArrayObject;
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return [new Client(['handler' => $stack, 'http_errors' => false]), $mock, $history];
    }

    private function wafResponse(): Response
    {
        return new Response(202, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><script>window.awsWafCookieDomainList=[];window.gokuProps={};</script></html>');
    }

    private function titleResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], file_get_contents(base_path('tests/Fixtures/imdb/title_jsonld.html')) ?: '');
    }

    /**
     * @param  array<int, Response|\Throwable>  $responses
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
