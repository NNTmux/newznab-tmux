<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\IpBlockSource;
use App\Models\BlockedIp;
use App\Services\Cloudflare\CloudflareIpRangeService;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The site-wide IP block list: answers "is this address blocked" for the DenyBlockedIps
 * middleware on every request, and is the only place that adds or lifts a block.
 */
class IpBlockService
{
    public const string CACHE_KEY = 'blocked_ips:active';

    private const int CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly CloudflareIpRangeService $cloudflareRanges) {}

    /**
     * Whether the address is blocked right now. Fails open: if neither the cache nor the
     * database can be read, nobody is treated as blocked.
     */
    public function isBlocked(string $ip): bool
    {
        $ip = $this->normalize($ip);
        if ($ip === null) {
            return false;
        }

        $blocks = $this->activeBlocks();
        if (! array_key_exists($ip, $blocks)) {
            return false;
        }

        return $blocks[$ip] === null || $blocks[$ip] > now()->getTimestamp();
    }

    /**
     * Block an address after detected abuse. The first block lasts ip_blocking.block_hours and
     * each later one doubles, up to ip_blocking.max_block_hours. A block already in force is
     * returned unchanged. Returns null when the address may not be blocked.
     */
    public function blockAutomatically(string $ip, string $reason, ?string $deviceFingerprint = null): ?BlockedIp
    {
        $ip = $this->normalize($ip);
        if ($ip === null || $this->isProtected($ip)) {
            return null;
        }

        $existing = BlockedIp::query()->where('ip_address', $ip)->first();
        if ($existing !== null && $existing->isActive()) {
            return $existing;
        }

        $blockCount = ($existing === null ? 0 : $existing->block_count) + 1;

        return $this->store($ip, $existing, [
            'source' => IpBlockSource::Auto,
            'reason' => $reason,
            'device_fingerprint' => $deviceFingerprint,
            'block_count' => $blockCount,
            'expires_at' => now()->addHours($this->escalatedHours($blockCount)),
            'created_by' => null,
        ]);
    }

    /**
     * Block an address by hand, replacing any block already in force. A null expiry is
     * permanent. Returns null when the address may not be blocked.
     */
    public function blockManually(string $ip, ?CarbonInterface $expiresAt = null, ?string $reason = null, ?int $createdBy = null): ?BlockedIp
    {
        $ip = $this->normalize($ip);
        if ($ip === null || $this->isProtected($ip)) {
            return null;
        }

        $existing = BlockedIp::query()->where('ip_address', $ip)->first();
        $blockCount = match (true) {
            $existing === null => 1,
            $existing->isActive() => $existing->block_count,
            default => $existing->block_count + 1,
        };

        return $this->store($ip, $existing, [
            'source' => IpBlockSource::Manual,
            'reason' => $reason,
            'device_fingerprint' => null,
            'block_count' => $blockCount,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * End the block on an address now. Returns false when it was not blocked.
     */
    public function lift(string $ip): bool
    {
        $ip = $this->normalize($ip);
        if ($ip === null) {
            return false;
        }

        $lifted = BlockedIp::query()->active()->where('ip_address', $ip)->update(['expires_at' => now()]);
        $this->forgetCache();

        return $lifted > 0;
    }

    /**
     * Addresses that must never be blocked: anything that is not a public address, the
     * proxies in front of the site (blocking one would block every visitor behind it), and
     * the ip_blocking.allowlist.
     */
    public function isProtected(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        $ranges = array_values(array_filter(
            [...$this->allowlist(), ...$this->cloudflareRanges->trustedProxies()],
            static fn (string $range): bool => filter_var(explode('/', $range, 2)[0], FILTER_VALIDATE_IP) !== false,
        ));

        return $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }

    /**
     * Canonical text form of an address (IPv6 is compressed and lower-cased), or null when
     * the value is not an IP address.
     */
    public function normalize(string $ip): ?string
    {
        $packed = @inet_pton(trim($ip));
        if ($packed === false) {
            return null;
        }

        $normalized = inet_ntop($packed);

        return $normalized === false ? null : $normalized;
    }

    public function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $throwable) {
            $this->logBypass('IP block cache invalidation bypassed', $throwable);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(string $ip, ?BlockedIp $existing, array $attributes): BlockedIp
    {
        $attributes['blocked_at'] = now();

        try {
            $block = $existing ?? new BlockedIp(['ip_address' => $ip]);
            $block->fill($attributes)->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request blocked the same address first; its row stands.
            $block = BlockedIp::query()->where('ip_address', $ip)->firstOrFail();
        }

        $this->forgetCache();

        return $block;
    }

    /**
     * Blocks in force, keyed by address; the value is the expiry timestamp, or null when permanent.
     *
     * @return array<string, int|null>
     */
    private function activeBlocks(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable $throwable) {
            $this->logBypass('IP block cache read bypassed', $throwable);
        }

        try {
            $blocks = [];
            foreach (BlockedIp::query()->active()->get(['ip_address', 'expires_at']) as $block) {
                $blocks[$block->ip_address] = $block->expires_at?->getTimestamp();
            }
        } catch (\Throwable $throwable) {
            $this->logBypass('IP block list unavailable; allowing the request', $throwable);

            return [];
        }

        try {
            Cache::put(self::CACHE_KEY, $blocks, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $throwable) {
            $this->logBypass('IP block cache write bypassed', $throwable);
        }

        return $blocks;
    }

    private function escalatedHours(int $blockCount): int
    {
        $baseHours = max(1, (int) config('nntmux.ip_blocking.block_hours', 24));
        $maxHours = max($baseHours, (int) config('nntmux.ip_blocking.max_block_hours', 720));

        // The exponent is capped so a long-lived repeat offender cannot overflow the shift.
        return min($maxHours, $baseHours * (2 ** min($blockCount - 1, 16)));
    }

    /**
     * @return list<string>
     */
    private function allowlist(): array
    {
        return array_values(array_filter(
            (array) config('nntmux.ip_blocking.allowlist', []),
            static fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));
    }

    /**
     * Runs on the fail-open path of every request, so it must not throw either.
     */
    private function logBypass(string $message, \Throwable $throwable): void
    {
        if (! config('app.debug')) {
            return;
        }

        try {
            Log::debug($message.': '.$throwable->getMessage());
        } catch (\Throwable) {
            // Nothing left to report to.
        }
    }
}
