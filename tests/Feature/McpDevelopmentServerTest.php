<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\AdditionalProcessingDiagnose;
use App\Console\Commands\CloudflareReload;
use App\Console\Commands\CollectSystemMetrics;
use App\Console\Commands\DisableExpiredRegistrationPeriods;
use App\Console\Commands\PostProcessGuid;
use App\Console\Commands\ProcessReleasesCommand;
use App\Console\Commands\UpdatePerGroup;
use App\Console\Commands\UpdatePostProcess;
use App\Mcp\Prompts\DiagnoseNntmuxProblemPrompt;
use App\Mcp\Prompts\ReviewNntmuxChangePrompt;
use App\Mcp\Resources\ProjectInstructionsResource;
use App\Mcp\Resources\ProjectOverviewResource;
use App\Mcp\Servers\NewznabTmuxServer;
use App\Mcp\Tools\InspectChangeImpactTool;
use App\Mcp\Tools\InspectCodeContextTool;
use App\Mcp\Tools\PlanVerificationTool;
use App\Mcp\Tools\ProbeRuntimeTool;
use App\Mcp\Tools\RunVerificationTool;
use App\Services\Mcp\ProcessRunner;
use App\Services\Mcp\RuntimeProbeRunner;
use App\Services\Mcp\SensitiveValueRedactor;
use Illuminate\Testing\Fluent\AssertableJson;
use Mockery;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class McpDevelopmentServerTest extends TestCase
{
    public function test_it_registers_expected_tools_resources_and_prompts(): void
    {
        NewznabTmuxServer::tools()->assertRegistered([
            InspectCodeContextTool::class,
            InspectChangeImpactTool::class,
            PlanVerificationTool::class,
            RunVerificationTool::class,
            ProbeRuntimeTool::class,
        ]);
        NewznabTmuxServer::resources()->assertRegistered([
            ProjectOverviewResource::class,
            ProjectInstructionsResource::class,
        ]);
        NewznabTmuxServer::prompts()->assertRegistered([
            DiagnoseNntmuxProblemPrompt::class,
            ReviewNntmuxChangePrompt::class,
        ]);
    }

    public function test_tool_schemas_and_annotations_describe_bounded_read_only_operations(): void
    {
        $context = (new InspectCodeContextTool)->toArray();
        $runtime = (new ProbeRuntimeTool)->toArray();
        $verification = (new RunVerificationTool)->toArray();

        $this->assertSame(['auto', 'path', 'symbol', 'error'], $context['inputSchema']['properties']['kind']['enum']);
        $this->assertSame(50, $context['inputSchema']['properties']['max_results']['maximum']);
        $this->assertSame(
            ['schema_version', 'summary', 'warnings', 'truncated'],
            $context['outputSchema']['required'],
        );
        $this->assertTrue($context['annotations']['readOnlyHint']);
        $this->assertTrue($context['annotations']['idempotentHint']);
        $this->assertSame(
            ['database', 'redis', 'search', 'queue', 'disk', 'nntp'],
            $runtime['inputSchema']['properties']['probes']['items']['enum'],
        );
        $this->assertTrue($runtime['annotations']['openWorldHint']);
        $this->assertFalse($verification['annotations']['readOnlyHint']);
    }

    public function test_context_tool_collects_versioned_evidence_for_known_nntmux_code(): void
    {
        NewznabTmuxServer::tool(InspectCodeContextTool::class, [
            'query' => 'app/Services/StatusProbes/DatabaseProbe.php',
            'kind' => 'path',
            'max_results' => 5,
        ])->assertOk()->assertStructuredContent(function (AssertableJson $json): void {
            $json->where('schema_version', 1)
                ->has('summary')
                ->where('evidence.kind', 'path')
                ->where('evidence.architecture_area', 'Read-only service health probes')
                ->has('evidence.matches')
                ->has('warnings')
                ->has('truncated');
        });
    }

    public function test_context_tool_rejects_traversal_and_sensitive_paths(): void
    {
        NewznabTmuxServer::tool(InspectCodeContextTool::class, [
            'query' => '../.env',
            'kind' => 'path',
        ])->assertHasErrors(['Path traversal is not allowed.']);

        NewznabTmuxServer::tool(InspectCodeContextTool::class, [
            'query' => '.env',
            'kind' => 'path',
        ])->assertHasErrors(['outside the allowed source roots']);
    }

    public function test_context_results_report_truncation(): void
    {
        $this->app->instance(ProcessRunner::class, new class extends ProcessRunner
        {
            public function __construct()
            {
                parent::__construct(new SensitiveValueRedactor);
            }

            /**
             * Return canned search hits so the test does not depend on ripgrep being installed.
             */
            public function run(array $command, int $timeoutSeconds, array $environment = []): array
            {
                $isSourceSearch = $command[0] === 'rg' && in_array('--line-number', $command, true);

                return [
                    'command' => $command,
                    'exit_code' => 0,
                    'duration_ms' => 0,
                    'status' => 'passed',
                    'output' => $isSourceSearch ? "app/First.php:3:class First\napp/Second.php:5:class Second" : '',
                    'truncated' => false,
                ];
            }
        });

        NewznabTmuxServer::tool(InspectCodeContextTool::class, [
            'query' => 'class',
            'kind' => 'symbol',
            'max_results' => 1,
        ])->assertOk()->assertStructuredContent(function (AssertableJson $json): void {
            $json->where('schema_version', 1)
                ->where('truncated', true)
                ->has('evidence.matches', 1)
                ->etc();
        });
    }

    public function test_runtime_tool_runs_only_explicitly_selected_probes(): void
    {
        $runner = Mockery::mock(RuntimeProbeRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->with(['database', 'disk'])
            ->andReturn([
                ['id' => 'database', 'ok' => true, 'latency_ms' => 2, 'impact' => null, 'reason' => 'Connected', 'metadata' => []],
                ['id' => 'disk', 'ok' => true, 'latency_ms' => 1, 'impact' => null, 'reason' => 'Writable', 'metadata' => []],
            ]);
        $this->app->instance(RuntimeProbeRunner::class, $runner);

        NewznabTmuxServer::tool(ProbeRuntimeTool::class, [
            'probes' => ['database', 'disk'],
        ])->assertOk()->assertStructuredContent(function (AssertableJson $json): void {
            $json->where('schema_version', 1)
                ->has('results', 2)
                ->where('results.0.id', 'database')
                ->where('results.1.id', 'disk')
                ->where('truncated', false)
                ->etc();
        });
    }

    public function test_tool_validation_rejects_empty_probe_lists_and_unknown_checks(): void
    {
        NewznabTmuxServer::tool(ProbeRuntimeTool::class, [
            'probes' => [],
        ])->assertHasErrors();

        NewznabTmuxServer::tool(RunVerificationTool::class, [
            'check' => 'shell',
            'paths' => ['composer.json'],
        ])->assertHasErrors();
    }

    public function test_commands_with_required_services_have_lazy_command_metadata(): void
    {
        $commands = [
            AdditionalProcessingDiagnose::class,
            CloudflareReload::class,
            CollectSystemMetrics::class,
            DisableExpiredRegistrationPeriods::class,
            PostProcessGuid::class,
            ProcessReleasesCommand::class,
            UpdatePerGroup::class,
            UpdatePostProcess::class,
        ];

        foreach ($commands as $command) {
            $attributes = (new ReflectionClass($command))->getAttributes(AsCommand::class);

            $this->assertCount(1, $attributes, "{$command} must be lazily registered.");
            $this->assertNotSame('', $attributes[0]->newInstance()->name);
        }
    }

    public function test_resources_and_prompts_provide_project_specific_guidance(): void
    {
        NewznabTmuxServer::resource(ProjectOverviewResource::class)
            ->assertOk()
            ->assertSee(['schema_version', 'NNTP -> NNTPService', 'Laravel Boost']);
        NewznabTmuxServer::resource(ProjectInstructionsResource::class)
            ->assertOk()
            ->assertSee(['schema_version: 1', 'AGENTS.md', 'Command execution precedence']);
        NewznabTmuxServer::prompt(DiagnoseNntmuxProblemPrompt::class, [
            'problem' => 'Release processing fails after parsing a header.',
        ])->assertOk()->assertSee(['evidence-first', 'Release processing fails']);
        NewznabTmuxServer::prompt(ReviewNntmuxChangePrompt::class, [
            'goal' => 'Preserve API behavior while optimizing browse queries.',
        ])->assertOk()->assertSee(['failure-mode risks', 'Preserve API behavior']);
    }

    public function test_stdio_protocol_starts_and_lists_tools_without_mariadb(): void
    {
        $configurationCache = sys_get_temp_dir().'/nntmux-mcp-protocol-'.bin2hex(random_bytes(6)).'.php';
        $messages = [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-11-25',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ]],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized', 'params' => (object) []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => (object) []],
        ];
        $input = implode("\n", array_map(
            static fn (array $message): string => (string) json_encode($message, JSON_THROW_ON_ERROR),
            $messages,
        ))."\n";
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'mcp:start', 'newznab-tmux'],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'APP_CONFIG_CACHE' => $configurationCache,
                'DB_CONNECTION' => 'mariadb',
                'DB_HOST' => 'mariadb-deliberately-unavailable.invalid',
                'DB_DATABASE' => 'nntmux',
            ],
            $input,
            15,
        );

        try {
            $process->run();
        } finally {
            if (is_file($configurationCache)) {
                unlink($configurationCache);
            }
        }

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('', trim($process->getErrorOutput()));
        $lines = array_values(array_filter(preg_split('/\R/', trim($process->getOutput())) ?: []));
        $this->assertCount(2, $lines);

        $responses = array_map(
            static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $lines,
        );
        $this->assertSame(1, $responses[0]['id']);
        $this->assertSame(2, $responses[1]['id']);
        $toolNames = array_column($responses[1]['result']['tools'], 'name');
        $this->assertContains('inspect-code-context', $toolNames);
        $this->assertContains('probe-runtime', $toolNames);
    }
}
