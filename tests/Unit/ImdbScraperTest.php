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
        ]);

        $data = $scraper->fetchById('1234567');

        $this->assertFalse($data);
        $this->assertTrue($scraper->wasBlockedByWaf());
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
