<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mcp\ProcessRunner;
use App\Services\Mcp\ProjectPathGuard;
use App\Services\Mcp\RepositoryContextCollector;
use App\Services\Mcp\RuntimeProbeRunner;
use App\Services\Mcp\SensitiveValueRedactor;
use App\Services\Mcp\VerificationPlanner;
use App\Services\Mcp\VerificationRunner;
use App\Services\StatusProbes\Contracts\ServiceProbeInterface;
use App\Services\StatusProbes\DatabaseProbe;
use App\Services\StatusProbes\DiskProbe;
use App\Services\StatusProbes\NntpProbe;
use App\Services\StatusProbes\ProbeResult;
use Closure;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class McpVerificationServicesTest extends TestCase
{
    public function test_path_guard_allows_project_sources_and_rejects_excluded_or_sensitive_paths(): void
    {
        $guard = $this->app->make(ProjectPathGuard::class);

        $this->assertSame('app/Mcp/Tools/RunVerificationTool.php', $guard->normalize('app/Mcp/Tools/RunVerificationTool.php'));
        $this->assertSame('.env.example', $guard->normalize('.env.example'));
        $this->assertSame('composer.lock', $guard->normalize('composer.lock'));

        foreach (['/etc/passwd', '../.env', '.env', 'vendor/laravel/mcp', 'node_modules/package.json', 'storage/logs/laravel.log', 'public/build/manifest.json', 'tests/id_rsa'] as $path) {
            try {
                $guard->normalize($path);
                $this->fail("Expected [{$path}] to be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_sensitive_values_are_redacted_from_strings_and_metadata(): void
    {
        $redactor = new SensitiveValueRedactor;
        $value = $redactor->redact('mysql://alice:secret@db Bearer abc.def API_TOKEN=token-value password: hunter2');
        $metadata = $redactor->redactValue([
            'api_token' => 'plain-token',
            'nested' => ['dsn' => 'redis://user:pass@redis'],
        ]);

        $this->assertStringNotContainsString('alice:secret', $value);
        $this->assertStringNotContainsString('abc.def', $value);
        $this->assertStringNotContainsString('token-value', $value);
        $this->assertStringNotContainsString('hunter2', $value);
        $this->assertSame('[REDACTED]', $metadata['api_token']);
        $this->assertSame('redis://[REDACTED]@redis', $metadata['nested']['dsn']);
    }

    public function test_process_runner_reports_success_failure_timeout_and_truncation(): void
    {
        $runner = $this->app->make(ProcessRunner::class);

        config()->set('nntmux-mcp.limits.max_process_output_bytes', 1024);
        $success = $runner->run([PHP_BINARY, '-r', 'fwrite(STDOUT, "API_TOKEN=super-secret");'], 5);
        $failure = $runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "failed"); exit(3);'], 5);
        $timeout = $runner->run([PHP_BINARY, '-r', 'sleep(2);'], 1);

        config()->set('nntmux-mcp.limits.max_process_output_bytes', 16);
        $truncated = $runner->run([PHP_BINARY, '-r', 'fwrite(STDOUT, str_repeat("x", 100));'], 5);

        $this->assertSame('passed', $success['status']);
        $this->assertSame('API_TOKEN=[REDACTED]', $success['output']);
        $this->assertSame('failed', $failure['status']);
        $this->assertSame(3, $failure['exit_code']);
        $this->assertSame('timed_out', $timeout['status']);
        $this->assertNull($timeout['exit_code']);
        $this->assertTrue($truncated['truncated']);
        $this->assertStringContainsString('[output truncated]', $truncated['output']);
    }

    public function test_verification_plans_cover_php_frontend_configuration_migration_and_mcp_paths(): void
    {
        $planner = $this->app->make(VerificationPlanner::class);
        $migration = str_replace(base_path().'/', '', (glob(base_path('database/migrations/*.php')) ?: [])[0]);

        $phpChecks = array_column($planner->plan(['app/Models/Release.php'])['checks'], 'name');
        $frontendChecks = array_column($planner->plan(['resources/js/app.js'])['checks'], 'name');
        $configurationChecks = array_column($planner->plan(['config/nntmux-mcp.php'])['checks'], 'name');
        $migrationChecks = array_column($planner->plan([$migration])['checks'], 'name');
        $mcpPlan = $planner->plan(['app/Mcp/Tools/InspectCodeContextTool.php']);

        $this->assertSame(['phpunit', 'pint', 'phpstan', 'php-lint', 'diff'], $phpChecks);
        $this->assertSame(['frontend', 'diff'], $frontendChecks);
        $this->assertSame(['phpunit', 'pint', 'phpstan', 'php-lint', 'diff'], $configurationChecks);
        $this->assertContains('php-lint', $migrationChecks);
        $this->assertContains('tests/Feature/McpDevelopmentServerTest.php', $mcpPlan['test_paths']);
        $this->assertContains('tests/Feature/McpVerificationServicesTest.php', $mcpPlan['test_paths']);
    }

    public function test_phpunit_verification_uses_argument_arrays_and_isolated_testing_configuration(): void
    {
        $processes = $this->processRunner(
            function (array $command, int $timeout, array $environment): array {
                $this->assertSame('vendor/bin/phpunit', $command[0]);
                $this->assertSame('tests/Feature/McpDevelopmentServerTest.php', $command[2]);
                $this->assertSame('--filter=test_context_results_report_truncation', $command[3]);
                $this->assertSame(600, $timeout);
                $this->assertSame('testing', $environment['APP_ENV']);
                $this->assertSame('testing', $environment['DB_CONNECTION']);
                $this->assertSame(':memory:', $environment['DB_DATABASE']);
                $this->assertStringStartsWith(sys_get_temp_dir().'/nntmux-mcp-config-', $environment['APP_CONFIG_CACHE']);

                return $this->processResult('passed');
            },
        );
        $runner = new VerificationRunner(
            $this->app->make(ProjectPathGuard::class),
            $processes,
            $this->app->make(VerificationPlanner::class),
        );

        $result = $runner->run(
            'phpunit',
            ['tests/Feature/McpDevelopmentServerTest.php'],
            'tests/Feature/McpDevelopmentServerTest.php',
            'test_context_results_report_truncation',
        );

        $this->assertSame('passed', $result['status']);
        $this->expectException(InvalidArgumentException::class);
        $runner->run(
            'phpunit',
            ['tests/Feature/McpDevelopmentServerTest.php'],
            'tests/Feature/McpDevelopmentServerTest.php',
            'test_name;touch',
        );
    }

    public function test_each_verification_check_builds_only_fixed_argument_array_commands(): void
    {
        $cases = [
            'phpstan' => [
                'paths' => ['app/Mcp/Tools/ProbeRuntimeTool.php'],
                'commands' => [['vendor/bin/phpstan', 'analyse', '--memory-limit=2G', '--debug', '--no-progress', 'app/Mcp/Tools/ProbeRuntimeTool.php']],
            ],
            'pint' => [
                'paths' => ['app/Mcp/Tools/ProbeRuntimeTool.php'],
                'commands' => [['vendor/bin/pint', '--test', '--format=agent', 'app/Mcp/Tools/ProbeRuntimeTool.php']],
            ],
            'php-lint' => [
                'paths' => ['app/Mcp/Tools/ProbeRuntimeTool.php'],
                'commands' => [['php', '-l', 'app/Mcp/Tools/ProbeRuntimeTool.php']],
            ],
            'diff' => [
                'paths' => ['app/Mcp/Tools/ProbeRuntimeTool.php'],
                'commands' => [
                    ['git', 'diff', '--check', '--', 'app/Mcp/Tools/ProbeRuntimeTool.php'],
                    ['git', 'diff', '--cached', '--check', '--', 'app/Mcp/Tools/ProbeRuntimeTool.php'],
                ],
            ],
            'composer' => [
                'paths' => ['composer.json'],
                'commands' => [['composer', 'validate', '--no-check-publish']],
            ],
            'frontend' => [
                'paths' => ['resources/js/app.js'],
                'commands' => [['npm', 'run', 'build']],
            ],
        ];

        foreach ($cases as $check => $case) {
            $commands = [];
            $processes = $this->processRunner(function (array $command) use (&$commands): array {
                $commands[] = $command;

                return $this->processResult('passed');
            });
            $runner = new VerificationRunner(
                $this->app->make(ProjectPathGuard::class),
                $processes,
                $this->app->make(VerificationPlanner::class),
            );

            $runner->run($check, $case['paths']);

            $this->assertSame($case['commands'], $commands, "Unexpected command arguments for {$check}.");
        }
    }

    public function test_change_impact_uses_scoped_git_and_search_processes(): void
    {
        $processes = $this->processRunner(function (array $command): array {
            if ($command[0] === 'git') {
                $this->assertSame('--', $command[count($command) - 2]);
                $this->assertSame('config/nntmux-mcp.php', $command[count($command) - 1]);

                return $this->processResult('passed', 'diff --git a/config/nntmux-mcp.php b/config/nntmux-mcp.php');
            }

            return $this->processResult('passed', 'app/Mcp/Tools/PlanVerificationTool.php:20:nntmux-mcp');
        });
        $collector = new RepositoryContextCollector(
            $this->app->make(ProjectPathGuard::class),
            $processes,
            new SensitiveValueRedactor,
            $this->app->make(VerificationPlanner::class),
        );

        $impact = $collector->changeImpact(['config/nntmux-mcp.php']);

        $this->assertSame(['config/nntmux-mcp.php'], $impact['paths']);
        $this->assertStringContainsString('diff --git', $impact['unstaged_diff']);
        $this->assertSame('runtime configuration', $impact['affected_interfaces'][0]);
        $this->assertSame('app/Mcp/Tools/PlanVerificationTool.php', $impact['dependants'][0]['path']);
    }

    public function test_runtime_probe_runner_resolves_only_requested_probe_classes(): void
    {
        $database = Mockery::mock(ServiceProbeInterface::class);
        $database->shouldReceive('probe')->once()->andReturn(new ProbeResult(true, 2, null, 'Connected'));
        $disk = Mockery::mock(ServiceProbeInterface::class);
        $disk->shouldReceive('probe')->once()->andReturn(new ProbeResult(true, 1, null, 'Writable'));
        $nntp = Mockery::mock(ServiceProbeInterface::class);
        $nntp->shouldNotReceive('probe');
        $this->app->instance(DatabaseProbe::class, $database);
        $this->app->instance(DiskProbe::class, $disk);
        $this->app->instance(NntpProbe::class, $nntp);
        $runner = new RuntimeProbeRunner($this->app, new SensitiveValueRedactor);

        $results = $runner->run(['database', 'disk']);

        $this->assertSame(['database', 'disk'], array_column($results, 'id'));
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
    }

    /**
     * @return array{command: list<string>, exit_code: int|null, duration_ms: int, status: string, output: string, truncated: bool}
     */
    private function processResult(string $status, string $output = ''): array
    {
        return [
            'command' => [],
            'exit_code' => $status === 'passed' ? 0 : 1,
            'duration_ms' => 1,
            'status' => $status,
            'output' => $output,
            'truncated' => false,
        ];
    }

    private function processRunner(Closure $handler): ProcessRunner
    {
        return new class($handler) extends ProcessRunner
        {
            public function __construct(private readonly Closure $handler)
            {
                parent::__construct(new SensitiveValueRedactor);
            }

            public function run(array $command, int $timeoutSeconds, array $environment = []): array
            {
                $result = ($this->handler)($command, $timeoutSeconds, $environment);

                if (! is_array($result)) {
                    throw new \LogicException('The process test handler must return a process result array.');
                }

                return $result;
            }
        };
    }
}
