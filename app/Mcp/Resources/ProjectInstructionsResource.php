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

#[Name('NNTmux Project Instructions')]
#[Description('Committed AGENTS.md and .ai/rules guidance, bounded for agent context.')]
#[Uri('nntmux://project/instructions')]
#[MimeType('text/markdown')]
class ProjectInstructionsResource extends Resource
{
    public function handle(Request $request): Response
    {
        $maximumBytes = max(1, (int) config('nntmux-mcp.limits.max_resource_bytes', 65_536));
        $sections = [];

        foreach ($this->instructionPaths() as $path) {
            $absolutePath = base_path($path);
            if (! is_file($absolutePath)) {
                continue;
            }

            $sections[] = "# {$path}\n\n".(string) file_get_contents($absolutePath);
        }

        $content = implode("\n\n", $sections);
        $truncated = strlen($content) > $maximumBytes;
        if ($truncated) {
            $content = mb_strcut($content, 0, $maximumBytes)."\n\n[project instructions truncated]";
        }

        return Response::text(implode("\n", [
            '<!-- schema_version: '.(int) config('nntmux-mcp.schema_version', 1).' -->',
            '<!-- truncated: '.($truncated ? 'true' : 'false').' -->',
            $content,
        ]));
    }

    /**
     * @return list<string>
     */
    private function instructionPaths(): array
    {
        $paths = ['AGENTS.md', '.ai/rules/index.md'];
        $rulePaths = glob(base_path('.ai/rules/*.md')) ?: [];

        foreach ($rulePaths as $rulePath) {
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', substr($rulePath, strlen(base_path()) + 1));
            if ($relativePath !== '.ai/rules/index.md') {
                $paths[] = $relativePath;
            }
        }

        sort($paths);

        return array_values(array_unique($paths));
    }
}
