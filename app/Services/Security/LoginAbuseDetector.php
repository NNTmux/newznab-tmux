<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Finds addresses that try to sign in to several accounts that do not exist, from the
 * login_attempts rows recorded by spatie/laravel-suspicious-login, and blocks them.
 *
 * A failed attempt has no account when authenticatable_id is null. Attempts are counted per
 * address and per device cookie together, so a visitor who switches address between guesses
 * is still counted as one.
 */
class LoginAbuseDetector
{
    public function __construct(private readonly IpBlockService $blocks) {}

    /**
     * Block the address, and every other address its device used, when it crossed the
     * threshold. Returns the blocks this call created.
     *
     * @return list<BlockedIp>
     */
    public function evaluate(string $ip, ?string $deviceFingerprint = null): array
    {
        $finding = $this->inspect($ip, $deviceFingerprint);
        if ($finding === null) {
            return [];
        }

        $reason = sprintf(
            'Tried %d accounts that do not exist within %d hours',
            count($finding['identifiers']),
            $this->windowHours(),
        );

        $created = [];
        foreach ($finding['ips'] as $address) {
            if ($this->blocks->isBlocked($address)) {
                continue;
            }

            $block = $this->blocks->blockAutomatically($address, $reason, $deviceFingerprint);
            if ($block === null) {
                continue;
            }

            $created[] = $block;
            Log::warning('IP blocked after sign-in attempts for non-existent accounts', [
                'ip' => $block->ip_address,
                'distinct_accounts' => count($finding['identifiers']),
                'block_count' => $block->block_count,
                'expires_at' => $block->expires_at?->toIso8601String(),
            ]);
        }

        return $created;
    }

    /**
     * What the address did, when that crosses the threshold; null otherwise. Changes nothing.
     *
     * @return array{identifiers: list<string>, ips: list<string>}|null The distinct non-existent
     *                                                                  accounts tried, and every address involved.
     */
    public function inspect(string $ip, ?string $deviceFingerprint = null): ?array
    {
        $deviceFingerprint = $deviceFingerprint === '' ? null : $deviceFingerprint;
        $threshold = max(1, (int) config('nntmux.ip_blocking.unknown_account_threshold', 3));

        $attempts = LoginAttempt::query()
            ->where('successful', false)
            ->whereNull('authenticatable_id')
            ->whereNotNull('identifier')
            ->where('created_at', '>', $this->countFrom($ip, $deviceFingerprint))
            ->where(static function (Builder $query) use ($ip, $deviceFingerprint): void {
                $query->where('ip_address', $ip);
                if ($deviceFingerprint !== null) {
                    $query->orWhere('device_fingerprint', $deviceFingerprint);
                }
            })
            ->toBase()
            ->get(['identifier', 'ip_address']);

        $identifiers = $attempts->pluck('identifier')
            ->map(static fn (mixed $identifier): string => trim((string) $identifier))
            ->filter(static fn (string $identifier): bool => $identifier !== '')
            ->unique(static fn (string $identifier): string => Str::lower($identifier))
            ->values()
            ->all();

        if (count($identifiers) < $threshold) {
            return null;
        }

        $identifiers = $this->withoutDeletedAccounts($identifiers);
        if (count($identifiers) < $threshold) {
            return null;
        }

        $ips = $attempts->pluck('ip_address')
            ->prepend($ip)
            ->filter(static fn (mixed $address): bool => is_string($address) && $address !== '')
            ->unique()
            ->values()
            ->all();

        return ['identifiers' => $identifiers, 'ips' => $ips];
    }

    public function windowHours(): int
    {
        return max(1, (int) config('nntmux.ip_blocking.window_hours', 24));
    }

    /**
     * Attempts count from the start of the window, or from the end of the most recent block
     * on this address or device if that is later. Without this, lifting a block would be
     * undone by the next single mistake, because the old attempts would still add up.
     */
    private function countFrom(string $ip, ?string $deviceFingerprint): Carbon
    {
        $windowStart = now()->subHours($this->windowHours());
        $address = $this->blocks->normalize($ip) ?? $ip;

        $lastBlockEnd = BlockedIp::query()
            ->where('expires_at', '<=', now())
            ->where(static function (Builder $query) use ($address, $deviceFingerprint): void {
                $query->where('ip_address', $address);
                if ($deviceFingerprint !== null) {
                    $query->orWhere('device_fingerprint', $deviceFingerprint);
                }
            })
            ->max('expires_at');

        if ($lastBlockEnd === null) {
            return $windowStart;
        }

        $lastBlockEnd = Carbon::parse($lastBlockEnd);

        return $lastBlockEnd->greaterThan($windowStart) ? $lastBlockEnd : $windowStart;
    }

    /**
     * A sign-in to a deleted account is recorded like one to an account that never existed.
     * Its former owner is not guessing, so those names do not count.
     *
     * @param  list<string>  $identifiers
     * @return list<string>
     */
    private function withoutDeletedAccounts(array $identifiers): array
    {
        // Both spellings are sent so the match also works on a case-sensitive database.
        $candidates = array_values(array_unique([...$identifiers, ...array_map(Str::lower(...), $identifiers)]));

        $deleted = [];
        $accounts = User::onlyTrashed()
            ->where(static function (Builder $query) use ($candidates): void {
                $query->whereIn('username', $candidates)->orWhereIn('email', $candidates);
            })
            ->toBase()
            ->get(['username', 'email']);

        foreach ($accounts as $account) {
            $deleted[Str::lower((string) $account->username)] = true;
            $deleted[Str::lower((string) $account->email)] = true;
        }

        return array_values(array_filter(
            $identifiers,
            static fn (string $identifier): bool => ! isset($deleted[Str::lower($identifier)]),
        ));
    }
}
