<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HasVersionedStructuredOutput;
use App\Services\Mcp\RepositoryContextCollector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('inspect-change-impact')]
#[Description('Inspect staged and unstaged changes for selected paths and identify affected NNTmux interfaces, dependants, rules, tests, and checks.')]
#[IsReadOnly]
#[IsIdempotent]
class InspectChangeImpactTool extends Tool
{
    use HasVersionedStructuredOutput;

    public function schema(JsonSchema $schema): array
    {
        return [
            'paths' => $schema->array()->items($schema->string()->min(1))->min(1)->max(20)->unique()->description('Repository-relative changed paths to inspect.')->required(),
        ];
    }

    public function handle(Request $request, RepositoryContextCollector $collector): ResponseFactory|Response
    {
        $validated = $request->validate([
            'paths' => ['required', 'array', 'min:1', 'max:20'],
            'paths.*' => ['required', 'string', 'distinct'],
        ]);

        try {
            $impact = $collector->changeImpact(array_values($validated['paths']));
        } catch (InvalidArgumentException $exception) {
            return $this->structuredError($exception->getMessage());
        }

        $payload = [
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => sprintf('Assessed change impact for %d path(s).', count($impact['paths'])),
            'evidence' => $impact,
            'warnings' => $impact['warnings'],
            'truncated' => $impact['truncated'],
        ];

        return Response::make(Response::text($payload['summary']))->withStructuredContent($payload);
    }
}
