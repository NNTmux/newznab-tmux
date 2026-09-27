<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DiagnoseNntmuxProblemPrompt;
use App\Mcp\Prompts\ReviewNntmuxChangePrompt;
use App\Mcp\Resources\ProjectInstructionsResource;
use App\Mcp\Resources\ProjectOverviewResource;
use App\Mcp\Tools\InspectChangeImpactTool;
use App\Mcp\Tools\InspectCodeContextTool;
use App\Mcp\Tools\PlanVerificationTool;
use App\Mcp\Tools\ProbeRuntimeTool;
use App\Mcp\Tools\RunVerificationTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('newznab-tmux Development Server')]
#[Version('1.0.0')]
#[Instructions('Use this local, source-read-only server to gather NNTmux-specific evidence and verify scoped changes. Read project guidance, inspect relevant code and impact, edit through the client workspace, and run targeted verification. Runtime probes are opt-in; never invoke NNTP implicitly.')]
class NewznabTmuxServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        InspectCodeContextTool::class,
        InspectChangeImpactTool::class,
        PlanVerificationTool::class,
        RunVerificationTool::class,
        ProbeRuntimeTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        ProjectOverviewResource::class,
        ProjectInstructionsResource::class,
    ];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [
        DiagnoseNntmuxProblemPrompt::class,
        ReviewNntmuxChangePrompt::class,
    ];
}
