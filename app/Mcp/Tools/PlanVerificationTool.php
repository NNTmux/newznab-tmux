<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HasVersionedStructuredOutput;
use App\Services\Mcp\ProjectPathGuard;
use App\Services\Mcp\VerificationPlanner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('plan-verification')]
#[Description('Build the ordered host-side NNTmux verification plan for selected changed paths without executing commands.')]
#[IsReadOnly]
#[IsIdempotent]
class PlanVerificationTool extends Tool
{
    use HasVersionedStructuredOutput;

    public function schema(JsonSchema $schema): array
    {
        return [
            'paths' => $schema->array()->items($schema->string()->min(1))->min(1)->max(20)->unique()->description('Repository-relative changed paths.')->required(),
        ];
    }

    public function handle(Request $request, ProjectPathGuard $guard, VerificationPlanner $planner): ResponseFactory|Response
    {
        $startedAt = hrtime(true);
        $validated = $request->validate([
            'paths' => ['required', 'array', 'min:1', 'max:20'],
            'paths.*' => ['required', 'string', 'distinct'],
        ]);

        try {
            $paths = $guard->normalizeMany(array_values($validated['paths']));
        } catch (InvalidArgumentException $exception) {
            return $this->structuredError($exception->getMessage());
        }

        $plan = $planner->plan($paths);
        $payload = [
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => sprintf('Planned %d verification check(s).', count($plan['checks'])),
            'paths' => $paths,
            'results' => $plan,
            'warnings' => [],
            'truncated' => false,
        ];

        Log::channel('nntmux_mcp')->info('MCP tool completed', [
            'tool' => 'plan-verification',
            'safe_paths' => $paths,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'status' => 'completed',
            'truncated' => false,
        ]);

        return Response::make(Response::text($payload['summary']))->withStructuredContent($payload);
    }
}
