<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Log;

class RepositoryContextCollector
{
    public function __construct(
        private readonly ProjectPathGuard $paths,
        private readonly ProcessRunner $processes,
        private readonly SensitiveValueRedactor $redactor,
        private readonly VerificationPlanner $verificationPlanner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $query, string $kind, int $maximumResults): array
    {
        $startedAt = hrtime(true);
        $resolvedKind = $kind === 'auto' ? $this->detectKind($query) : $kind;
        $path = $resolvedKind === 'path' ? $this->paths->normalize($query) : null;
        $terms = $this->searchTerms($query, $resolvedKind, $path);
        $matches = $this->search($terms, $maximumResults);
        $contextPaths = array_values(array_unique(array_filter([
            $path,
            ...array_column($matches, 'path'),
        ])));

        $payload = [
            'kind' => $resolvedKind,
            'query' => $this->redactor->redact($query),
            'architecture_area' => $this->architectureArea($path ?? ($contextPaths[0] ?? '')),
            'matches' => $matches,
            'dependants' => array_values(array_filter($matches, fn (array $match): bool => ! str_starts_with($match['path'], 'tests/'))),
            'related_tests' => array_values(array_unique(array_map(
                fn (array $match): string => $match['path'],
                array_filter($matches, fn (array $match): bool => str_starts_with($match['path'], 'tests/')),
            ))),
            'applicable_rules' => $this->applicableRules($contextPaths, $terms),
            'configuration_keys' => $this->configurationKeys($contextPaths),
            'recent_history' => $path !== null ? $this->recentHistory($path) : [],
            'warnings' => $matches === [] ? ['No matching source locations were found.'] : [],
            'truncated' => count($matches) >= $maximumResults,
        ];

        Log::channel('nntmux_mcp')->info('MCP tool completed', [
            'tool' => 'inspect-code-context',
            'safe_paths' => $contextPaths,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'status' => 'completed',
            'truncated' => $payload['truncated'],
        ]);

        return $payload;
    }

    /**
     * @param  list<string>  $inputPaths
     * @return array<string, mixed>
     */
    public function changeImpact(array $inputPaths): array
    {
        $startedAt = hrtime(true);
        $paths = $this->paths->normalizeMany($inputPaths);
        $maximumResults = max(1, (int) config('nntmux-mcp.limits.max_results', 50));
        $dependants = [];
        $rules = [];

        foreach ($paths as $path) {
            $term = preg_replace('/(?:\.blade)?\.[^.]+$/', '', basename($path)) ?? basename($path);
            $dependants = [...$dependants, ...$this->search([$term], min(10, $maximumResults))];
            $rules = [...$rules, ...$this->applicableRules([$path], [$term])];
        }

        $unstaged = $this->gitDiff($paths, false);
        $staged = $this->gitDiff($paths, true);
        $verification = $this->verificationPlanner->plan($paths);
        $uniqueDependants = $this->uniqueMatches($dependants);
        $dependantsTruncated = count($uniqueDependants) > $maximumResults;

        $payload = [
            'paths' => $paths,
            'areas' => array_values(array_unique(array_map($this->architectureArea(...), $paths))),
            'unstaged_diff' => $unstaged['output'],
            'staged_diff' => $staged['output'],
            'dependants' => array_slice($uniqueDependants, 0, $maximumResults),
            'related_tests' => $verification['test_paths'],
            'applicable_rules' => array_values(array_unique($rules)),
            'affected_interfaces' => $this->affectedInterfaces($paths),
            'verification' => $verification,
            'warnings' => array_values(array_filter([
                $unstaged['status'] === 'failed' ? 'Unable to read the unstaged diff.' : null,
                $staged['status'] === 'failed' ? 'Unable to read the staged diff.' : null,
            ])),
            'truncated' => $unstaged['truncated'] || $staged['truncated'] || $dependantsTruncated,
        ];

        Log::channel('nntmux_mcp')->info('MCP tool completed', [
            'tool' => 'inspect-change-impact',
            'safe_paths' => $paths,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'status' => 'completed',
            'truncated' => $payload['truncated'],
        ]);

        return $payload;
    }

    private function detectKind(string $query): string
    {
        if (str_contains($query, "\n") || preg_match('/(?:Exception|Error|SQLSTATE|Stack trace| at )/i', $query) === 1) {
            return 'error';
        }

        if (str_contains($query, '/') || preg_match('/\.(?:php|js|ts|css|json|md|xml|ya?ml)$/i', $query) === 1) {
            return 'path';
        }

        return 'symbol';
    }

    /**
     * @return list<string>
     */
    private function searchTerms(string $query, string $kind, ?string $path): array
    {
        if ($kind === 'path' && $path !== null) {
            return [preg_replace('/(?:\.blade)?\.[^.]+$/', '', basename($path)) ?? basename($path)];
        }

        if ($kind === 'symbol') {
            return [trim($query)];
        }

        preg_match_all('/(?:App\\\\[A-Za-z0-9_\\\\]+|app\/[A-Za-z0-9_\/.\-]+\.php|[A-Z][A-Za-z0-9_]+(?:Exception|Service|Controller|Command|Runner|Probe))/', $query, $matches);
        $terms = array_map(
            fn (string $term): string => basename(str_replace('\\', '/', preg_replace('/:\d+$/', '', $term) ?? $term), '.php'),
            $matches[0],
        );

        return array_slice(array_values(array_unique(array_filter($terms))), 0, 5) ?: [mb_substr(trim($query), 0, 120)];
    }

    /**
     * @param  list<string>  $terms
     * @return list<array{path: string, line: int, excerpt: string}>
     */
    private function search(array $terms, int $maximumResults): array
    {
        $roots = array_values(array_filter(
            (array) config('nntmux-mcp.allowed_roots', []),
            fn (string $root): bool => is_dir(base_path($root)),
        ));
        $matches = [];

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $result = $this->processes->run([
                'rg', '--line-number', '--no-heading', '--color=never', '--fixed-strings', '--', $term, ...$roots,
            ], (int) config('nntmux-mcp.timeouts.repository_seconds', 20));

            foreach (preg_split('/\R/', $result['output']) ?: [] as $line) {
                if (preg_match('/^([^:]+):(\d+):(.*)$/', $line, $parts) !== 1) {
                    continue;
                }

                $matches[] = [
                    'path' => $parts[1],
                    'line' => (int) $parts[2],
                    'excerpt' => $this->redactor->redact(trim($parts[3])),
                ];

                if (count($matches) >= $maximumResults) {
                    break 2;
                }
            }
        }

        return $this->uniqueMatches($matches);
    }

    /**
     * @param  list<array{path: string, line: int, excerpt: string}>  $matches
     * @return list<array{path: string, line: int, excerpt: string}>
     */
    private function uniqueMatches(array $matches): array
    {
        $unique = [];
        foreach ($matches as $match) {
            $unique[$match['path'].':'.$match['line']] = $match;
        }

        return array_values($unique);
    }

    private function architectureArea(string $path): string
    {
        foreach ((array) config('nntmux-mcp.architecture_areas', []) as $prefix => $description) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return (string) $description;
            }
        }

        return 'General project infrastructure';
    }

    /**
     * @param  list<string>  $paths
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function applicableRules(array $paths, array $terms): array
    {
        $rules = [];
        $indexPath = base_path('.ai/rules/index.md');
        if (is_file($indexPath)) {
            $rules[] = '.ai/rules/index.md';
            $index = (string) file_get_contents($indexPath);
            if (preg_match_all('/\|\s*`([^`]+)`\s*\|\s*\[[^]]+]\(\.\/([^)]+)\)/', $index, $rows, PREG_SET_ORDER)) {
                foreach ($rows as $row) {
                    foreach ($paths as $path) {
                        if ($this->globMatches($row[1], $path)) {
                            $rules[] = '.ai/rules/'.$row[2];
                        }
                    }
                }
            }
        }

        foreach (array_slice($terms, 0, 3) as $term) {
            if ($term === '' || ! is_dir(base_path('.ai/rules'))) {
                continue;
            }

            $result = $this->processes->run(
                ['rg', '--files-with-matches', '--ignore-case', '--fixed-strings', '--', $term, '.ai/rules'],
                (int) config('nntmux-mcp.timeouts.repository_seconds', 20),
            );
            $rules = [...$rules, ...(preg_split('/\R/', trim($result['output'])) ?: [])];
        }

        return array_values(array_unique(array_filter($rules, fn (string $rule): bool => $rule !== '')));
    }

    private function globMatches(string $glob, string $path): bool
    {
        if ($glob === '**') {
            return true;
        }

        $quoted = preg_quote($glob, '#');
        $pattern = str_replace(['\*\*', '\*'], ['.*', '[^/]*'], $quoted);

        return preg_match('#^'.$pattern.'$#', $path) === 1;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function configurationKeys(array $paths): array
    {
        $keys = [];
        foreach (array_slice($paths, 0, 20) as $path) {
            try {
                $safePath = $this->paths->normalize($path);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (! is_file(base_path($safePath))) {
                continue;
            }

            $contents = (string) file_get_contents(base_path($safePath));
            preg_match_all('/\b(?:config|env)\(\s*["\']([^"\']+)["\']/', $contents, $matches);
            $keys = [...$keys, ...$matches[1]];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{commit: string, subject: string}>
     */
    private function recentHistory(string $path): array
    {
        $result = $this->processes->run(
            ['git', 'log', '-n', '5', '--format=%h%x09%s', '--', $path],
            (int) config('nntmux-mcp.timeouts.repository_seconds', 20),
        );
        $history = [];

        foreach (preg_split('/\R/', trim($result['output'])) ?: [] as $line) {
            [$commit, $subject] = array_pad(explode("\t", $line, 2), 2, '');
            if ($commit !== '') {
                $history[] = ['commit' => $commit, 'subject' => $subject];
            }
        }

        return $history;
    }

    /**
     * @param  list<string>  $paths
     * @return array{command: list<string>, exit_code: int|null, duration_ms: int, status: string, output: string, truncated: bool}
     */
    private function gitDiff(array $paths, bool $cached): array
    {
        $command = ['git', 'diff', '--no-ext-diff', '--unified=3'];
        if ($cached) {
            $command[] = '--cached';
        }
        $command[] = '--';

        return $this->processes->run(
            [...$command, ...$paths],
            (int) config('nntmux-mcp.timeouts.repository_seconds', 20),
        );
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function affectedInterfaces(array $paths): array
    {
        $interfaces = [];
        foreach ($paths as $path) {
            $interfaces[] = match (true) {
                str_starts_with($path, 'routes/') => 'route registration and URL contracts',
                str_starts_with($path, 'app/Http/Controllers/Api/') => 'public API responses',
                str_starts_with($path, 'app/Console/Commands/') => 'Artisan command interface',
                str_starts_with($path, 'database/migrations/') => 'database schema',
                str_starts_with($path, 'config/'), $path === '.env.example' => 'runtime configuration',
                str_starts_with($path, 'resources/') => 'rendered frontend assets',
                str_starts_with($path, 'app/Mcp/') => 'MCP tool, resource, or prompt contract',
                default => 'internal application behavior',
            };
        }

        return array_values(array_unique($interfaces));
    }
}
