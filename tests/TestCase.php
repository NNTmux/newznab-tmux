<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Support\HtmlString;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication; // Boot Laravel application for tests.

    /**
     * Environment keys that select the test database; many tests point them at their own SQLite file.
     */
    private const array ISOLATED_ENVIRONMENT_KEYS = ['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL'];

    /**
     * Values from phpunit.xml, captured before any test could change them.
     *
     * @var array<string, array{getenv: string|false, env: mixed, server: mixed}>|null
     */
    private static ?array $baselineEnvironment = null;

    protected function setUp(): void
    {
        self::$baselineEnvironment ??= self::captureEnvironment();

        parent::setUp();

        if (is_file(public_path('build/manifest.json'))) {
            return;
        }

        $this->app->singleton(ViteManager::class, static fn (): ViteManager => new class extends ViteManager
        {
            /**
             * Avoid requiring compiled frontend assets while rendering Blade views in tests.
             */
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    /**
     * Restore the database environment so a test's temporary SQLite file never leaks into later tests.
     */
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach (self::$baselineEnvironment ?? [] as $key => $value) {
                if ($value['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv("{$key}={$value['getenv']}");
                }
                self::restoreSuperglobal($_ENV, $key, $value['env']);
                self::restoreSuperglobal($_SERVER, $key, $value['server']);
            }
        }
    }

    /**
     * @return array<string, array{getenv: string|false, env: mixed, server: mixed}>
     */
    private static function captureEnvironment(): array
    {
        $environment = [];
        foreach (self::ISOLATED_ENVIRONMENT_KEYS as $key) {
            $environment[$key] = [
                'getenv' => getenv($key),
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
            ];
        }

        return $environment;
    }

    /**
     * @param  array<string, mixed>  $superglobal
     */
    private static function restoreSuperglobal(array &$superglobal, string $key, mixed $value): void
    {
        if ($value === null) {
            unset($superglobal[$key]);
        } else {
            $superglobal[$key] = $value;
        }
    }
}
