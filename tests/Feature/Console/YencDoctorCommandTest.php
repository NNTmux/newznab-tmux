<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class YencDoctorCommandTest extends TestCase
{
    public function test_invalid_decoder_mode_reports_configuration_failure(): void
    {
        config(['yenc.decoder' => 'typo']);

        $this->assertSame(1, Artisan::call('nntmux:yenc-doctor', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('YENC_DECODER must be auto, php or native.', $report['configuration_error']);
        $this->assertSame('unavailable', $report['active_decoder']);
    }

    public function test_php_mode_succeeds_and_reports_php_decoder(): void
    {
        config(['yenc.decoder' => 'php']);

        $this->artisan('nntmux:yenc-doctor')->assertExitCode(0);

        Artisan::call('nntmux:yenc-doctor', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('php', $report['decoder_mode']);
        $this->assertSame('PHP', $report['active_decoder']);
        $this->assertArrayHasKey('ffi_extension', $report);
    }

    public function test_native_mode_reports_failure_when_library_is_missing(): void
    {
        config(['yenc.decoder' => 'native', 'yenc.native_library' => '/nonexistent/librapidyenc.so']);

        $this->artisan('nntmux:yenc-doctor')
            ->expectsOutput('YENC_DECODER=native but the RapidYenc library is unusable.')
            ->assertExitCode(1);

        $this->assertSame(1, Artisan::call('nntmux:yenc-doctor', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($report['native_available']);
        $this->assertSame('unavailable', $report['active_decoder']);
        $this->assertNotNull($report['native_error']);
    }

    public function test_auto_mode_warns_but_succeeds_when_library_is_missing(): void
    {
        config(['yenc.decoder' => 'auto', 'yenc.native_library' => '/nonexistent/librapidyenc.so']);

        $this->artisan('nntmux:yenc-doctor')->assertExitCode(0);

        Artisan::call('nntmux:yenc-doctor', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($report['native_available']);
        $this->assertSame('PHP', $report['active_decoder']);
        $this->assertNotNull($report['native_error']);
    }
}
