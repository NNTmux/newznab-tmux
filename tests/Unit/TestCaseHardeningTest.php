<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Depends;
use ReflectionClass;
use Tests\TestCase;

/**
 * Covers the shared harness added to Tests\TestCase: unique temp paths that are
 * cleaned up automatically, and the guard that stops one test class from leaving
 * DB_CONNECTION/DB_DATABASE pointing at its own database.
 */
class TestCaseHardeningTest extends TestCase
{
    private static ?string $directoryFromPreviousTest = null;

    public function test_make_temp_path_returns_a_unique_path_under_the_temp_directory(): void
    {
        $first = $this->makeTempPath('nntmux-harness', '.sqlite');
        $second = $this->makeTempPath('nntmux-harness', '.sqlite');

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), $first);
        $this->assertStringEndsWith('.sqlite', $first);
        $this->assertFileDoesNotExist($first, 'makeTempPath() must not touch the filesystem.');
    }

    public function test_make_temp_directory_creates_the_directory(): void
    {
        $directory = $this->makeTempDirectory('nntmux-harness');
        file_put_contents($directory.'/nested.txt', 'contents');

        $this->assertDirectoryExists($directory);

        self::$directoryFromPreviousTest = $directory;
    }

    #[Depends('test_make_temp_directory_creates_the_directory')]
    public function test_temporary_paths_are_removed_recursively_after_the_test(): void
    {
        $this->assertNotNull(self::$directoryFromPreviousTest);
        $this->assertDirectoryDoesNotExist(
            self::$directoryFromPreviousTest,
            'Temporary directories must be removed in tearDown(), including their contents.'
        );
    }

    public function test_guard_fails_and_repairs_the_environment_when_database_env_leaks(): void
    {
        $reflection = new ReflectionClass(TestCase::class);
        $previousName = $reflection->getStaticPropertyValue('previousTestName');
        $previousAllowed = $reflection->getStaticPropertyValue('previousTestAllowedSwap');

        $reflection->setStaticPropertyValue('previousTestName', 'Tests\Fake\LeakyTest::test_leaks');
        $reflection->setStaticPropertyValue('previousTestAllowedSwap', false);

        putenv('DB_DATABASE='.sys_get_temp_dir().'/leaked.sqlite');
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = sys_get_temp_dir().'/leaked.sqlite';

        try {
            $this->invokeGuard();
            $this->fail('The guard must reject a leaked database environment.');
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString('Tests\Fake\LeakyTest::test_leaks', $error->getMessage());
            $this->assertStringContainsString('DB_DATABASE', $error->getMessage());
        } finally {
            $reflection->setStaticPropertyValue('previousTestName', $previousName);
            $reflection->setStaticPropertyValue('previousTestAllowedSwap', $previousAllowed);
        }

        $baseline = (new ReflectionClass(TestCase::class))->getStaticPropertyValue('databaseEnvironmentBaseline');
        $this->assertSame(
            $baseline['DB_DATABASE'],
            getenv('DB_DATABASE'),
            'The guard must restore the baseline environment.'
        );
    }

    public function test_guard_stays_silent_when_the_previous_test_opted_out(): void
    {
        $reflection = new ReflectionClass(TestCase::class);
        $previousAllowed = $reflection->getStaticPropertyValue('previousTestAllowedSwap');
        $reflection->setStaticPropertyValue('previousTestAllowedSwap', true);

        putenv('DB_DATABASE='.sys_get_temp_dir().'/allowed.sqlite');

        try {
            $this->invokeGuard();
            $this->assertTrue(true);
        } finally {
            $reflection->setStaticPropertyValue('previousTestAllowedSwap', $previousAllowed);
            $baseline = $reflection->getStaticPropertyValue('databaseEnvironmentBaseline');
            putenv('DB_DATABASE='.$baseline['DB_DATABASE']);
            $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $baseline['DB_DATABASE'];
        }
    }

    private function invokeGuard(): void
    {
        $method = new \ReflectionMethod(TestCase::class, 'guardAgainstLeakedDatabaseEnvironment');
        $method->invoke($this);
    }
}
