<?php

namespace Tests\Integration;

use App\Services\ImdbScraper;
use Tests\TestCase;

/**
 * Integration tests for ImdbScraper with live IMDb site.
 *
 * These tests are marked as skipped by default to avoid:
 * - Network dependencies in CI/CD pipelines
 * - Rate limiting issues with IMDb
 * - Potential TOS violations
 *
 * To run these tests locally:
 * php artisan test --filter ImdbScraperIntegrationTest
 *
 * Or uncomment the skip annotations below.
 */
class ImdbScraperIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Integration tests disabled by default. Remove this line to run live tests.');
    }

    /**
     * Test fetching real data from IMDb for The Shawshank Redemption.
     *
     * @group integration
     * @group slow
     */
    public function test_fetch_shawshank_redemption_from_live_site(): void
    {
        $scraper = new ImdbScraper;
        $data = $scraper->fetchById('0111161');

        $this->assertIsArray($data);
        $this->assertSame('The Shawshank Redemption', $data['title']);
        $this->assertNotEmpty($data['plot']);
        $this->assertGreaterThan(9.0, (float) $data['rating']);
        $this->assertSame('1994', $data['year']);
        $this->assertNotEmpty($data['cover']);
        $this->assertStringContainsString('Drama', $data['genre']);
        $this->assertContains('Tim Robbins', $data['actors']);
        $this->assertContains('Morgan Freeman', $data['actors']);
        $this->assertContains('Frank Darabont', $data['director']);
    }

    /**
     * Test searching for movies on live IMDb site.
     *
     * @group integration
     * @group slow
     */
    public function test_search_inception_on_live_site(): void
    {
        $scraper = new ImdbScraper;
        $results = $scraper->search('Inception');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Find the 2010 Inception movie in results
        $found = false;
        foreach ($results as $result) {
            if ($result['title'] === 'Inception' && $result['year'] === '2010') {
                $found = true;
                $this->assertSame('1375666', $result['imdbid']);
                break;
            }
        }

        $this->assertTrue($found, 'Inception (2010) should be found in search results');
    }

    /**
     * Test that invalid IMDb IDs return false.
     *
     * @group integration
     * @group slow
     */
    public function test_fetch_invalid_id_returns_false(): void
    {
        $scraper = new ImdbScraper;
        $data = $scraper->fetchById('9999999');

        $this->assertFalse($data);
    }

    /**
     * Test caching works correctly on live requests.
     *
     * @group integration
     * @group slow
     */
    public function test_caching_reduces_network_calls(): void
    {
        $scraper = new ImdbScraper;

        // First call - should hit the network
        $start1 = microtime(true);
        $data1 = $scraper->fetchById('0111161');
        $time1 = microtime(true) - $start1;

        $this->assertIsArray($data1);

        // Second call - should be cached (much faster)
        $start2 = microtime(true);
        $data2 = $scraper->fetchById('0111161');
        $time2 = microtime(true) - $start2;

        $this->assertIsArray($data2);
        $this->assertSame($data1['title'], $data2['title']);

        // Cached call should be at least 10x faster
        $this->assertLessThan($time1 / 10, $time2, 'Cached call should be significantly faster');
    }
}
