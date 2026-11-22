<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminLoginAttemptListRequest;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Services\Security\IpBlockService;
use App\Services\Security\LoginAbuseDetector;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\View\View;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

class AdminLoginAttemptController extends BasePageController
{
    public function __construct(
        private readonly IpBlockService $blocks,
        private readonly LoginAbuseDetector $detector,
    ) {
        parent::__construct();
    }

    public function index(AdminLoginAttemptListRequest $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Login Attempts';
        $filters = $request->filters();

        $attempts = LoginAttempt::query()
            ->filter($filters)
            ->with(['authenticatable' => static fn (MorphTo $account): MorphTo => $account->withTrashed()])
            ->orderByDesc('id')
            ->paginate((int) config('nntmux.items_per_page', 50))
            ->withQueryString();

        $this->viewData = array_merge($this->viewData, [
            'attempts' => $attempts,
            'blockedIps' => $this->blockedIpsAmong($attempts),
            'deletedAccountLogins' => $this->deletedAccountLoginsAmong($attempts),
            'summary' => $this->lastDaySummary(),
            'filters' => $filters,
            'statuses' => LoginAttempt::STATUSES,
            'riskOptions' => AdminLoginAttemptListRequest::riskOptions(),
            'recordingEnabled' => (bool) config('suspicious-login.enabled'),
            'title' => $title,
            'meta_title' => $meta_title,
            'page_title' => $title,
        ]);

        return view('admin.login-attempts.index', $this->viewData);
    }

    /**
     * The addresses on this page that are blocked site-wide right now, as recorded on the attempts.
     *
     * @param  LengthAwarePaginator<int, LoginAttempt>  $attempts
     * @return list<string>
     */
    private function blockedIpsAmong(LengthAwarePaginator $attempts): array
    {
        $normalized = [];
        foreach (collect($attempts->items())->pluck('ip_address')->filter()->unique() as $ip) {
            $normalized[(string) $ip] = $this->blocks->normalize((string) $ip);
        }

        $addresses = array_values(array_unique(array_filter($normalized)));
        if ($addresses === []) {
            return [];
        }

        $blocked = BlockedIp::query()->active()->whereIn('ip_address', $addresses)->pluck('ip_address')->all();

        return array_keys(array_filter($normalized, static fn (?string $address): bool => in_array($address, $blocked, true)));
    }

    /**
     * The submitted logins on this page that found no account because the account was deleted,
     * lower-cased. Their owners are former members, not people guessing names.
     *
     * @param  LengthAwarePaginator<int, LoginAttempt>  $attempts
     * @return list<string>
     */
    private function deletedAccountLoginsAmong(LengthAwarePaginator $attempts): array
    {
        $identifiers = [];
        foreach ($attempts->items() as $attempt) {
            if ($attempt->authenticatable_id === null && is_string($attempt->identifier)) {
                $identifiers[] = $attempt->identifier;
            }
        }

        return $this->detector->deletedAccountLogins($identifiers);
    }

    /**
     * Attempts of the last 24 hours, regardless of the list's filters.
     *
     * @return array{total: int, failed: int, suspicious: int}
     */
    private function lastDaySummary(): array
    {
        $row = (array) LoginAttempt::query()->toBase()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN successful = 0 THEN 1 ELSE 0 END) AS failed')
            ->selectRaw('SUM(CASE WHEN risk_level IN (?, ?) THEN 1 ELSE 0 END) AS suspicious', [
                RiskLevel::Medium->value,
                RiskLevel::High->value,
            ])
            ->first();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'suspicious' => (int) ($row['suspicious'] ?? 0),
        ];
    }
}
