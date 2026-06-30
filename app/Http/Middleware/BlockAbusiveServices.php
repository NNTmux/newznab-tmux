<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to block abusive streaming services and cloud providers.
 *
 * Blocks:
 * - AIOStreams (User-Agent based)
 * - UsenetStreamer (User-Agent based)
 * - Configured indexer apps on public indexer API/RSS endpoints (User-Agent based, opt-in)
 * - Oracle Cloud (ASN: AS31898)
 * - Cloudflare WARP (ASN: AS13335)
 */
class BlockAbusiveServices
{
    /**
     * User-Agent patterns to block (case-insensitive).
     *
     * @var array<string>
     */
    protected array $blockedUserAgents = [
        'aiostreams',
        'usenetstreamer',
        'stremio',
    ];

    /**
     * Blocked ASNs (Autonomous System Numbers).
     * Format: ASN number => Description
     *
     * @var array<int, string>
     */
    protected array $blockedAsns = [
        31898 => 'Oracle Cloud Infrastructure',
        13335 => 'Cloudflare WARP',
    ];

    /**
     * Cache TTL for ASN lookups (in seconds).
     * Default: 24 hours
     */
    protected int $cacheTtl = 86400;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        // Check for blocked User-Agents
        if ($this->isBlockedUserAgent($userAgent)) {
            Log::warning('Blocked abusive User-Agent', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'uri' => $request->getRequestUri(),
            ]);

            return $this->blockedResponse('Access denied: Streaming services are not allowed.');
        }

        if ($this->shouldBlockProxyIndexerApp($request, $userAgent)) {
            Log::warning('Blocked proxy indexer app request', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'uri' => $request->getRequestUri(),
            ]);

            return $this->blockedResponse('Access denied: Proxy indexer app access is not allowed.');
        }

        // Check for blocked ASNs
        $asnInfo = $this->getAsnInfo($ip);
        if ($asnInfo !== null && isset($this->blockedAsns[$asnInfo['asn']])) {
            Log::warning('Blocked ASN', [
                'ip' => $ip,
                'asn' => $asnInfo['asn'],
                'org' => $asnInfo['org'] ?? 'Unknown',
                'blocked_reason' => $this->blockedAsns[$asnInfo['asn']],
                'user_agent' => $userAgent,
                'uri' => $request->getRequestUri(),
            ]);

            return $this->blockedResponse(
                sprintf('Access denied: %s is not allowed.', $this->blockedAsns[$asnInfo['asn']])
            );
        }

        return $next($request);
    }

    /**
     * Check if the User-Agent matches any blocked patterns.
     */
    protected function isBlockedUserAgent(string $userAgent): bool
    {
        $lowerUserAgent = strtolower($userAgent);

        foreach ($this->blockedUserAgents as $pattern) {
            if (str_contains($lowerUserAgent, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an opt-in proxy indexer app block should apply.
     */
    protected function shouldBlockProxyIndexerApp(Request $request, string $userAgent): bool
    {
        if (! (bool) config('nntmux.block_proxy_indexer_apps', false)) {
            return false;
        }

        if (! $this->isPublicIndexerEndpoint($request)) {
            return false;
        }

        return $this->matchesAnyUserAgentPattern(
            $userAgent,
            $this->configuredProxyIndexerAppUserAgents()
        );
    }

    /**
     * Detect public indexer API/RSS endpoints while leaving unrelated requests alone.
     */
    protected function isPublicIndexerEndpoint(Request $request): bool
    {
        if ($request->is('api/v1/api') || $request->is('api/v2/*')) {
            return true;
        }

        return $request->is('rss/*') && ! $request->is('rss/health');
    }

    /**
     * @return array<int, string>
     */
    protected function configuredProxyIndexerAppUserAgents(): array
    {
        $configured = config('nntmux.block_proxy_indexer_app_user_agents', []);

        if (is_string($configured)) {
            $configured = preg_split('/[\r\n,]+/', $configured) ?: [];
        }

        if (! is_array($configured)) {
            Log::warning('Ignoring invalid proxy indexer app User-Agent configuration.', [
                'configured_type' => get_debug_type($configured),
            ]);

            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $pattern): string => is_string($pattern) ? trim($pattern) : '',
            $configured,
        )));
    }

    /**
     * @param  array<int, string>  $patterns
     */
    protected function matchesAnyUserAgentPattern(string $userAgent, array $patterns): bool
    {
        $lowerUserAgent = strtolower($userAgent);

        foreach ($patterns as $pattern) {
            if (str_contains($lowerUserAgent, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get ASN information for an IP address.
     * Uses ip-api.com with caching to minimize API calls.
     *
     * @return array{asn: int, org: string}|null
     */
    protected function getAsnInfo(string $ip): ?array
    {
        // Skip private/local IPs
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $cacheKey = 'asn_lookup_'.md5($ip);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip) {
            return $this->fetchAsnFromApi($ip);
        });
    }

    /**
     * Fetch ASN info from ip-api.com.
     *
     * @return array{asn: int, org: string}|null
     */
    protected function fetchAsnFromApi(string $ip): ?array
    {
        try {
            $response = Http::timeout(3)
                ->retry(2, 100)
                ->get("https://ip-api.com/json/{$ip}", [
                    'fields' => 'status,as,org',
                ]);

            if (! $response->successful()) {
                Log::debug('ASN lookup failed', ['ip' => $ip, 'status' => $response->status()]);

                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success' || empty($data['as'])) {
                return null;
            }

            // Parse ASN from "AS31898 Oracle Corporation" format
            if (preg_match('/^AS(\d+)/', $data['as'], $matches)) {
                return [
                    'asn' => (int) $matches[1],
                    'org' => $data['org'] ?? $data['as'],
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::debug('ASN lookup exception', ['ip' => $ip, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Generate a blocked response.
     */
    protected function blockedResponse(string $message): Response
    {
        return response()->json([
            'error' => true,
            'message' => $message,
        ], 403);
    }
}
