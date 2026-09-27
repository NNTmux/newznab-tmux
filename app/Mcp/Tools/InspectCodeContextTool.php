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

#[Name('inspect-code-context')]
#[Description('Collect bounded NNTmux source evidence, callers, tests, rules, configuration keys, and path history for a path, symbol, or error.')]
#[IsReadOnly]
#[IsIdempotent]
class InspectCodeContextTool extends Tool
{
    use HasVersionedStructuredOutput;

    public function schema(JsonSchema $schema): array
    {
        $maximumResults = max(1, (int) config('nntmux-mcp.limits.max_results', 50));

        return [
            'query' => $schema->string()->min(1)->max(8192)->description('Repository-relative path, PHP symbol, or error text to investigate.')->required(),
            'kind' => $schema->string()->enum(['auto', 'path', 'symbol', 'error'])->default('auto')->description('How the query should be interpreted.'),
            'max_results' => $schema->integer()->min(1)->max($maximumResults)->default(min(20, $maximumResults))->description('Maximum source matches to return.'),
        ];
    }

    public function handle(Request $request, RepositoryContextCollector $collector): ResponseFactory|Response
    {
        $maximumResults = max(1, (int) config('nntmux-mcp.limits.max_results', 50));
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:8192'],
            'kind' => ['sometimes', 'string', 'in:auto,path,symbol,error'],
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:'.$maximumResults],
        ]);

        try {
            $evidence = $collector->inspect(
                trim($validated['query']),
                $validated['kind'] ?? 'auto',
                $validated['max_results'] ?? min(20, $maximumResults),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->structuredError($exception->getMessage());
        }

        $payload = [
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => sprintf('Found %d relevant source location(s).', count($evidence['matches'])),
            'evidence' => $evidence,
            'warnings' => $evidence['warnings'],
            'truncated' => $evidence['truncated'],
        ];

        return Response::make(Response::text($payload['summary']))->withStructuredContent($payload);
    }
}
