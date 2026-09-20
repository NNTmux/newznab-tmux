<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedisConnectionConfigurationTest extends TestCase
{
    #[Test]
    public function redis_connections_have_separate_database_and_optional_cache_endpoint_keys(): void
    {
        $databaseConfig = file_get_contents(__DIR__.'/../../config/database.php');
        $environmentExample = file_get_contents(__DIR__.'/../../.env.example');

        self::assertIsString($databaseConfig);
        self::assertStringContainsString("'database' => env('REDIS_DB', 0)", $databaseConfig);
        self::assertStringContainsString("'database' => env('REDIS_CACHE_DB', 1)", $databaseConfig);
        self::assertStringContainsString("'database' => env('REDIS_SESSION_DB', 2)", $databaseConfig);
        self::assertStringContainsString("'host' => env('REDIS_CACHE_HOST'", $databaseConfig);
        self::assertStringContainsString("'port' => env('REDIS_CACHE_PORT'", $databaseConfig);
        self::assertIsString($environmentExample);
        self::assertStringContainsString('REDIS_DB=0', $environmentExample);
        self::assertStringContainsString('REDIS_CACHE_DB=1', $environmentExample);
        self::assertStringContainsString('REDIS_SESSION_DB=2', $environmentExample);
        self::assertStringContainsString('SESSION_CONNECTION=session', $environmentExample);
    }
}
