<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

trait HasVersionedStructuredOutput
{
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'schema_version' => $schema->integer()->description('Stable response schema version.')->required(),
            'summary' => $schema->string()->description('Concise result summary.')->required(),
            'evidence' => $schema->union(['object', 'array'])->description('Collected inspection evidence when applicable.'),
            'results' => $schema->union(['object', 'array'])->description('Planned, executed, or probed results when applicable.'),
            'warnings' => $schema->array()->items($schema->string())->description('Non-fatal caveats and partial-result notices.')->required(),
            'truncated' => $schema->boolean()->description('Whether any bounded content was truncated.')->required(),
        ];
    }

    protected function structuredError(string $message): ResponseFactory
    {
        return Response::make(Response::error($message))->withStructuredContent([
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => 'The request was rejected.',
            'warnings' => [$message],
            'truncated' => false,
        ]);
    }
}
