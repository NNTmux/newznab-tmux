<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Figures for the admin payments page and dashboard: totals, plan and coin breakdowns
 * and a revenue trend for a filtered set of payments.
 *
 * Revenue is the invoice price of settled and legacy payments; unsettled invoices are
 * counted as payments but reported separately. Rows are aggregated in PHP because the
 * amounts are stored as text and coins and months have no SQL shared by MariaDB and SQLite.
 *
 * @phpstan-type PaymentRow array{id: int, at: ?CarbonImmutable, payer: string, plan: string, coin: string, amount: float, received: float, state: string}
 * @phpstan-type Breakdown array{label: string, payments: int, revenue: float, share: float, received: float}
 */
final class PaymentStatsReport
{
    /**
     * A bounded range of at most this many days is charted per day instead of per month.
     */
    private const int DAILY_TREND_MAX_DAYS = 62;

    private const int TREND_MAX_BUCKETS = 120;

    /**
     * @param  array<string, string>  $filters  Filters accepted by Payment::scopeFilter().
     * @return array{
     *     summary: array{revenue: float, payments: int, paid: int, average: float, payers: int, returning: int, unsettled: int, unsettled_amount: float, legacy: int, previous: ?array{start: string, end: string, revenue: float, payments: int, revenue_change: ?float, payments_change: ?float}},
     *     plans: list<Breakdown>,
     *     coins: list<Breakdown>,
     *     trend: array{unit: string, labels: list<string>, revenue: list<float>, payments: list<int>}
     * }
     */
    public function build(array $filters, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rows = $this->rows($filters);

        return [
            'summary' => $this->summary($rows, $filters),
            'plans' => $this->breakdown($rows, 'plan'),
            'coins' => $this->breakdown($rows, 'coin'),
            'trend' => $this->trend($rows, $filters, $now),
        ];
    }

    /**
     * Values present in the table, for the filter dropdowns.
     *
     * @return array{plans: list<string>, coins: list<string>, payment_statuses: list<string>}
     */
    public function filterOptions(): array
    {
        $distinct = static fn (string $column): Collection => Payment::query()
            ->distinct()
            ->pluck($column)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '');

        return [
            'plans' => $distinct('item_description')->unique()->sort()->values()->all(),
            'coins' => $distinct('payment_method')
                ->map(static fn (string $method): string => Payment::coinFor($method))
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'payment_statuses' => $distinct('payment_status')->unique()->sort()->values()->all(),
        ];
    }

    /**
     * Everything one payer has paid, regardless of the other filters; null when they have no payments.
     *
     * @return ?array{username: string, payments: int, revenue: float, first_at: ?CarbonImmutable, last_at: ?CarbonImmutable, user: ?User}
     */
    public function payer(string $username): ?array
    {
        $rows = $this->rows(['user' => $username]);

        if ($rows === []) {
            return null;
        }

        $dates = array_values(array_filter(array_column($rows, 'at')));

        return [
            'username' => $username,
            'payments' => count($rows),
            'revenue' => self::revenue($rows),
            'first_at' => $dates === [] ? null : min($dates),
            'last_at' => $dates === [] ? null : max($dates),
            'user' => User::query()->with('role')->where('username', $username)->first(),
        ];
    }

    /**
     * Revenue and payment counts for the admin dashboard cards.
     *
     * @return array{revenue_30d: float, count_30d: int, revenue_year: float, count_year: int}
     */
    public function dashboardStats(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $last30Days = $now->subDays(30);
        $thisYear = $now->startOfYear();

        $rows = $this->rows([], ['id', 'created_at', 'invoice_amount', 'invoice_status'], min($last30Days, $thisYear));
        $since = static fn (CarbonImmutable $start): array => array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['at'] !== null && $row['at']->gte($start),
        ));

        return [
            'revenue_30d' => self::revenue($since($last30Days)),
            'count_30d' => count($since($last30Days)),
            'revenue_year' => self::revenue($since($thisYear)),
            'count_year' => count($since($thisYear)),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @param  list<string>  $columns
     * @return list<PaymentRow>
     */
    private function rows(array $filters, array $columns = ['id', 'created_at', 'username', 'item_description', 'payment_method', 'invoice_amount', 'payment_value', 'invoice_status'], ?CarbonImmutable $since = null): array
    {
        $query = Payment::query()->filter($filters)->orderBy('payments.id');

        if ($since !== null) {
            $query->where('payments.created_at', '>=', $since);
        }

        return $query->get($columns)
            ->map(static fn (Payment $payment): array => [
                'id' => (int) $payment->id,
                'at' => $payment->created_at?->toImmutable(),
                'payer' => strtolower(trim((string) $payment->username)),
                'plan' => trim((string) $payment->item_description) ?: 'Unknown',
                'coin' => Payment::coinFor($payment->payment_method),
                'amount' => self::number($payment->invoice_amount),
                'received' => self::number($payment->payment_value),
                'state' => $payment->invoiceState(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<PaymentRow>  $rows
     * @param  array<string, string>  $filters
     * @return array{revenue: float, payments: int, paid: int, average: float, payers: int, returning: int, unsettled: int, unsettled_amount: float, legacy: int, previous: ?array{start: string, end: string, revenue: float, payments: int, revenue_change: ?float, payments_change: ?float}}
     */
    private function summary(array $rows, array $filters): array
    {
        $revenue = self::revenue($rows);
        $unsettled = array_values(array_filter($rows, static fn (array $row): bool => $row['state'] === Payment::STATE_UNSETTLED));
        $paid = count($rows) - count($unsettled);
        $firstPayments = $rows === [] ? [] : $this->firstPaymentIds();

        return [
            'revenue' => $revenue,
            'payments' => count($rows),
            'paid' => $paid,
            'average' => $paid > 0 ? round($revenue / $paid, 2) : 0.0,
            'payers' => count(array_unique(array_column($rows, 'payer'))),
            'returning' => count(array_filter($rows, static fn (array $row): bool => ($firstPayments[$row['payer']] ?? $row['id']) !== $row['id'])),
            'unsettled' => count($unsettled),
            'unsettled_amount' => round(array_sum(array_column($unsettled, 'amount')), 2),
            'legacy' => count(array_filter($rows, static fn (array $row): bool => $row['state'] === Payment::STATE_LEGACY)),
            'previous' => $this->previousPeriod($rows, $filters),
        ];
    }

    /**
     * The same filters over the period of equal length just before a bounded date range.
     *
     * @param  list<PaymentRow>  $rows
     * @param  array<string, string>  $filters
     * @return ?array{start: string, end: string, revenue: float, payments: int, revenue_change: ?float, payments_change: ?float}
     */
    private function previousPeriod(array $rows, array $filters): ?array
    {
        $range = $this->boundedRange($filters);

        if ($range === null) {
            return null;
        }

        [$start, $end] = $range;
        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days - 1);
        $previousRows = $this->rows(array_merge($filters, [
            'start_date' => $previousStart->toDateString(),
            'end_date' => $previousEnd->toDateString(),
        ]));
        $previousRevenue = self::revenue($previousRows);

        return [
            'start' => $previousStart->toDateString(),
            'end' => $previousEnd->toDateString(),
            'revenue' => $previousRevenue,
            'payments' => count($previousRows),
            'revenue_change' => self::change(self::revenue($rows), $previousRevenue),
            'payments_change' => self::change((float) count($rows), (float) count($previousRows)),
        ];
    }

    /**
     * Payments, revenue and revenue share per plan or coin, highest revenue first.
     *
     * @param  list<PaymentRow>  $rows
     * @param  'plan'|'coin'  $key
     * @return list<Breakdown>
     */
    private function breakdown(array $rows, string $key): array
    {
        $total = self::revenue($rows);
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row[$key]][] = $row;
        }

        $breakdown = [];
        foreach ($groups as $label => $group) {
            $revenue = self::revenue($group);
            $breakdown[] = [
                'label' => (string) $label,
                'payments' => count($group),
                'revenue' => $revenue,
                'share' => $total > 0 ? round($revenue / $total * 100, 1) : 0.0,
                'received' => (float) array_sum(array_column($group, 'received')),
            ];
        }

        usort($breakdown, static fn (array $a, array $b): int => [$b['revenue'], $b['payments'], $a['label']] <=> [$a['revenue'], $a['payments'], $b['label']]);

        return $breakdown;
    }

    /**
     * Revenue and payment count per month, or per day for a short bounded range, zero-filled.
     *
     * @param  list<PaymentRow>  $rows
     * @param  array<string, string>  $filters
     * @return array{unit: string, labels: list<string>, revenue: list<float>, payments: list<int>}
     */
    private function trend(array $rows, array $filters, CarbonImmutable $now): array
    {
        $dated = array_values(array_filter($rows, static fn (array $row): bool => $row['at'] !== null));

        if ($dated === []) {
            return ['unit' => 'month', 'labels' => [], 'revenue' => [], 'payments' => []];
        }

        $range = $this->boundedRange($filters);
        $daily = $range !== null && (int) $range[0]->diffInDays($range[1]) + 1 <= self::DAILY_TREND_MAX_DAYS;
        $keyFormat = $daily ? 'Y-m-d' : 'Y-m';
        $labelFormat = $daily ? 'j M' : 'M Y';

        $start = self::date($filters['start_date'] ?? '') ?? min(array_column($dated, 'at'));
        $end = self::date($filters['end_date'] ?? '') ?? max($now, max(array_column($dated, 'at')));
        $cursor = $daily ? $start->startOfDay() : $start->startOfMonth();
        $last = $daily ? $end->startOfDay() : $end->startOfMonth();

        $buckets = [];
        while ($cursor->lte($last)) {
            $buckets[$cursor->format($keyFormat)] = ['label' => $cursor->format($labelFormat), 'revenue' => 0.0, 'payments' => 0];
            $cursor = $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow();
        }
        $buckets = \array_slice($buckets, -self::TREND_MAX_BUCKETS, null, true);

        foreach ($dated as $row) {
            $key = $row['at']->format($keyFormat);

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['payments']++;
            if ($row['state'] !== Payment::STATE_UNSETTLED) {
                $buckets[$key]['revenue'] += $row['amount'];
            }
        }

        return [
            'unit' => $daily ? 'day' : 'month',
            'labels' => array_column($buckets, 'label'),
            'revenue' => array_map(static fn (array $bucket): float => round($bucket['revenue'], 2), array_values($buckets)),
            'payments' => array_column($buckets, 'payments'),
        ];
    }

    /**
     * Id of each payer's first payment, keyed by lowercased username.
     *
     * @return array<string, int>
     */
    private function firstPaymentIds(): array
    {
        $first = [];

        foreach (Payment::query()->toBase()->selectRaw('username, MIN(id) AS first_id')->groupBy('username')->get() as $payer) {
            $username = strtolower(trim((string) $payer->username));
            $first[$username] = min($first[$username] ?? PHP_INT_MAX, (int) $payer->first_id);
        }

        return $first;
    }

    /**
     * @param  array<string, string>  $filters
     * @return ?array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function boundedRange(array $filters): ?array
    {
        $start = self::date($filters['start_date'] ?? '');
        $end = self::date($filters['end_date'] ?? '');

        return $start !== null && $end !== null && $start->lte($end) ? [$start, $end] : null;
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param  list<PaymentRow>  $rows
     */
    private static function revenue(array $rows): float
    {
        $revenue = 0.0;

        foreach ($rows as $row) {
            if ($row['state'] !== Payment::STATE_UNSETTLED) {
                $revenue += $row['amount'];
            }
        }

        return round($revenue, 2);
    }

    private static function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private static function change(float $current, float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }
}
