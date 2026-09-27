<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('diagnose-nntmux-problem')]
#[Description('Evidence-first workflow from an NNTmux failure to root cause, scoped repair, and regression checks.')]
class DiagnoseNntmuxProblemPrompt extends Prompt
{
    /** @return list<Argument> */
    public function arguments(): array
    {
        return [
            new Argument('problem', 'The observed error, failure, or unexpected behavior.', required: true),
            new Argument('scope', 'Optional repository paths or subsystem boundary.'),
        ];
    }

    /** @return list<Response> */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'problem' => ['required', 'string', 'max:12000'],
            'scope' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            Response::text('Act as an evidence-first NNTmux debugger. Inspect the producer, runtime path, schema/configuration, history, and related tests before proposing a change. Identify the narrow root cause, preserve public behavior, and finish with focused failure-mode coverage plus the verification plan. Use runtime probes only when useful, and request NNTP explicitly if it is genuinely required.')->asAssistant(),
            Response::text("Problem:\n{$validated['problem']}\n\nScope:\n".($validated['scope'] ?? 'Infer the narrowest safe scope from evidence.')),
        ];
    }
}
