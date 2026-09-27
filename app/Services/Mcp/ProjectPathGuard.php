<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use InvalidArgumentException;

class ProjectPathGuard
{
    public function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('A non-empty repository-relative path is required.');
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new InvalidArgumentException('Absolute paths are not allowed.');
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $segments = explode('/', $path);

        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new InvalidArgumentException('Path traversal is not allowed.');
        }

        $this->assertAllowedLocation($path);
        $this->assertNotSensitive($path);

        $absolutePath = realpath(base_path($path));
        if ($absolutePath === false) {
            throw new InvalidArgumentException("Path [{$path}] does not exist.");
        }

        $projectRoot = realpath(base_path());
        if ($projectRoot === false || ($absolutePath !== $projectRoot && ! str_starts_with($absolutePath, $projectRoot.DIRECTORY_SEPARATOR))) {
            throw new InvalidArgumentException('The resolved path is outside the project root.');
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', ltrim(substr($absolutePath, strlen($projectRoot)), DIRECTORY_SEPARATOR));
    }

    /**
     * @param  array<int, string>  $paths
     * @return list<string>
     */
    public function normalizeMany(array $paths): array
    {
        $maximum = max(1, (int) config('nntmux-mcp.limits.max_paths', 20));
        if ($paths === [] || count($paths) > $maximum) {
            throw new InvalidArgumentException("Provide between 1 and {$maximum} paths.");
        }

        return array_values(array_unique(array_map($this->normalize(...), $paths)));
    }

    private function assertAllowedLocation(string $path): void
    {
        $rootFiles = (array) config('nntmux-mcp.allowed_root_files', []);
        if (in_array($path, $rootFiles, true)) {
            return;
        }

        $root = explode('/', $path, 2)[0];
        if (! in_array($root, (array) config('nntmux-mcp.allowed_roots', []), true)) {
            throw new InvalidArgumentException("Path [{$path}] is outside the allowed source roots.");
        }

        foreach ((array) config('nntmux-mcp.denied_segments', []) as $denied) {
            if ($path === $denied || str_starts_with($path, $denied.'/') || str_contains($path, '/'.$denied.'/')) {
                throw new InvalidArgumentException("Path [{$path}] is not available through MCP.");
            }
        }
    }

    private function assertNotSensitive(string $path): void
    {
        foreach (explode('/', $path) as $segment) {
            $lower = strtolower($segment);

            if ($lower === '.env' || (str_starts_with($lower, '.env.') && $lower !== '.env.example')) {
                throw new InvalidArgumentException('Environment files other than .env.example are not available through MCP.');
            }

            if (preg_match('/(?:credential|private[_-]?key|id_rsa|id_ed25519|\.pem$|\.p12$|\.pfx$)/i', $segment) === 1) {
                throw new InvalidArgumentException('Credential and private-key files are not available through MCP.');
            }
        }
    }
}
