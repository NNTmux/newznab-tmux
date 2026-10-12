<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Laravel\Pulse\Pulse;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class ProcessingPulseRecordingTest extends TestCase
{
    /** @return iterable<string, array{string|list<string>, bool}> */
    public static function commands(): iterable
    {
        foreach (['tmux:monitor', 'multiprocessing:binaries', 'postprocess:guid', 'update:postprocess',
            'releases:verify-candidates', 'binaries:part-repair', 'backfill:group', 'group:update-all', 'predb:import-feed'] as $command) {
            yield $command => [$command, false];
        }
        yield 'separated environment option' => [['--env', 'production', 'tmux:monitor'], false];
        yield 'inline environment option' => [['--env=production', 'tmux:monitor'], false];
        yield 'queue worker environment option' => [['--env', 'production', 'queue:work'], true];
        foreach (['horizon', 'queue:work', 'schedule:work', 'pulse:check', 'monitoring:export-metrics', 'config:cache'] as $command) {
            yield $command => [$command, true];
        }
    }

    /** @param string|list<string> $command */
    #[DataProvider('commands')]
    public function test_processing_commands_skip_recorders_before_pulse_boots(string|array $command, bool $enabled): void
    {
        $argv = $_SERVER['argv'] ?? null;
        try {
            $_SERVER['argv'] = ['artisan', '--no-interaction', ...(array) $command];
            $this->refreshApplication();

            $this->assertSame($enabled, config('pulse.enabled'));
            $this->assertSame($enabled, app(Pulse::class)->recorders()->isNotEmpty());
        } finally {
            $_SERVER['argv'] = $argv;
        }
    }

    public function test_web_requests_and_processing_opt_in_preserve_the_master_switch(): void
    {
        $argv = $_SERVER['argv'] ?? null;
        $console = new ReflectionProperty($this->app, 'isRunningInConsole');
        try {
            $_SERVER['argv'] = ['artisan', 'tmux:monitor'];
            config(['pulse.enabled' => true, 'pulse.processing_enabled' => false]);
            $console->setValue($this->app, false);
            (new AppServiceProvider($this->app))->register();
            $this->assertTrue(config('pulse.enabled'));

            $console->setValue($this->app, true);
            config(['pulse.processing_enabled' => true]);
            (new AppServiceProvider($this->app))->register();
            $this->assertTrue(config('pulse.enabled'));

            config(['pulse.enabled' => false]);
            (new AppServiceProvider($this->app))->register();
            $this->assertFalse(config('pulse.enabled'));
        } finally {
            $console->setValue($this->app, true);
            $_SERVER['argv'] = $argv;
        }
    }
}
