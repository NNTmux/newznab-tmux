<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ConsoleService;
use App\Services\IGDB\Models\Game;
use App\Services\IGDBService;
use App\Services\ReleaseImageService;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class ConsoleServiceIgdbDelegationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_fetch_igdb_properties_delegates_to_igdb_service_and_maps_console_genre_id(): void
    {
        $igdbService = Mockery::mock(IGDBService::class);
        $game = new Game(['id' => 42, 'name' => 'Halo']);

        $igdbService->shouldReceive('isConfigured')->once()->andReturn(true);
        $igdbService->shouldReceive('searchConsole')->once()->with('Halo', 'Xbox 360')->andReturn($game);
        $igdbService->shouldReceive('buildConsoleData')->once()->with($game, 'Xbox 360')->andReturn([
            'title' => 'Halo',
            'asin' => '42',
            'review' => 'Sci-fi shooter',
            'coverurl' => 'https://images.example.test/halo.jpg',
            'releasedate' => '2001-11-15',
            'esrb' => '94%',
            'url' => 'https://www.igdb.com/games/halo',
            'publisher' => 'Microsoft',
            'platform' => 'Xbox 360',
            'consolegenre' => 'Action',
            'salesrank' => '',
        ]);

        /** @var ConsoleServiceTestDouble $service */
        $service = (new ReflectionClass(ConsoleServiceTestDouble::class))->newInstanceWithoutConstructor();

        $service->initialize($igdbService);

        $result = $service->fetchIGDBProperties('Halo', 'X360');

        $this->assertIsArray($result);
        $this->assertSame('Xbox 360', $result['platform']);
        $this->assertSame('Action', $result['consolegenre']);
        $this->assertSame(7, $result['consolegenreid']);
    }

    public function test_parse_title_no_longer_returns_legacy_browse_node(): void
    {
        /** @var ConsoleServiceTestDouble $service */
        $service = (new ReflectionClass(ConsoleServiceTestDouble::class))->newInstanceWithoutConstructor();

        $result = $service->parseTitle('Halo.3.X360-PROPER');

        $this->assertIsArray($result);
        $this->assertSame('Halo 3', $result['title']);
        $this->assertSame('X360', $result['platform']);
        $this->assertArrayNotHasKey('node', $result);
    }
}

class ConsoleServiceTestDouble extends ConsoleService
{
    public function initialize(IGDBService $igdbService): void
    {
        $this->echoOutput = false;
        $this->gameQty = 0;
        $this->lookupThrottleMs = 0;
        $this->imgSavePath = '';
        $this->renamed = false;
        $this->failCache = [];
        $this->igdbService = $igdbService;
        $this->imageService = new ReleaseImageService;
    }

    protected function loadGenres(): array
    {
        return [7 => 'action'];
    }
}
