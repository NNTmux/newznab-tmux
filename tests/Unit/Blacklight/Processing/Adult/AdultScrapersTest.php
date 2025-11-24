<?php

namespace Tests\Unit\Blacklight\Processing\Adult;

use Blacklight\processing\adult\ADE;
use Blacklight\processing\adult\ADM;
use Blacklight\processing\adult\AEBN;
use Blacklight\processing\adult\Hotmovies;
use Blacklight\processing\adult\Popporn;
use Tests\TestCase;

/**
 * Adult Scrapers Real Data Tests
 *
 * These tests verify that scrapers can successfully retrieve and parse
 * real data from adult content sites. They help identify when sites
 * change their HTML structure and scraping needs to be updated.
 *
 * @group adult
 * @group scrapers
 * @group integration
 */
class AdultScrapersTest extends TestCase
{
    /**
     * Test data for known movies that should be findable
     */
    private array $testMovies = [
        'ade' => [
            ['title' => 'Pirates', 'year' => 2005],
            ['title' => 'The Masseuse', 'year' => 1990],
            ['title' => 'Debbie Does Dallas', 'year' => 1978],
        ],
        'adm' => [
            ['title' => 'Pirates', 'year' => 2005],
            ['title' => 'The Masseuse', 'year' => 1990],
        ],
        'aebn' => [
            ['title' => 'Pirates', 'year' => 2005],
            ['title' => 'The Masseuse', 'year' => 1990],
        ],
        'hotmovies' => [
            ['title' => 'Pirates', 'year' => 2005],
            ['title' => 'The Masseuse', 'year' => 1990],
        ],
        'popporn' => [
            ['title' => 'Pirates', 'year' => 2005],
            ['title' => 'The Masseuse', 'year' => 1990],
        ],
    ];

    /**
     * @test
     *
     * @group ade
     */
    public function ade_can_search_and_find_movies(): void
    {
        $scraper = new ADE;

        foreach ($this->testMovies['ade'] as $movie) {
            $result = $scraper->processSite($movie['title']);

            $this->assertTrue(
                $result,
                "ADE failed to find movie: {$movie['title']} ({$movie['year']})"
            );

            if ($result) {
                $data = $scraper->getAll();
                $this->assertNotEmpty($data, 'ADE returned empty data');
                $this->assertArrayHasKey('title', $data, 'ADE missing title');
                $this->assertArrayHasKey('directurl', $data, 'ADE missing directurl');

                echo "\n✓ ADE found: {$data['title']}\n";
                echo "  URL: {$data['directurl']}\n";
            }
        }
    }

    /**
     * @test
     *
     * @group ade
     */
    public function ade_extracts_complete_movie_data(): void
    {
        $scraper = new ADE;
        $result = $scraper->processSite('Pirates');

        if (! $result) {
            $this->markTestSkipped('ADE could not find test movie "Pirates"');
        }

        $data = $scraper->getAll();

        // Required fields
        $this->assertArrayHasKey('title', $data, 'Missing title');
        $this->assertArrayHasKey('directurl', $data, 'Missing directurl');

        // Optional but expected fields
        $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover'];
        $missingFields = [];

        foreach ($expectedFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            echo "\n⚠ ADE missing or empty fields: ".implode(', ', $missingFields)."\n";
        }

        // Log extracted data for inspection
        $this->logScrapedData('ADE', 'Pirates', $data);
    }

    /**
     * @test
     *
     * @group adm
     */
    public function adm_can_search_and_find_movies(): void
    {
        $scraper = new ADM;

        foreach ($this->testMovies['adm'] as $movie) {
            $result = $scraper->processSite($movie['title']);

            $this->assertTrue(
                $result,
                "ADM failed to find movie: {$movie['title']} ({$movie['year']})"
            );

            if ($result) {
                $data = $scraper->getAll();
                $this->assertNotEmpty($data, 'ADM returned empty data');
                $this->assertArrayHasKey('title', $data, 'ADM missing title');
                $this->assertArrayHasKey('directurl', $data, 'ADM missing directurl');

                echo "\n✓ ADM found: {$data['title']}\n";
                echo "  URL: {$data['directurl']}\n";
            }
        }
    }

    /**
     * @test
     *
     * @group adm
     */
    public function adm_extracts_complete_movie_data(): void
    {
        $scraper = new ADM;
        $result = $scraper->processSite('Pirates');

        if (! $result) {
            $this->markTestSkipped('ADM could not find test movie "Pirates"');
        }

        $data = $scraper->getAll();

        // Required fields
        $this->assertArrayHasKey('title', $data, 'Missing title');
        $this->assertArrayHasKey('directurl', $data, 'Missing directurl');

        // Optional but expected fields
        $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover', 'director'];
        $missingFields = [];

        foreach ($expectedFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            echo "\n⚠ ADM missing or empty fields: ".implode(', ', $missingFields)."\n";
        }

        // Log extracted data for inspection
        $this->logScrapedData('ADM', 'Pirates', $data);
    }

    /**
     * @test
     *
     * @group aebn
     */
    public function aebn_can_search_and_find_movies(): void
    {
        $scraper = new AEBN;

        foreach ($this->testMovies['aebn'] as $movie) {
            $result = $scraper->processSite($movie['title']);

            $this->assertTrue(
                $result,
                "AEBN failed to find movie: {$movie['title']} ({$movie['year']})"
            );

            if ($result) {
                $data = $scraper->getAll();
                $this->assertNotEmpty($data, 'AEBN returned empty data');
                $this->assertArrayHasKey('title', $data, 'AEBN missing title');
                $this->assertArrayHasKey('directurl', $data, 'AEBN missing directurl');

                echo "\n✓ AEBN found: {$data['title']}\n";
                echo "  URL: {$data['directurl']}\n";
            }
        }
    }

    /**
     * @test
     *
     * @group aebn
     */
    public function aebn_extracts_complete_movie_data(): void
    {
        $scraper = new AEBN;
        $result = $scraper->processSite('Pirates');

        if (! $result) {
            $this->markTestSkipped('AEBN could not find test movie "Pirates"');
        }

        $data = $scraper->getAll();

        // Required fields
        $this->assertArrayHasKey('title', $data, 'Missing title');
        $this->assertArrayHasKey('directurl', $data, 'Missing directurl');

        // Optional but expected fields
        $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover', 'director'];
        $missingFields = [];

        foreach ($expectedFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            echo "\n⚠ AEBN missing or empty fields: ".implode(', ', $missingFields)."\n";
        }

        // Log extracted data for inspection
        $this->logScrapedData('AEBN', 'Pirates', $data);
    }

    /**
     * @test
     *
     * @group hotmovies
     */
    public function hotmovies_can_search_and_find_movies(): void
    {
        $scraper = new Hotmovies;

        foreach ($this->testMovies['hotmovies'] as $movie) {
            $result = $scraper->processSite($movie['title']);

            $this->assertTrue(
                $result,
                "Hotmovies failed to find movie: {$movie['title']} ({$movie['year']})"
            );

            if ($result) {
                $data = $scraper->getAll();
                $this->assertNotEmpty($data, 'Hotmovies returned empty data');
                $this->assertArrayHasKey('title', $data, 'Hotmovies missing title');
                $this->assertArrayHasKey('directurl', $data, 'Hotmovies missing directurl');

                echo "\n✓ Hotmovies found: {$data['title']}\n";
                echo "  URL: {$data['directurl']}\n";
            }
        }
    }

    /**
     * @test
     *
     * @group hotmovies
     */
    public function hotmovies_extracts_complete_movie_data(): void
    {
        $scraper = new Hotmovies;
        $result = $scraper->processSite('Pirates');

        if (! $result) {
            $this->markTestSkipped('Hotmovies could not find test movie "Pirates"');
        }

        $data = $scraper->getAll();

        // Required fields
        $this->assertArrayHasKey('title', $data, 'Missing title');
        $this->assertArrayHasKey('directurl', $data, 'Missing directurl');

        // Optional but expected fields
        $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover', 'director'];
        $missingFields = [];

        foreach ($expectedFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            echo "\n⚠ Hotmovies missing or empty fields: ".implode(', ', $missingFields)."\n";
        }

        // Log extracted data for inspection
        $this->logScrapedData('Hotmovies', 'Pirates', $data);
    }

    /**
     * @test
     *
     * @group popporn
     */
    public function popporn_can_search_and_find_movies(): void
    {
        $scraper = new Popporn;

        foreach ($this->testMovies['popporn'] as $movie) {
            $result = $scraper->processSite($movie['title']);

            $this->assertTrue(
                $result,
                "Popporn failed to find movie: {$movie['title']} ({$movie['year']})"
            );

            if ($result) {
                $data = $scraper->getAll();
                $this->assertNotEmpty($data, 'Popporn returned empty data');
                $this->assertArrayHasKey('title', $data, 'Popporn missing title');
                $this->assertArrayHasKey('directurl', $data, 'Popporn missing directurl');

                echo "\n✓ Popporn found: {$data['title']}\n";
                echo "  URL: {$data['directurl']}\n";
            }
        }
    }

    /**
     * @test
     *
     * @group popporn
     */
    public function popporn_extracts_complete_movie_data(): void
    {
        $scraper = new Popporn;
        $result = $scraper->processSite('Pirates');

        if (! $result) {
            $this->markTestSkipped('Popporn could not find test movie "Pirates"');
        }

        $data = $scraper->getAll();

        // Required fields
        $this->assertArrayHasKey('title', $data, 'Missing title');
        $this->assertArrayHasKey('directurl', $data, 'Missing directurl');

        // Optional but expected fields
        $expectedFields = ['synopsis', 'cast', 'genres', 'boxcover', 'director'];
        $missingFields = [];

        foreach ($expectedFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            echo "\n⚠ Popporn missing or empty fields: ".implode(', ', $missingFields)."\n";
        }

        // Log extracted data for inspection
        $this->logScrapedData('Popporn', 'Pirates', $data);
    }

    /**
     * @test
     *
     * @group similarity
     */
    public function all_scrapers_have_configurable_similarity_threshold(): void
    {
        $scrapers = [
            'ADE' => new ADE,
            'ADM' => new ADM,
            'AEBN' => new AEBN,
            'Hotmovies' => new Hotmovies,
            'Popporn' => new Popporn,
        ];

        foreach ($scrapers as $name => $scraper) {
            $reflection = new \ReflectionClass($scraper);

            $this->assertTrue(
                $reflection->hasProperty('minimumSimilarity'),
                "{$name} missing minimumSimilarity property"
            );

            $property = $reflection->getProperty('minimumSimilarity');
            $property->setAccessible(true);
            $value = $property->getValue($scraper);

            $this->assertIsFloat($value, "{$name} minimumSimilarity should be float");
            $this->assertEquals(90.0, $value, "{$name} default threshold should be 90.0");

            echo "\n✓ {$name}: minimumSimilarity = {$value}\n";
        }
    }

    /**
     * Helper method to log scraped data for manual inspection
     */
    private function logScrapedData(string $scraper, string $movie, array $data): void
    {
        $logFile = storage_path('logs/scraper_test_'.date('Y-m-d').'.log');
        $logDir = dirname($logFile);

        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = sprintf(
            "[%s] %s - %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $scraper,
            $movie,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        echo "\n📝 Logged scraped data to: {$logFile}\n";
    }
}
