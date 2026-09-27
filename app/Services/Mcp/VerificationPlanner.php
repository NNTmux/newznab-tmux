<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class VerificationPlanner
{
    /**
     * @param  list<string>  $paths
     * @return array{checks: list<array{name: string, reason: string, command: string}>, test_paths: list<string>}
     */
    public function plan(array $paths): array
    {
        $checks = [];
        $phpPaths = array_values(array_filter(
            $paths,
            fn (string $path): bool => str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php'),
        ));
        $frontendPaths = array_values(array_filter($paths, fn (string $path): bool => $this->isFrontendPath($path)));
        $testPaths = $this->relatedTests($paths);

        if ($testPaths !== []) {
            $checks[] = [
                'name' => 'phpunit',
                'reason' => 'Run focused regression tests related to the changed paths.',
                'command' => 'vendor/bin/phpunit '.implode(' ', $testPaths),
            ];
        }

        if ($phpPaths !== []) {
            $checks[] = [
                'name' => 'pint',
                'reason' => 'Check changed PHP files against the project formatting rules without rewriting them.',
                'command' => 'vendor/bin/pint --test --format=agent '.implode(' ', $phpPaths),
            ];
            $checks[] = [
                'name' => 'phpstan',
                'reason' => 'Analyze the changed PHP surface with serial, sandbox-tolerant settings.',
                'command' => 'vendor/bin/phpstan analyse --memory-limit=2G --debug --no-progress '.implode(' ', $phpPaths),
            ];
            $checks[] = [
                'name' => 'php-lint',
                'reason' => 'Check each changed PHP file for syntax errors.',
                'command' => 'php -l <each changed PHP file>',
            ];
        }

        if ($frontendPaths !== []) {
            $checks[] = [
                'name' => 'frontend',
                'reason' => 'Compile Blade, JavaScript, CSS, and Vite inputs after frontend changes.',
                'command' => 'npm run build',
            ];
        }

        if (in_array('composer.json', $paths, true)) {
            $checks[] = [
                'name' => 'composer',
                'reason' => 'Validate Composer metadata after dependency changes.',
                'command' => 'composer validate --no-check-publish',
            ];
        }

        $checks[] = [
            'name' => 'diff',
            'reason' => 'Detect whitespace errors in both staged and unstaged changes for the selected paths.',
            'command' => 'git diff --check -- <paths> and git diff --cached --check -- <paths>',
        ];

        return [
            'checks' => $checks,
            'test_paths' => $testPaths,
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function relatedTests(array $paths): array
    {
        $tests = array_values(array_filter(
            $paths,
            fn (string $path): bool => str_starts_with($path, 'tests/') && str_ends_with($path, 'Test.php'),
        ));

        if ($this->containsMcpPath($paths)) {
            foreach (['tests/Feature/McpDevelopmentServerTest.php', 'tests/Feature/McpVerificationServicesTest.php'] as $mcpTest) {
                if (is_file(base_path($mcpTest))) {
                    $tests[] = $mcpTest;
                }
            }
        }
        $stems = array_values(array_unique(array_filter(array_map(
            function (string $path): string {
                $name = basename($path);

                return preg_replace('/(?:Test)?\.php$/', '', $name) ?? '';
            },
            $paths,
        ))));

        $testsRoot = base_path('tests');
        if (! is_dir($testsRoot)) {
            return $tests;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsRoot));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            foreach ($stems as $stem) {
                if ($stem !== '' && str_contains($file->getFilename(), $stem)) {
                    $tests[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1));
                    break;
                }
            }
        }

        return array_slice(array_values(array_unique($tests)), 0, 10);
    }

    private function isFrontendPath(string $path): bool
    {
        return str_starts_with($path, 'resources/views/')
            || str_starts_with($path, 'resources/js/')
            || str_starts_with($path, 'resources/css/')
            || in_array($path, ['package.json', 'vite.config.js'], true);
    }

    /**
     * @param  list<string>  $paths
     */
    private function containsMcpPath(array $paths): bool
    {
        return array_any($paths, fn (string $path): bool => str_starts_with($path, 'app/Mcp/')
            || str_starts_with($path, 'app/Services/Mcp/')
            || $path === 'config/nntmux-mcp.php'
            || $path === 'routes/ai.php');
    }
}
