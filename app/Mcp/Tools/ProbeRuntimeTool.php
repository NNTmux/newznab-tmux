<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HasVersionedStructuredOutput;
use App\Services\Mcp\RuntimeProbeRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('probe-runtime')]
#[Description('Run only explicitly selected NNTmux service probes and return sanitized health evidence. NNTP is never selected implicitly.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class ProbeRuntimeTool extends Tool
{
    use HasVersionedStructuredOutput;

    private const array PROBES = ['database', 'redis', 'search', 'queue', 'disk', 'nntp'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'probes' => $schema->array()->items($schema->string()->enum(self::PROBES))->min(1)->max(6)->unique()->description('Explicit service probes to run.')->required(),
        ];
    }

    public function handle(Request $request, RuntimeProbeRunner $runner): ResponseFactory
    {
        $validated = $request->validate([
            'probes' => ['required', 'array', 'min:1', 'max:6'],
            'probes.*' => ['required', 'string', 'distinct', 'in:'.implode(',', self::PROBES)],
        ]);
        $results = $runner->run(array_values($validated['probes']));
        $failed = count(array_filter($results, fn (array $result): bool => ! $result['ok']));
        $payload = [
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => sprintf('Completed %d probe(s); %d reported unhealthy.', count($results), $failed),
            'results' => $results,
            'warnings' => $failed > 0 ? ['One or more selected runtime services reported unhealthy.'] : [],
            'truncated' => false,
        ];

        return Response::make(Response::text($payload['summary']))->withStructuredContent($payload);
    }
}
