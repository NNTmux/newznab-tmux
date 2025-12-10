<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SteamApp;
use App\Services\SteamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for SteamService - tests requiring database and HTTP mocking.
 */
class SteamServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SteamService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SteamService();
        Cache::flush();
    }

    // ==========================================
    // Search Tests with Mocked Database
    // ==========================================

    public function test_search_finds_exact_match_in_database(): void
    {
        // Insert test data
        SteamApp::query()->insert([
            ['appid' => 292030, 'name' => 'The Witcher 3: Wild Hunt'],
            ['appid' => 1091500, 'name' => 'Cyberpunk 2077'],
            ['appid' => 1245620, 'name' => 'ELDEN RING'],
        ]);

        // Search should find exact match
        $result = $this->service->search('Cyberpunk 2077');

        $this->assertNotNull($result);
        $this->assertSame(1091500, $result);
    }

    public function test_search_finds_match_from_scene_release_name(): void
    {
        SteamApp::query()->insert([
            ['appid' => 292030, 'name' => 'The Witcher 3: Wild Hunt'],
        ]);

        $result = $this->service->search('The.Witcher.3.Wild.Hunt-CODEX');

        $this->assertNotNull($result);
        $this->assertSame(292030, $result);
    }

    public function test_search_returns_null_for_no_match(): void
    {
        SteamApp::query()->insert([
            ['appid' => 292030, 'name' => 'The Witcher 3: Wild Hunt'],
        ]);

        $result = $this->service->search('NonExistent Game Title XYZ');

        $this->assertNull($result);
    }

    public function test_search_caches_successful_result(): void
    {
        SteamApp::query()->insert([
            ['appid' => 1091500, 'name' => 'Cyberpunk 2077'],
        ]);

        // First search
        $result1 = $this->service->search('Cyberpunk 2077');
        $this->assertSame(1091500, $result1);

        // Delete from database
        SteamApp::query()->where('appid', 1091500)->delete();

        // Second search should return cached result
        $result2 = $this->service->search('Cyberpunk 2077');
        $this->assertSame(1091500, $result2);
    }

    public function test_search_caches_failed_lookup(): void
    {
        // Search for non-existent game
        $this->service->search('NonExistent Game 12345');

        // Add the game to database
        SteamApp::query()->insert([
            ['appid' => 99999, 'name' => 'NonExistent Game 12345'],
        ]);

        // Search should still return null due to cache
        $result = $this->service->search('NonExistent Game 12345');
        $this->assertNull($result);
    }

    public function test_search_multiple_returns_collection(): void
    {
        SteamApp::query()->insert([
            ['appid' => 292030, 'name' => 'The Witcher 3: Wild Hunt'],
            ['appid' => 20900, 'name' => 'The Witcher'],
            ['appid' => 20920, 'name' => 'The Witcher 2: Assassins of Kings'],
        ]);

        $results = $this->service->searchMultiple('Witcher', 10);

        $this->assertNotEmpty($results);
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    // ==========================================
    // API Tests with HTTP Mocking
    // ==========================================

    public function test_get_game_details_returns_formatted_data(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '1091500' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Cyberpunk 2077',
                        'steam_appid' => 1091500,
                        'short_description' => 'An open-world RPG set in Night City.',
                        'detailed_description' => 'Full description here...',
                        'about_the_game' => 'About the game...',
                        'header_image' => 'https://cdn.steam.com/header.jpg',
                        'background' => 'https://cdn.steam.com/background.jpg',
                        'publishers' => ['CD PROJEKT RED'],
                        'developers' => ['CD PROJEKT RED'],
                        'genres' => [
                            ['id' => '1', 'description' => 'Action'],
                            ['id' => '3', 'description' => 'RPG'],
                        ],
                        'categories' => [
                            ['id' => 2, 'description' => 'Single-player'],
                        ],
                        'release_date' => [
                            'coming_soon' => false,
                            'date' => 'Dec 10, 2020',
                        ],
                        'metacritic' => [
                            'score' => 86,
                            'url' => 'https://www.metacritic.com/game/cyberpunk-2077',
                        ],
                        'platforms' => [
                            'windows' => true,
                            'mac' => false,
                            'linux' => false,
                        ],
                        'price_overview' => [
                            'currency' => 'USD',
                            'initial' => 5999,
                            'final' => 2999,
                            'discount_percent' => 50,
                            'final_formatted' => '$29.99',
                        ],
                        'screenshots' => [
                            [
                                'id' => 0,
                                'path_thumbnail' => 'https://cdn.steam.com/ss_thumb.jpg',
                                'path_full' => 'https://cdn.steam.com/ss_full.jpg',
                            ],
                        ],
                        'movies' => [
                            [
                                'id' => 256123,
                                'name' => 'Launch Trailer',
                                'thumbnail' => 'https://cdn.steam.com/movie_thumb.jpg',
                                'mp4' => [
                                    '480' => 'https://cdn.steam.com/movie_480.mp4',
                                    'max' => 'https://cdn.steam.com/movie_max.mp4',
                                ],
                            ],
                        ],
                        'achievements' => [
                            'total' => 44,
                        ],
                        'recommendations' => [
                            'total' => 500000,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getGameDetails(1091500);

        $this->assertIsArray($result);
        $this->assertSame('Cyberpunk 2077', $result['title']);
        $this->assertSame(1091500, $result['steamid']);
        $this->assertSame('game', $result['type']);
        $this->assertSame('CD PROJEKT RED', $result['publisher']);
        $this->assertSame(['CD PROJEKT RED'], $result['developers']);
        $this->assertSame(86, $result['metacritic_score']);
        $this->assertStringContainsString('Action', $result['genres']);
        $this->assertContains('Windows', $result['platforms']);
        $this->assertNotEmpty($result['screenshots']);
        $this->assertNotEmpty($result['movies']);
        $this->assertSame(44, $result['achievements']);
    }

    public function test_get_game_details_returns_false_on_api_failure(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '99999' => [
                    'success' => false,
                ],
            ]),
        ]);

        $result = $this->service->getGameDetails(99999);

        $this->assertFalse($result);
    }

    public function test_get_game_details_caches_result(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '1091500' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Cyberpunk 2077',
                        'steam_appid' => 1091500,
                    ],
                ],
            ]),
        ]);

        // First call
        $result1 = $this->service->getGameDetails(1091500);
        $this->assertSame('Cyberpunk 2077', $result1['title']);

        // Second call should use cache (no HTTP request)
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '1091500' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Different Name',
                        'steam_appid' => 1091500,
                    ],
                ],
            ]),
        ]);

        $result2 = $this->service->getGameDetails(1091500);
        $this->assertSame('Cyberpunk 2077', $result2['title']); // Still cached
    }

    public function test_get_reviews_summary(): void
    {
        Http::fake([
            'store.steampowered.com/api/appreviews/*' => Http::response([
                'success' => 1,
                'query_summary' => [
                    'total_positive' => 400000,
                    'total_negative' => 100000,
                    'total_reviews' => 500000,
                    'review_score' => 8,
                    'review_score_desc' => 'Very Positive',
                ],
            ]),
        ]);

        $result = $this->service->getReviewsSummary(1091500);

        $this->assertIsArray($result);
        $this->assertSame(400000, $result['total_positive']);
        $this->assertSame(100000, $result['total_negative']);
        $this->assertSame(500000, $result['total_reviews']);
        $this->assertSame('Very Positive', $result['review_score_desc']);
    }

    public function test_get_full_app_list(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamApps/GetAppList/v2/*' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 10, 'name' => 'Counter-Strike'],
                        ['appid' => 20, 'name' => 'Team Fortress Classic'],
                        ['appid' => 30, 'name' => 'Day of Defeat'],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getFullAppList();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(10, $result[0]['appid']);
        $this->assertSame('Counter-Strike', $result[0]['name']);
    }

    // ==========================================
    // Populate Apps Table Tests
    // ==========================================

    public function test_populate_steam_apps_table(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamApps/GetAppList/v2/*' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 10, 'name' => 'Counter-Strike'],
                        ['appid' => 20, 'name' => 'Team Fortress Classic'],
                        ['appid' => 30, 'name' => 'Day of Defeat'],
                    ],
                ],
            ]),
        ]);

        $stats = $this->service->populateSteamAppsTable();

        $this->assertArrayHasKey('inserted', $stats);
        $this->assertArrayHasKey('skipped', $stats);
        $this->assertArrayHasKey('errors', $stats);
        $this->assertSame(3, $stats['inserted']);

        // Verify data in database
        $this->assertDatabaseHas('steam_apps', ['appid' => 10, 'name' => 'Counter-Strike']);
        $this->assertDatabaseHas('steam_apps', ['appid' => 20, 'name' => 'Team Fortress Classic']);
    }

    public function test_populate_steam_apps_table_skips_duplicates(): void
    {
        // Pre-insert some apps
        SteamApp::query()->insert([
            ['appid' => 10, 'name' => 'Counter-Strike'],
        ]);

        Http::fake([
            'api.steampowered.com/ISteamApps/GetAppList/v2/*' => Http::response([
                'applist' => [
                    'apps' => [
                        ['appid' => 10, 'name' => 'Counter-Strike'],
                        ['appid' => 20, 'name' => 'Team Fortress Classic'],
                    ],
                ],
            ]),
        ]);

        $stats = $this->service->populateSteamAppsTable();

        $this->assertSame(1, $stats['inserted']);
        $this->assertSame(1, $stats['skipped']);
    }

    // ==========================================
    // Price Handling Tests
    // ==========================================

    public function test_handles_free_game(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '570' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Dota 2',
                        'steam_appid' => 570,
                        'is_free' => true,
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getGameDetails(570);

        $this->assertIsArray($result);
        $this->assertNotNull($result['price']);
        $this->assertSame(0.0, $result['price']['final']);
        $this->assertSame('Free', $result['price']['final_formatted']);
    }

    public function test_handles_game_on_sale(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => Http::response([
                '1091500' => [
                    'success' => true,
                    'data' => [
                        'type' => 'game',
                        'name' => 'Cyberpunk 2077',
                        'steam_appid' => 1091500,
                        'price_overview' => [
                            'currency' => 'USD',
                            'initial' => 5999,
                            'final' => 2999,
                            'discount_percent' => 50,
                            'final_formatted' => '$29.99',
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->service->getGameDetails(1091500);

        $this->assertSame(59.99, $result['price']['initial']);
        $this->assertSame(29.99, $result['price']['final']);
        $this->assertSame(50, $result['price']['discount_percent']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function test_handles_empty_search_term(): void
    {
        $result = $this->service->search('');
        $this->assertNull($result);

        $result = $this->service->search('   ');
        $this->assertNull($result);
    }

    public function test_clean_title_handles_url_encoded_input(): void
    {
        $result = $this->service->cleanTitle('The%20Witcher%203');
        $this->assertSame('The Witcher 3', $result);
    }

    public function test_handles_api_timeout(): void
    {
        Http::fake([
            'store.steampowered.com/api/appdetails*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->getGameDetails(1091500);
        $this->assertFalse($result);
    }
}

