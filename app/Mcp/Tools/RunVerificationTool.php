<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\HasVersionedStructuredOutput;
use App\Services\Mcp\VerificationRunner;
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

#[Name('run-verification')]
#[Description('Run an allowlisted, non-source-mutating NNTmux verification check with root-constrained paths, fixed arguments, timeouts, and bounded output.')]
#[IsReadOnly(false)]
#[IsIdempotent]
class RunVerificationTool extends Tool
{
    use HasVersionedStructuredOutput;

    private const array CHECKS = ['targeted', 'phpunit', 'phpstan', 'pint', 'php-lint', 'diff', 'composer', 'frontend'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'check' => $schema->string()->enum(self::CHECKS)->description('Allowlisted verification check to execute.')->required(),
            'paths' => $schema->array()->items($schema->string()->min(1))->min(1)->max(20)->unique()->description('Repository-relative paths in scope.')->required(),
            'test_path' => $schema->string()->description('Required for phpunit unless a Test.php path is included or inferred.'),
            'filter' => $schema->string()->max(200)->description('Optional PHPUnit class or test-method filter.'),
        ];
    }

    public function handle(Request $request, VerificationRunner $runner): ResponseFactory|Response
    {
        $validated = $request->validate([
            'check' => ['required', 'string', 'in:'.implode(',', self::CHECKS)],
            'paths' => ['required', 'array', 'min:1', 'max:20'],
            'paths.*' => ['required', 'string', 'distinct'],
            'test_path' => ['sometimes', 'string'],
            'filter' => ['sometimes', 'string', 'max:200'],
        ]);

        try {
            $verification = $runner->run(
                $validated['check'],
                array_values($validated['paths']),
                $validated['test_path'] ?? null,
                $validated['filter'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->structuredError($exception->getMessage());
        }

        $payload = [
            'schema_version' => (int) config('nntmux-mcp.schema_version', 1),
            'summary' => sprintf('Verification %s with %d result(s).', $verification['status'], count($verification['results'])),
            'results' => $verification,
            'warnings' => $verification['warnings'],
            'truncated' => $verification['truncated'],
        ];

        return Response::make(Response::text($payload['summary']))->withStructuredContent($payload);
    }
}
