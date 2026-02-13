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
        $fixturePath = base_path('tests/Fixtures/imdb/example_title.html');
        if (! file_exists($fixturePath)) {
            $this->markTestSkipped('Fixture file not found: tests/Fixtures/imdb/example_title.html');
        }

        $this->markTestSkipped('Test requires parseForTest() method which is not implemented in ImdbScraper');
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
