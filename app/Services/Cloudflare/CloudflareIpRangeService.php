<?php

declare(strict_types=1);

namespace App\Services\Cloudflare;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class CloudflareIpRangeService
{
    private const MANIFEST_VERSION = 1;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_PROXY_TOKENS = [
        'REMOTE_ADDR',
        'PRIVATE_SUBNETS',
        'private_ranges',
    ];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * Fetch the latest Cloudflare IP ranges and optionally persist them.
     *
     * @return array<string, mixed>
     */
    public function refresh(bool $persist = true): array
    {
        $ipv4 = $this->fetchRanges((string) config('trustedproxy.cloudflare.ipv4_url'));
        $ipv6 = $this->fetchRanges((string) config('trustedproxy.cloudflare.ipv6_url'));
        $proxies = $this->deduplicate(array_merge($ipv4, $ipv6));

        if ($proxies === []) {
            throw new RuntimeException('Cloudflare did not return any trusted proxy CIDRs.');
        }

        $manifest = [
            'version' => self::MANIFEST_VERSION,
            'updated_at' => now()->toIso8601String(),
            'sources' => [
                'ipv4_url' => (string) config('trustedproxy.cloudflare.ipv4_url'),
                'ipv6_url' => (string) config('trustedproxy.cloudflare.ipv6_url'),
            ],
            'ipv4' => $ipv4,
            'ipv6' => $ipv6,
            'proxies' => $proxies,
        ];

        if ($persist) {
            $this->writeManifest($manifest);
        }

        return $manifest;
    }

    /**
     * Return the currently effective trusted proxies.
     *
     * @return array<int, string>
     */
    public function trustedProxies(): array
    {
        $proxies = $this->deduplicate([
            ...$this->manualTrustedProxies(),
            ...$this->cloudflareTrustedProxies(),
        ]);

        if ($proxies !== []) {
            return $proxies;
        }

        if ((bool) config('trustedproxy.cloudflare.fallback_to_remote_addr', true)) {
            return ['REMOTE_ADDR'];
        }

        return [];
    }

    /**
     * Return manually configured trusted proxies.
     *
     * @return array<int, string>
     */
    public function manualTrustedProxies(): array
    {
        return $this->normalizeConfiguredProxies(config('trustedproxy.proxies'));
    }

    /**
     * Return persisted Cloudflare ranges when Cloudflare trust is enabled.
     *
     * @return array<int, string>
     */
    public function cloudflareTrustedProxies(): array
    {
        if (! (bool) config('trustedproxy.cloudflare.enabled', true)) {
            return [];
        }

        return $this->storedTrustedProxies();
    }

    public function manifestPath(): string
    {
        return $this->resolveManifestPath(
            (string) config('trustedproxy.cloudflare.storage_path', storage_path('app/cloudflare/trusted-proxies.json'))
        );
    }

    private function resolveManifestPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return storage_path('app/cloudflare/trusted-proxies.json');
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('~^[A-Za-z]:[\\/]~', $path) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function storedTrustedProxies(): array
    {
        $manifest = $this->readManifest();

        if ($manifest === null) {
            return [];
        }

        $ranges = $manifest['proxies'] ?? array_merge($manifest['ipv4'] ?? [], $manifest['ipv6'] ?? []);

        if (! is_array($ranges)) {
            Log::warning('Cloudflare trusted proxy manifest is missing proxy arrays.', [
                'path' => $this->manifestPath(),
            ]);

            return [];
        }

        try {
            return $this->normalizeRanges($ranges);
        } catch (RuntimeException $exception) {
            Log::warning('Ignoring invalid Cloudflare trusted proxy manifest.', [
                'path' => $this->manifestPath(),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(): ?array
    {
        $path = $this->manifestPath();

        if (! $this->files->exists($path)) {
            return null;
        }

        try {
            $decoded = json_decode($this->files->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Unable to decode Cloudflare trusted proxy manifest.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, string>
     */
    private function fetchRanges(string $url): array
    {
        if ($url === '') {
            throw new RuntimeException('Cloudflare IP list URL is not configured.');
        }

        $response = Http::accept('text/plain')
            ->connectTimeout((int) config('trustedproxy.cloudflare.connect_timeout', 5))
            ->retry(
                (int) config('trustedproxy.cloudflare.retry_times', 2),
                (int) config('trustedproxy.cloudflare.retry_sleep_ms', 250),
            )
            ->timeout((int) config('trustedproxy.cloudflare.timeout', 10))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Cloudflare IP refresh failed for [{$url}] with HTTP status {$response->status()}.");
        }

        $ranges = $this->parseRangesFromBody($response->body());

        if ($ranges === []) {
            throw new RuntimeException("Cloudflare IP refresh returned no valid CIDRs for [{$url}].");
        }

        return $ranges;
    }

    /**
     * @return array<int, string>
     */
    private function parseRangesFromBody(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];

        return $this->normalizeRanges($lines);
    }

    /**
     * @param  array<int, mixed>|string|null  $proxies
     * @return array<int, string>
     */
    private function normalizeConfiguredProxies(array|string|null $proxies): array
    {
        if ($proxies === null) {
            return [];
        }

        if (is_string($proxies)) {
            $proxies = preg_split('/[\s,]+/', $proxies) ?: [];
        }

        try {
            return $this->normalizeRanges($proxies, true);
        } catch (RuntimeException $exception) {
            Log::warning('Ignoring invalid manually configured trusted proxies.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $ranges
     * @return array<int, string>
     */
    private function normalizeRanges(array $ranges, bool $allowProxyTokens = false): array
    {
        $normalized = [];

        foreach ($ranges as $range) {
            if (! is_string($range)) {
                continue;
            }

            $range = trim($range);

            if ($range === '' || str_starts_with($range, '#')) {
                continue;
            }

            if ($allowProxyTokens && in_array($range, self::ALLOWED_PROXY_TOKENS, true)) {
                $normalized[] = $range === 'private_ranges' ? 'PRIVATE_SUBNETS' : $range;

                continue;
            }

            if (! $this->isValidCidr($range)) {
                throw new RuntimeException("Invalid trusted proxy entry [{$range}].");
            }

            $normalized[] = $range;
        }

        return $this->deduplicate($normalized);
    }

    private function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$address, $prefix] = explode('/', $cidr, 2);

        if (! ctype_digit($prefix)) {
            return false;
        }

        $isIpv4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isIpv6 = ! $isIpv4 && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! $isIpv4 && ! $isIpv6) {
            return false;
        }

        $maxPrefix = $isIpv4 ? 32 : 128;
        $prefixLength = (int) $prefix;

        return $prefixLength >= 0 && $prefixLength <= $maxPrefix;
    }

    /**
     * @param  array<int, string>  $ranges
     * @return array<int, string>
     */
    private function deduplicate(array $ranges): array
    {
        return array_values(array_unique($ranges));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function writeManifest(array $manifest): void
    {
        $path = $this->manifestPath();
        $directory = dirname($path);
        $temporaryPath = $path.'.tmp';

        $this->files->ensureDirectoryExists($directory);
        $this->files->put(
            $temporaryPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        if ($this->files->exists($path)) {
            $this->files->delete($path);
        }

        if (! @rename($temporaryPath, $path)) {
            $this->files->delete($temporaryPath);

            throw new RuntimeException("Unable to persist the Cloudflare trusted proxy manifest to [{$path}].");
        }
    }
}
