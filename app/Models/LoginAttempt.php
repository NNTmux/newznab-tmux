<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\SuspiciousLogin\Enums\RiskLevel;
use Spatie\SuspiciousLogin\Models\LoginAttempt as BaseLoginAttempt;

/**
 * A login attempt recorded by spatie/laravel-suspicious-login, with the filters of the admin list.
 */
class LoginAttempt extends BaseLoginAttempt
{
    public const string STATUS_SUCCESSFUL = 'successful';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_BLOCKED = 'blocked';

    /**
     * Results an attempt can be filtered by.
     *
     * @var list<string>
     */
    public const array STATUSES = [self::STATUS_SUCCESSFUL, self::STATUS_FAILED, self::STATUS_BLOCKED];

    /** Risk filter that matches every level above low. */
    public const string RISK_SUSPICIOUS = 'suspicious';

    /**
     * Filters of the admin login attempts list; empty values are ignored.
     *
     * @param  Builder<LoginAttempt>  $query
     * @param  array<string, string>  $filters  Keys: status, user, user_id, ip, risk, date_from, date_to.
     * @return Builder<LoginAttempt>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $value = static fn (string $key): string => trim((string) ($filters[$key] ?? ''));
        $userType = (new User)->getMorphClass();

        match ($value('status')) {
            self::STATUS_SUCCESSFUL => $query->where('login_attempts.successful', true)->where('login_attempts.blocked', false),
            self::STATUS_FAILED => $query->where('login_attempts.successful', false),
            self::STATUS_BLOCKED => $query->where('login_attempts.blocked', true),
            default => null,
        };

        if (ctype_digit($value('user_id'))) {
            $query->where('login_attempts.authenticatable_type', $userType)
                ->where('login_attempts.authenticatable_id', (int) $value('user_id'));
        }

        if ($value('user') !== '') {
            $like = '%'.$value('user').'%';
            $accounts = User::withTrashed()->select('id')->where(function (Builder $users) use ($like): void {
                $users->where('username', 'like', $like)->orWhere('email', 'like', $like);
            });

            $query->where(function (Builder $match) use ($like, $userType, $accounts): void {
                $match->where('login_attempts.identifier', 'like', $like)
                    ->orWhere(function (Builder $owned) use ($userType, $accounts): void {
                        $owned->where('login_attempts.authenticatable_type', $userType)
                            ->whereIn('login_attempts.authenticatable_id', $accounts);
                    });
            });
        }

        if ($value('ip') !== '') {
            filter_var($value('ip'), FILTER_VALIDATE_IP) !== false
                ? $query->where('login_attempts.ip_address', $value('ip'))
                : $query->where('login_attempts.ip_address', 'like', $value('ip').'%');
        }

        if ($value('risk') === self::RISK_SUSPICIOUS) {
            $query->whereIn('login_attempts.risk_level', [RiskLevel::Medium->value, RiskLevel::High->value]);
        } elseif (RiskLevel::tryFrom($value('risk')) !== null) {
            $query->where('login_attempts.risk_level', $value('risk'));
        }

        if (Carbon::canBeCreatedFromFormat($value('date_from'), 'Y-m-d')) {
            $query->where('login_attempts.created_at', '>=', Carbon::parse($value('date_from'))->startOfDay());
        }

        if (Carbon::canBeCreatedFromFormat($value('date_to'), 'Y-m-d')) {
            $query->where('login_attempts.created_at', '<=', Carbon::parse($value('date_to'))->endOfDay());
        }

        return $query;
    }
}
