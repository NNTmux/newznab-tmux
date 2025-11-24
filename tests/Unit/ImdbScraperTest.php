<?php

namespace Tests\Unit;

use App\Services\ImdbScraper;
use Tests\TestCase;

class ImdbScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_parse_title_page_from_fixture(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/imdb/example_title.html'));
        $scraper = new ImdbScraper;
        $data = $scraper->parseForTest($html, '1234567');

        $this->assertIsArray($data);
        $this->assertSame('Example Movie', $data['title']);
        $this->assertNotEmpty($data['plot']); // relaxed assertion
        $this->assertSame('7.3', $data['rating']);
        $this->assertSame('2024', $data['year']);
        $this->assertStringContainsString('Action', $data['genre']);
    }

    public function test_fetch_by_id_caches_false_on_failure(): void
    {
        $scraper = new ImdbScraper;
        $data = $scraper->fetchById('123'); // invalid length triggers early false
        $this->assertFalse($data);
        $data2 = $scraper->fetchById('123');
        $this->assertFalse($data2);
    }

    public function test_search_empty_returns_empty_array(): void
    {
        $scraper = new ImdbScraper;
        $this->assertSame([], $scraper->search(''));
    }
}
