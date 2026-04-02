<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImdbScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ImdbScraperRealDataTest extends ImdbScraperTestCase
{
    public function test_fetch_by_id_validates_format(): void
    {
        $scraper = new ImdbScraper;

        $this->assertFalse($scraper->fetchById('1234'));
        $this->assertFalse($scraper->fetchById('123456789'));
        $this->assertFalse($scraper->fetchById('abc1234'));
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $scraper = new ImdbScraper;
        $this->assertSame([], $scraper->search(''));
        $this->assertSame([], $scraper->search('   '));
    }

    public function test_fetch_by_id_uses_dom_fallback_when_jsonld_is_missing(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta property="og:title" content="Fallback Movie - IMDb">
    <meta property="og:image" content="https://example.com/fallback.jpg">
</head>
<body>
    <h1>Fallback Movie</h1>
    <ul data-testid="hero-title-block__metadata"><li>2025</li></ul>
    <span data-testid="plot-xl">Fallback plot line.</span>
    <div data-testid="hero-rating-bar__aggregate-rating__score"><span>8.1/10</span></div>
    <div data-testid="genres"><a>Drama</a><a>Thriller</a></div>
    <li data-testid="title-pc-principal-credit"><span>Director</span><a>Fallback Director</a></li>
    <div data-testid="title-cast-item"><a data-testid="title-cast-item__actor">Fallback Actor</a></div>
    <li data-testid="title-details-languages"><a>English</a><a>German</a></li>
</body>
</html>
HTML;

        $scraper = $this->makeScraperWithResponses([
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html),
        ]);

        $data = $scraper->fetchById('7654321');

        $this->assertIsArray($data);
        $this->assertSame('Fallback Movie', $data['title']);
        $this->assertSame('2025', $data['year']);
        $this->assertSame('Fallback plot line.', $data['plot']);
        $this->assertSame('8.1', $data['rating']);
        $this->assertSame(['Drama', 'Thriller'], $data['genre']);
        $this->assertSame(['Fallback Director'], $data['director']);
        $this->assertSame(['Fallback Actor'], $data['actors']);
        $this->assertSame('English, German', $data['language']);
    }

    public function test_search_falls_back_to_html_results_when_suggestion_json_is_unavailable(): void
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<body>
    <section>
        <a href="/title/tt1375666/">Inception</a>
        <span>2010</span>
    </section>
    <section>
        <a href="/title/tt1790736/">Inception: The Cobol Job</a>
        <span>2010</span>
    </section>
</body>
</html>
HTML;

        $scraper = $this->makeScraperWithResponses([
            new Response(500, ['Content-Type' => 'application/json; charset=UTF-8'], '{}'),
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html),
        ]);

        $results = $scraper->search('Inception');

        $this->assertCount(2, $results);
        $this->assertSame('1375666', $results[0]['imdbid']);
        $this->assertSame('Inception', $results[0]['title']);
        $this->assertSame('2010', $results[0]['year']);
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
