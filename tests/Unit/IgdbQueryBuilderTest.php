<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\IGDB\Models\Game;
use App\Services\IGDBService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'igdb.base_url' => 'https://api.igdb.test/v4',
            'igdb.token_url' => 'https://id.twitch.test/oauth2/token',
            'igdb.credentials.client_id' => 'client-id',
            'igdb.credentials.client_secret' => 'client-secret',
            'igdb.cache_lifetime' => 0,
        ]);
    }

    public function test_game_query_builder_uses_local_client_and_hydrates_nested_results(): void
    {
        Http::fake([
            'https://id.twitch.test/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ]),
            'https://api.igdb.test/v4/games' => Http::response([
                [
                    'id' => 42,
                    'name' => 'Halo',
                    'first_release_date' => 978307200,
                    'cover' => [
                        'url' => '//images.igdb.com/igdb/image/upload/t_cover_big/test.jpg',
                    ],
                    'themes' => [
                        ['name' => 'Sci-Fi'],
                    ],
                    'platforms' => [6, 14],
                ],
            ]),
        ]);

        $results = Game::search('Halo')
            ->whereIn('platforms', [6, 14])
            ->with([
                'cover' => ['url'],
                'themes' => ['name'],
            ])
            ->orderByDesc('aggregated_rating_count')
            ->limit(10)
            ->get();

        $this->assertCount(1, $results);
        $game = $results->first();
        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame('Halo', $game->name);
        $this->assertSame('//images.igdb.com/igdb/image/upload/t_cover_big/test.jpg', $game->cover->url);
        $this->assertSame('Sci-Fi', $game->themes[0]->name);
        $this->assertSame('2001-01-01', $game->first_release_date->format('Y-m-d'));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.igdb.test/v4/games') {
                return false;
            }

            $body = $request->body();

            return $request->hasHeader('Client-ID', 'client-id')
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && str_contains($body, 'fields *,cover.url,themes.name;')
                && str_contains($body, 'search "Halo";')
                && str_contains($body, 'where platforms = (6,14);')
                && str_contains($body, 'sort aggregated_rating_count desc;')
                && str_contains($body, 'limit 10;');
        });
    }

    public function test_igdb_service_reads_the_local_igdb_configuration(): void
    {
        $service = new IGDBService;

        $this->assertTrue($service->isConfigured());

        config([
            'igdb.credentials.client_id' => '',
        ]);

        $this->assertFalse($service->isConfigured());
    }

    public function test_igdb_service_can_build_console_data_using_platform_hint_matching(): void
    {
        Http::fake([
            'https://id.twitch.test/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ]),
            'https://api.igdb.test/v4/games' => Http::response([
                [
                    'id' => 42,
                    'name' => 'Halo',
                    'summary' => 'Sci-fi shooter',
                    'aggregated_rating' => 94.2,
                    'first_release_date' => 1005782400,
                    'cover' => [
                        'image_id' => 'halo-cover',
                    ],
                    'themes' => [
                        ['name' => 'Action'],
                    ],
                    'platforms' => [
                        ['name' => 'Xbox 360', 'abbreviation' => 'X360'],
                        ['name' => 'PC (Microsoft Windows)', 'abbreviation' => 'PC'],
                    ],
                ],
            ]),
        ]);

        $service = new IGDBService;
        $game = $service->searchConsole('Halo', 'X360');

        $this->assertNotNull($game);

        $consoleData = $service->buildConsoleData($game, 'Xbox 360');

        $this->assertSame('42', $consoleData['asin']);
        $this->assertSame('Xbox 360', $consoleData['platform']);
        $this->assertSame('Action', $consoleData['consolegenre']);
        $this->assertSame('94%', $consoleData['esrb']);
        $this->assertSame('https://images.igdb.com/igdb/image/upload/t_cover_big/halo-cover.jpg', $consoleData['coverurl']);
        $this->assertSame('2001-11-15', $consoleData['releasedate']);
    }
}
