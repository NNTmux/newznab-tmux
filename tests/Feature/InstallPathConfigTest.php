<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\InstallNntmux;
use Illuminate\Console\OutputStyle;
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

    private function assertNonEmptyString(mixed $value, string $key): void
    {
        $this->assertIsString($value, "config('{$key}') must be a string; the installer path check reads this key.");
        $this->assertNotSame('', $value, "config('{$key}') must not be empty; the installer path check reads this key.");
    }
}
