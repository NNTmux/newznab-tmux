<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('NNTmux Project Overview')]
#[Description('NNTmux architecture, processing flow, important directories, test stack, and host command policy.')]
#[Uri('nntmux://project/overview')]
#[MimeType('application/json')]
class ProjectOverviewResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::json([
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => 'NNTmux is a Laravel Usenet indexer with local host tooling and an optional tmux processing engine.',
            'architecture' => [
                'processing_flow' => 'NNTP -> NNTPService -> BinariesRunner -> ReleaseCreationService -> ReleaseProcessingService -> SearchService -> API/Web',
                'patterns' => ['service layer', 'pipelines', 'search drivers', 'runners', 'DTOs', 'enums', 'observers', 'status probes'],
                'important_directories' => [
                    'app/Services' => 'Domain services, runners, search drivers, processing pipelines, and status probes.',
                    'app/Console/Commands' => 'Artisan processing and maintenance commands.',
                    'app/Mcp' => 'The local NNTmux MCP server, tools, resources, and prompts.',
                    'tests' => 'PHPUnit unit, feature, install, and opt-in integration coverage.',
                    'resources' => 'Blade, Alpine.js, Tailwind CSS, and Vite source assets.',
                ],
            ],
            'testing' => [
                'framework' => 'PHPUnit 12',
                'database' => 'In-memory SQLite through DB_CONNECTION=testing',
                'focused_command' => 'php artisan test --compact --filter=TestName',
            ],
            'command_policy' => [
                'default' => 'Run PHP, Artisan, Composer, npm, Pint, PHPStan, and tests on the WSL host.',
                'containers' => 'Use Sail or make only for Compose lifecycle or when explicitly requested.',
            ],
            'companion' => 'Use Laravel Boost for generic Laravel documentation, schema and read-only queries, logs, browser logs, and Tinker.',
            'warnings' => ['Runtime probes may be unavailable. NNTP is never probed unless explicitly requested.'],
            'truncated' => false,
        ]);
    }
}
