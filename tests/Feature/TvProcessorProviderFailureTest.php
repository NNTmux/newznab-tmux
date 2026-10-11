<?php

namespace Tests\Feature;

use App\Services\TvProcessor;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TvProcessorProviderFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY, guid VARCHAR(40), leftguid VARCHAR(1), groups_id INT, searchname VARCHAR(255),
            size BIGINT, categories_id INT, videos_id INT DEFAULT 0, tv_episodes_id INT DEFAULT 0,
            postdate DATETIME NULL, isrenamed INT DEFAULT 0
        )');
        DB::table('releases')->insert([
            'id' => 1, 'guid' => 'abc', 'leftguid' => 'a', 'groups_id' => 1, 'searchname' => 'Show.S01E01.720p',
            'size' => 5_000_000, 'categories_id' => 5030, 'videos_id' => 0, 'tv_episodes_id' => 0, 'postdate' => now(),
        ]);
    }

    public function test_a_provider_that_throws_does_not_abort_the_rest_of_the_pipeline(): void
    {
        Log::spy();
        $ran = new \ArrayObject;
        $recorder = static fn (string $name): object => new class($name, $ran)
        {
            public bool $echooutput = false;

            public function __construct(private string $name, private \ArrayObject $ran) {}

            public function processSite(mixed $groupId, mixed $guidChar, mixed $process): void
            {
                $this->ran->append($this->name);
            }
        };

        $processor = new TvProcessor(false);
        $providers = [
            ['name' => 'Broken', 'factory' => static function (): never {
                throw new ConnectException('cURL error 6: Could not resolve host: api4.thetvdb.com', new Request('POST', 'https://api4.thetvdb.com/v4/login'));
            }, 'status' => 0],
            ['name' => 'Next', 'factory' => static fn () => $recorder('Next'), 'status' => 0],
        ];
        $property = new \ReflectionProperty($processor, 'providers');
        $property->setValue($processor, $providers);

        $processor->process('', '', 1);

        $this->assertSame(['Next'], $ran->getArrayCopy());
        Log::shouldHaveReceived('warning')->once()->with('TV provider failed, continuing with the next one', \Mockery::on(
            static fn (array $context): bool => $context['provider'] === 'Broken'
                && str_contains($context['message'], 'Could not resolve host'),
        ));
    }

    public function test_a_provider_defect_that_is_not_an_outage_still_fails_the_worker(): void
    {
        $processor = new TvProcessor(false);
        $property = new \ReflectionProperty($processor, 'providers');
        $property->setValue($processor, [
            ['name' => 'Defective', 'factory' => static function (): never {
                throw new \TypeError('programming error');
            }, 'status' => 0],
        ]);

        $this->expectException(\TypeError::class);

        $processor->process('', '', 1);
    }
}
