<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminLoginAttemptListRequest;
use App\Models\LoginAttempt;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\View\View;
use Spatie\SuspiciousLogin\Enums\RiskLevel;

class AdminLoginAttemptController extends BasePageController
{
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
