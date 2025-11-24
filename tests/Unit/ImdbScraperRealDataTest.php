<?php

namespace Tests\Unit;

use App\Services\ImdbScraper;
use Tests\TestCase;

class ImdbScraperRealDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_parse_shawshank_redemption_real_data(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/imdb/shawshank_redemption.html'));
        $scraper = new ImdbScraper;
        $data = $scraper->parseForTest($html, '0111161');

        // Assert core movie data
        $this->assertIsArray($data);
        $this->assertSame('The Shawshank Redemption', $data['title']);
        $this->assertStringContainsString('banker convicted', $data['plot']);
        $this->assertStringContainsString('friendship', $data['plot']);
        $this->assertSame('9.3', $data['rating']);
        $this->assertSame('1994', $data['year']);

        // Assert cover image
        $this->assertStringContainsString('media-amazon.com', $data['cover']);
        $this->assertStringContainsString('.jpg', $data['cover']);

        // Assert genre
        $this->assertStringContainsString('Drama', $data['genre']);

        // Assert actors (from JSON-LD)
        $this->assertIsArray($data['actors']);
        $this->assertCount(3, $data['actors']);
        $this->assertContains('Tim Robbins', $data['actors']);
        $this->assertContains('Morgan Freeman', $data['actors']);
        $this->assertContains('Bob Gunton', $data['actors']);

        // Assert director
        $this->assertIsArray($data['director']);
        $this->assertCount(1, $data['director']);
        $this->assertContains('Frank Darabont', $data['director']);

        // Assert tagline (from DOM)
        $this->assertSame('Fear can hold you prisoner. Hope can set you free.', $data['tagline']);

        // Assert language (from DOM)
        $this->assertSame('English', $data['language']);

        // Assert type
        $this->assertSame('Movie', $data['type']);
    }

    public function test_parse_handles_missing_optional_fields(): void
    {
        // Create minimal HTML with only required fields
        $minimalHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Test Movie - IMDb">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Movie",
        "name": "Test Movie",
        "datePublished": "2020-01-01"
    }
    </script>
</head>
<body></body>
</html>
HTML;

        $scraper = new ImdbScraper;
        $data = $scraper->parseForTest($minimalHtml, '1234567');

        $this->assertIsArray($data);
        $this->assertSame('Test Movie', $data['title']);
        $this->assertSame('2020', $data['year']);

        // Optional fields should be empty but not cause errors
        $this->assertSame('', $data['plot']);
        $this->assertSame('', $data['rating']);
        $this->assertSame('', $data['tagline']);
        $this->assertSame('', $data['language']);
        $this->assertIsArray($data['actors']);
        $this->assertEmpty($data['actors']);
    }

    public function test_fetch_by_id_validates_format(): void
    {
        $scraper = new ImdbScraper;

        // Too short
        $this->assertFalse($scraper->fetchById('1234'));

        // Too long
        $this->assertFalse($scraper->fetchById('123456789'));

        // Non-numeric
        $this->assertFalse($scraper->fetchById('abc1234'));

        // Valid format (will fail to fetch, but format is valid)
        // We can't test actual network call in unit tests
    }

    public function test_search_returns_empty_for_empty_query(): void
    {
        $scraper = new ImdbScraper;
        $this->assertSame([], $scraper->search(''));
        $this->assertSame([], $scraper->search('   '));
    }

    public function test_parse_with_og_meta_fallback(): void
    {
        // HTML where JSON-LD is missing but og:title exists
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="Fallback Movie Title - IMDb">
    <meta property="og:image" content="https://example.com/poster.jpg">
</head>
<body></body>
</html>
HTML;

        $scraper = new ImdbScraper;
        $data = $scraper->parseForTest($html, '9999999');

        $this->assertIsArray($data);
        $this->assertSame('Fallback Movie Title', $data['title']); // " - IMDb" should be stripped
        $this->assertSame('https://example.com/poster.jpg', $data['cover']);
    }

    public function test_parse_extracts_multiple_genres(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Movie",
        "name": "Action Drama",
        "genre": ["Action", "Drama", "Thriller"],
        "datePublished": "2020-01-01"
    }
    </script>
</head>
<body></body>
</html>
HTML;

        $scraper = new ImdbScraper;
        $data = $scraper->parseForTest($html, '1234567');

        $this->assertStringContainsString('Action', $data['genre']);
        $this->assertStringContainsString('Drama', $data['genre']);
        $this->assertStringContainsString('Thriller', $data['genre']);
    }
}
