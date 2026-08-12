<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Pdo\Sqlite;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication; // Boot Laravel application for tests.

    /**
     * Environment keys guarded against leaking between test classes: the phpunit.xml
     * values every test depends on and that a class booting its own application is
     * most likely to rewire.
     */
    private const GUARDED_DATABASE_ENV_KEYS = ['DB_CONNECTION', 'DB_DATABASE', 'LOG_CHANNEL'];

    /**
     * Set to true by tests that deliberately leave DB_CONNECTION/DB_DATABASE
     * pointing somewhere other than the phpunit.xml values when they finish.
     */
    protected bool $allowsConnectionSwap = false;

    /**
     * Temporary filesystem paths handed out by makeTempPath(), removed on teardown.
     *
     * @var list<string>
     */
    private array $temporaryPaths = [];

    /**
     * The phpunit.xml database environment, captured before the first test runs.
     *
     * @var array<string, string|false>|null
     */
    private static ?array $databaseEnvironmentBaseline = null;

    private static ?string $previousTestName = null;

    private static bool $previousTestAllowedSwap = false;

    protected function setUp(): void
    {
        $this->guardAgainstLeakedDatabaseEnvironment();

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

    protected function tearDown(): void
    {
        self::$previousTestName = static::class.'::'.$this->name();
        self::$previousTestAllowedSwap = $this->allowsConnectionSwap;

        $this->removeTemporaryPaths();

        parent::tearDown();
    }

    /**
     * Build a unique path under the system temp directory and register it for
     * recursive removal on teardown. Nothing is created on disk; use it for the
     * file or directory a test is about to create, so concurrent runs and stale
     * leftovers from a crashed run can never collide.
     */
    protected function makeTempPath(string $prefix = 'nntmux-test', string $suffix = ''): string
    {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$prefix.'-'.bin2hex(random_bytes(8)).$suffix;

        $this->temporaryPaths[] = $path;

        return $path;
    }

    /**
     * Same as makeTempPath(), but the directory is created before it is returned.
     */
    protected function makeTempDirectory(string $prefix = 'nntmux-test', int $mode = 0775): string
    {
        $path = $this->makeTempPath($prefix);

        if (! is_dir($path) && ! mkdir($path, $mode, true) && ! is_dir($path)) {
            throw new \RuntimeException('Unable to create temporary directory: '.$path);
        }

        return $path;
    }

    private function removeTemporaryPaths(): void
    {
        $filesystem = new Filesystem;

        foreach ($this->temporaryPaths as $path) {
            if (is_dir($path)) {
                $filesystem->deleteDirectory($path);

                continue;
            }

            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }

    /**
     * A test class that rewires DB_CONNECTION/DB_DATABASE without restoring them
     * silently repoints every later class at its own database — the failure mode
     * behind the 2026-08-12 CI incident. The check runs before the application is
     * booted, so it sees whatever the previous test left behind and names it.
     *
     * The environment is the only channel that actually leaks: config(['database.…'])
     * swaps die with the application, which is rebuilt for every test, so a dozen
     * classes swap the default connection in setUp() without ever affecting another.
     * Running here rather than in tearDown() also keeps the check independent of
     * where a subclass calls parent::tearDown() relative to its own restore code;
     * the trade-off is that a leak from the very last test in a run is not reported.
     */
    private function guardAgainstLeakedDatabaseEnvironment(): void
    {
        $current = [];
        foreach (self::GUARDED_DATABASE_ENV_KEYS as $key) {
            $current[$key] = getenv($key);
        }

        if (self::$databaseEnvironmentBaseline === null) {
            self::$databaseEnvironmentBaseline = $current;

            return;
        }

        if (self::$previousTestAllowedSwap || $current === self::$databaseEnvironmentBaseline) {
            return;
        }

        $leaked = [];
        foreach (self::$databaseEnvironmentBaseline as $key => $expected) {
            if ($current[$key] !== $expected) {
                $leaked[] = sprintf(
                    '%s is %s, expected %s',
                    $key,
                    var_export($current[$key], true),
                    var_export($expected, true),
                );
            }
        }

        // Restore the baseline so a single offender fails once instead of
        // cascading into every test that runs after it.
        $this->restoreDatabaseEnvironmentBaseline();

        $this->fail(sprintf(
            'Database environment leaked from %s: %s. Restore the original values in tearDown(), '.
            'or set $allowsConnectionSwap = true on that test class.',
            self::$previousTestName ?? 'a previous test',
            implode('; ', $leaked),
        ));
    }

    private function restoreDatabaseEnvironmentBaseline(): void
    {
        foreach (self::$databaseEnvironmentBaseline ?? [] as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
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
