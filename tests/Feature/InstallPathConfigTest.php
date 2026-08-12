<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\InstallNntmux;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Guards the config keys read by InstallNntmux::updatePaths(). Reading a key
 * from the wrong config file (e.g. nntmux_settings.tmp_unzip_path instead of
 * nntmux.tmp_unzip_path) returns null and surfaces only as a bogus
 * install-time warning; this test makes that class of bug fail in CI instead.
 */
class InstallPathConfigTest extends TestCase
{
    public function test_tmp_unzip_path_is_defined_in_nntmux_config(): void
    {
        $this->assertNonEmptyString(config('nntmux.tmp_unzip_path'), 'nntmux.tmp_unzip_path');
    }

    public function test_tmp_unrar_path_is_defined_in_nntmux_config(): void
    {
        $this->assertNonEmptyString(config('nntmux.tmp_unrar_path'), 'nntmux.tmp_unrar_path');
    }

    public function test_path_to_nzbs_is_defined_in_nntmux_settings_config(): void
    {
        $this->assertNonEmptyString(config('nntmux_settings.path_to_nzbs'), 'nntmux_settings.path_to_nzbs');
    }

    public function test_covers_path_is_defined_in_nntmux_settings_config(): void
    {
        $this->assertNonEmptyString(config('nntmux_settings.covers_path'), 'nntmux_settings.covers_path');
    }

    public function test_update_paths_reports_all_failures_not_just_the_first(): void
    {
        $writable = sys_get_temp_dir();
        config([
            'nntmux_settings.path_to_nzbs' => '',
            'nntmux.tmp_unzip_path' => '',
            'nntmux_settings.covers_path' => $writable,
            'nntmux.tmp_unrar_path' => $writable,
        ]);

        $command = $this->app->make(InstallNntmux::class);
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        $result = (new \ReflectionMethod($command, 'updatePaths'))->invoke($command);
        $output = $buffer->fetch();

        $this->assertFalse($result);
        $this->assertStringContainsString('nntmux_settings.path_to_nzbs', $output);
        $this->assertStringContainsString('nntmux.tmp_unzip_path', $output);
    }

    public function test_env_example_temp_paths_point_under_storage_not_resources_tmp(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('resources/tmp', $envExample, '.env.example must not reference the legacy resources/tmp location; nntmux:all env-merge propagates these values into real .env files.');
        $this->assertMatchesRegularExpression('#^TEMP_UNRAR_PATH=\'?/var/www/nntmux/storage/tmp/unrar/?\'?$#m', $envExample);
        $this->assertMatchesRegularExpression('#^TEMP_UNZIP_PATH=\'?/var/www/nntmux/storage/tmp/unzip/?\'?$#m', $envExample);
    }

    public function test_update_paths_creates_both_missing_temp_dirs_recursively(): void
    {
        $base = $this->makeTempPath('nntmux-install-test');
        $tmpRoot = $base.'/tmp';
        $writable = sys_get_temp_dir();
        config([
            'nntmux_settings.path_to_nzbs' => $writable,
            'nntmux_settings.covers_path' => $writable,
            'nntmux.tmp_unrar_path' => $tmpRoot.'/unrar/',
            'nntmux.tmp_unzip_path' => $tmpRoot.'/unzip/',
        ]);

        try {
            [$command] = $this->makeCommandWithBufferedOutput();

            $result = (new \ReflectionMethod($command, 'updatePaths'))->invoke($command);

            $this->assertTrue($result);
            $this->assertDirectoryExists($tmpRoot.'/unrar/');
            $this->assertDirectoryExists($tmpRoot.'/unzip/');
        } finally {
            File::deleteDirectory($base);
        }
    }

    public function test_ensure_temp_directory_creates_nested_missing_path(): void
    {
        $base = $this->makeTempPath('nntmux-install-test');
        $tmpRoot = $base.'/tmp';
        $path = $tmpRoot.'/unrar/';

        try {
            [$command] = $this->makeCommandWithBufferedOutput();

            $result = (new \ReflectionMethod($command, 'ensureTempDirectory'))->invoke($command, 'Unrar path', $path);

            $this->assertTrue($result);
            $this->assertDirectoryExists($path);
        } finally {
            File::deleteDirectory($base);
        }
    }

    public function test_ensure_temp_directory_warns_and_returns_false_when_path_is_uncreatable(): void
    {
        $base = $this->makeTempDirectory('nntmux-install-test');
        File::put($base.'/blocker', 'not a directory');
        $path = $base.'/blocker/unrar/';

        try {
            [$command, $buffer] = $this->makeCommandWithBufferedOutput();

            $result = (new \ReflectionMethod($command, 'ensureTempDirectory'))->invoke($command, 'Unrar path', $path);
            $output = $buffer->fetch();

            $this->assertFalse($result);
            $this->assertMatchesRegularExpression('#Unable to create Unrar path \S+: .+#', $output, 'The warning must include the underlying filesystem error, not just a generic message.');
        } finally {
            File::deleteDirectory($base);
        }
    }

    /**
     * @return array{0: InstallNntmux, 1: BufferedOutput}
     */
    private function makeCommandWithBufferedOutput(): array
    {
        $command = $this->app->make(InstallNntmux::class);
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        return [$command, $buffer];
    }

    private function assertNonEmptyString(mixed $value, string $key): void
    {
        $this->assertIsString($value, "config('{$key}') must be a string; the installer path check reads this key.");
        $this->assertNotSame('', $value, "config('{$key}') must not be empty; the installer path check reads this key.");
    }
}
