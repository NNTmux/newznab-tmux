<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Pdo\Sqlite;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication; // Boot Laravel application for tests.

    protected function setUp(): void
    {
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
     * Register a custom SQLite function on the default connection. Depending on the
     * PHP build, the registration method is PDO::sqliteCreateFunction (removed on
     * some 8.5 builds), Pdo\Sqlite::createFunction, or absent entirely because
     * PDO::connect() returned a plain PDO — in that case the connection's PDO is
     * replaced with a Pdo\Sqlite one, so call this before creating any schema on
     * an in-memory database.
     */
    protected function registerSqliteFunction(string $name, callable $callback, int $numArgs = -1): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        if (! method_exists($pdo, 'createFunction') && ! method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo = Sqlite::connect('sqlite:'.$connection->getDatabaseName());
            $connection->setPdo($pdo);
        }

        if (method_exists($pdo, 'createFunction')) {
            $pdo->createFunction($name, $callback, $numArgs);
        } else {
            $pdo->sqliteCreateFunction($name, $callback, $numArgs);
        }
    }
}
