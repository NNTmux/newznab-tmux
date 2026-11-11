<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\Drivers\ManticoreSearchDriver;
use Illuminate\Support\Facades\Cache;
use Manticoresearch\Client;
use Manticoresearch\Nodes;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

final class ManticoreAvailabilityTest extends TestCase
{
    private const string SHARED_KEY = 'search:manticore:available';

    /** @var array<string, mixed> */
    private array $previousStatics = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        foreach (['availabilityCache', 'availabilityCacheTime'] as $name) {
            $this->previousStatics[$name] = (new ReflectionProperty(ManticoreSearchDriver::class, $name))->getValue();
        }
        $this->forgetProcessMemo();
    }

    protected function tearDown(): void
    {
        foreach ($this->previousStatics as $name => $value) {
            (new ReflectionProperty(ManticoreSearchDriver::class, $name))->setValue(null, $value);
        }

        parent::tearDown();
    }

    #[Test]
    public function a_confirmed_availability_is_reused_by_the_next_request_without_a_status_call(): void
    {
        $nodes = $this->createMock(Nodes::class);
        $nodes->expects($this->once())->method('status')->willReturn(['uptime' => 1]);
        $driver = $this->driverWithNodes($nodes);

        $firstRequest = $driver->isAvailable();
        // PHP-FPM starts every request with an empty process memo.
        $this->forgetProcessMemo();
        $secondRequest = $driver->isAvailable();

        $this->assertTrue($firstRequest);
        $this->assertTrue($secondRequest);
    }

    #[Test]
    public function an_outage_is_never_shared_between_requests(): void
    {
        $nodes = $this->createMock(Nodes::class);
        $nodes->expects($this->exactly(2))->method('status')->willThrowException(new RuntimeException('connection refused'));
        $driver = $this->driverWithNodes($nodes);

        $firstRequest = $driver->isAvailable();
        $this->forgetProcessMemo();
        $secondRequest = $driver->isAvailable();

        $this->assertFalse($firstRequest);
        $this->assertFalse($secondRequest);
        $this->assertNull(Cache::get(self::SHARED_KEY));
    }

    private function driverWithNodes(Nodes $nodes): ManticoreSearchDriver
    {
        $client = $this->createMock(Client::class);
        $client->method('nodes')->willReturn($nodes);
        $driver = new ManticoreSearchDriver(['host' => '127.0.0.1', 'port' => 9308, 'indexes' => ['releases' => 'releases_rt']]);
        $driver->manticoreSearch = $client;

        return $driver;
    }

    private function forgetProcessMemo(): void
    {
        foreach (['availabilityCache', 'availabilityCacheTime'] as $name) {
            (new ReflectionProperty(ManticoreSearchDriver::class, $name))->setValue(null, null);
        }
    }
}
