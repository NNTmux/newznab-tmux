<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AdminPaymentListRequest extends FormRequest
{
    /**
     * Period presets of the payments list, in display order.
     *
     * @var array<string, string>
     */
    public const array PERIODS = [
        'all' => 'All time',
        '7days' => 'Last 7 days',
        '30days' => 'Last 30 days',
        '90days' => 'Last 90 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_year' => 'Last year',
        'custom' => 'Custom range',
    ];

    /**
     * @var list<string>
     */
    public const array SORT_COLUMNS = [
        'id',
        'created_at',
        'username',
        'email',
        'item_description',
        'order_id',
        'invoice_amount',
        'payment_method',
        'payment_status',
        'invoice_status',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole('Admin');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'user' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:255'],
            'coin' => ['nullable', 'string', 'max:32'],
            'invoice_status' => ['nullable', 'in:'.implode(',', Payment::INVOICE_STATES)],
            'payment_status' => ['nullable', 'string', 'max:64'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'min:0'],
            'period' => ['nullable', 'in:'.implode(',', array_keys(self::PERIODS))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'payer' => ['nullable', 'in:returning,first_time'],
            'account' => ['nullable', 'in:linked,missing'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORT_COLUMNS)],
            'order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Filters for Payment::scopeFilter(), with the period preset resolved to dates.
     *
     * @return array{q: string, username: string, user: string, email: string, plan: string, coin: string, invoice_status: string, payment_status: string, amount_min: string, amount_max: string, start_date: string, end_date: string, payer: string, account: string}
     */
    public function filters(?CarbonImmutable $now = null): array
    {
        [$startDate, $endDate] = $this->dateRange($now);

        return [
            'q' => $this->scalarInput('q'),
            'username' => $this->scalarInput('username'),
            'user' => $this->scalarInput('user'),
            'email' => $this->scalarInput('email'),
            'plan' => $this->scalarInput('plan'),
            'coin' => $this->scalarInput('coin'),
            'invoice_status' => $this->scalarInput('invoice_status'),
            'payment_status' => $this->scalarInput('payment_status'),
            'amount_min' => $this->scalarInput('amount_min'),
            'amount_max' => $this->scalarInput('amount_max'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payer' => $this->scalarInput('payer'),
            'account' => $this->scalarInput('account'),
        ];
    }

    /**
     * The selected period. Dates without a period mean a custom range; no period and no dates mean all time.
     */
    public function period(): string
    {
        $period = $this->scalarInput('period');

        if (isset(self::PERIODS[$period])) {
            return $period;
        }

        return $this->scalarInput('start_date') !== '' || $this->scalarInput('end_date') !== '' ? 'custom' : 'all';
    }

    /**
     * Inclusive Y-m-d bounds of the selected period; an empty string is an open bound.
     *
     * @return array{0: string, 1: string}
     */
    public function dateRange(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->toDateString();

        return match ($this->period()) {
            '7days' => [$now->subDays(6)->toDateString(), $today],
            '30days' => [$now->subDays(29)->toDateString(), $today],
            '90days' => [$now->subDays(89)->toDateString(), $today],
            'this_month' => [$now->startOfMonth()->toDateString(), $today],
            'last_month' => [
                $now->startOfMonth()->subMonthNoOverflow()->toDateString(),
                $now->startOfMonth()->subDay()->toDateString(),
            ],
            'this_year' => [$now->startOfYear()->toDateString(), $today],
            'last_year' => [
                $now->startOfYear()->subYear()->toDateString(),
                $now->startOfYear()->subDay()->toDateString(),
            ],
            'custom' => [$this->scalarInput('start_date'), $this->scalarInput('end_date')],
            default => ['', ''],
        };
    }

    public function sort(): string
    {
        $sort = $this->scalarInput('sort');

        return \in_array($sort, self::SORT_COLUMNS, true) ? $sort : 'created_at';
    }

    public function order(): string
    {
        return $this->scalarInput('order') === 'asc' ? 'asc' : 'desc';
    }

    private function scalarInput(string $key): string
    {
        $value = $this->input($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
