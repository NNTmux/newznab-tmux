<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication(): Application
    {
        $this->originalEnvironment = [
            'LOG_CHANNEL' => getenv('LOG_CHANNEL'),
            'LOG_STACK' => getenv('LOG_STACK'),
        ];

        $this->setEnvironmentValue('LOG_CHANNEL', null);
        $this->setEnvironmentValue(
            'LOG_STACK',
            $this->name() === 'test_stack_parses_comma_separated_channels' ? 'single,daily' : null,
        );

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_default_stack_can_write_without_resolving_flare(): void
    {
        $this->assertSame('stack', config('logging.default'));
        $this->assertSame(['single'], config('logging.channels.stack.channels'));

        // Deliberately a real file: this test guards that the production stack
        // channel can actually write, which a Log fake would not exercise. The
        // path is unique and outside storage/logs so no permission bit or
        // concurrent run can affect it.
        $logPath = $this->makeTempPath('nntmux-production-safe-logging', '.log');
        $message = 'Production-safe logging configuration test';
        config(['logging.channels.single.path' => $logPath]);

        try {
            Log::debug($message);

            $this->assertFileExists($logPath);
            $contents = file_get_contents($logPath);
            $this->assertIsString($contents);
            $this->assertStringContainsString($message, $contents);
        } finally {
            @unlink($logPath);
        }

        $this->assertNotContains('flare', config('logging.channels.stack.channels'));
    }

    public function test_stack_parses_comma_separated_channels(): void
    {
        $this->assertSame(['single', 'daily'], config('logging.channels.stack.channels'));
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
