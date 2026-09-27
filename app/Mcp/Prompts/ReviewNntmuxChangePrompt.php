<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('review-nntmux-change')]
#[Description('Review an NNTmux change for impact, conventions, failure modes, and targeted verification.')]
class ReviewNntmuxChangePrompt extends Prompt
{
    /** @return list<Argument> */
    public function arguments(): array
    {
        return [
            new Argument('goal', 'The intended behavior and constraints of the change.', required: true),
            new Argument('paths', 'Optional newline- or comma-separated repository-relative paths.'),
        ];
    }

    /** @return list<Response> */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'goal' => ['required', 'string', 'max:8000'],
            'paths' => ['nullable', 'string', 'max:4000'],
        ]);

        return [
            Response::text('Review the selected NNTmux change as a repository specialist. Inspect scoped diffs and dependants, verify applicable AGENTS.md and .ai/rules guidance, identify behavior and failure-mode risks, check test adequacy, then run only the targeted allowlisted verification that the paths require. Report findings by severity with concrete file evidence, followed by verification results and residual risk.')->asAssistant(),
            Response::text("Change goal:\n{$validated['goal']}\n\nChanged paths:\n".($validated['paths'] ?? 'Derive them from the current scoped diff.')),
        ];
    }
}
