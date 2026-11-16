<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminPaymentListRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\PaymentStatsReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends BasePageController
{
    /**
     * Labels of the filters shown as removable chips, keyed by query parameter.
     *
     * @var array<string, string>
     */
    private const array FILTER_LABELS = [
        'q' => 'Reference',
        'user' => 'Payer',
        'username' => 'Username',
        'email' => 'Email',
        'plan' => 'Plan',
        'coin' => 'Coin',
        'invoice_status' => 'State',
        'payment_status' => 'Payment status',
        'amount_min' => 'Min amount',
        'amount_max' => 'Max amount',
        'payer' => 'Payer type',
        'account' => 'Account',
    ];

    private const array CSV_HEADER = [
        'ID', 'Date', 'Username', 'Email', 'Plan', 'Amount', 'Coin', 'Method', 'Received',
        'Payment status', 'State', 'Order ID', 'Invoice ID', 'Payment ID',
    ];

    public function index(AdminPaymentListRequest $request, PaymentStatsReport $report): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Payments';
        $filters = $request->filters();
        $sort = $request->sort();
        $order = $request->order();

        $payments = $this->sorted(Payment::query()->filter($filters), $sort, $order)
            ->paginate((int) config('nntmux.items_per_page'))
            ->withQueryString();

        $usernames = $payments->getCollection()->pluck('username')->unique()->values();

        $this->viewData = array_merge($this->viewData, [
            'payments' => $payments,
            'report' => $report->build($filters),
            'payerCard' => $filters['user'] !== '' ? $report->payer($filters['user']) : null,
            'accounts' => $this->accountIds($usernames),
            'histories' => $this->paymentHistories($usernames),
            'options' => $report->filterOptions(),
            'filters' => $filters,
            'period' => $request->period(),
            'periods' => AdminPaymentListRequest::PERIODS,
            'activeFilters' => $this->activeFilters($request, $filters),
            'sort' => $sort,
            'order' => $order,
            'exportUrl' => route('admin.payment-list.export', $request->except('page')),
            'title' => $title,
            'meta_title' => $meta_title,
            'page_title' => $title,
        ]);

        return view('admin.payments.index', $this->viewData);
    }

    /**
     * Download the filtered payments as CSV, in the list's sort order.
     */
    public function export(AdminPaymentListRequest $request): StreamedResponse
    {
        $filters = $request->filters();
        $query = $this->sorted(Payment::query()->filter($filters), $request->sort(), $request->order());

        Log::channel('admin')->info('Payments exported to CSV', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'filters' => array_keys(array_filter($filters, static fn (string $value): bool => $value !== '')),
            'rows' => (clone $query)->count(),
        ]);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, self::CSV_HEADER, ',', '"', '');

            foreach ($query->cursor() as $payment) {
                fputcsv($output, array_map(self::csvCell(...), [
                    $payment->id,
                    $payment->created_at?->format('Y-m-d H:i:s'),
                    $payment->username,
                    $payment->email,
                    $payment->item_description,
                    $payment->invoice_amount,
                    Payment::coinFor($payment->payment_method),
                    $payment->payment_method,
                    $payment->payment_value,
                    $payment->payment_status,
                    $payment->invoiceState(),
                    $payment->order_id,
                    $payment->invoice_id,
                    $payment->payment_id,
                ]), ',', '"', '');
            }

            fclose($output);
        }, 'payments-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    private function sorted(Builder $query, string $sort, string $order): Builder
    {
        // Amounts are stored as text, so they need a numeric cast to sort by value.
        $sort === 'invoice_amount'
            ? $query->orderByRaw('CAST(payments.invoice_amount AS DECIMAL(20,8)) '.($order === 'asc' ? 'asc' : 'desc'))
            : $query->orderBy('payments.'.$sort, $order);

        return $sort === 'id' ? $query : $query->orderBy('payments.id', $order);
    }

    /**
     * User ids of the payers that still have an account, keyed by lowercased username.
     *
     * @param  Collection<int, mixed>  $usernames
     * @return array<string, int>
     */
    private function accountIds(Collection $usernames): array
    {
        if ($usernames->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('username', $usernames->all())
            ->get(['id', 'username'])
            ->mapWithKeys(static fn (User $user): array => [strtolower($user->username) => (int) $user->id])
            ->all();
    }

    /**
     * First payment id and payment count of each payer, keyed by lowercased username.
     *
     * @param  Collection<int, mixed>  $usernames
     * @return array<string, array{first_id: int, payments: int}>
     */
    private function paymentHistories(Collection $usernames): array
    {
        if ($usernames->isEmpty()) {
            return [];
        }

        $histories = [];
        $rows = Payment::query()->toBase()
            ->whereIn('username', $usernames->all())
            ->selectRaw('username, MIN(id) AS first_id, COUNT(*) AS payments')
            ->groupBy('username')
            ->get();

        foreach ($rows as $row) {
            $username = strtolower((string) $row->username);
            $histories[$username] = [
                'first_id' => min($histories[$username]['first_id'] ?? PHP_INT_MAX, (int) $row->first_id),
                'payments' => ($histories[$username]['payments'] ?? 0) + (int) $row->payments,
            ];
        }

        return $histories;
    }

    /**
     * Active filters as chips, each with the URL that removes it.
     *
     * @param  array<string, string>  $filters
     * @return list<array{label: string, value: string, remove_url: string}>
     */
    private function activeFilters(AdminPaymentListRequest $request, array $filters): array
    {
        $chips = [];

        foreach (self::FILTER_LABELS as $key => $label) {
            if ($filters[$key] !== '') {
                $chips[] = [
                    'label' => $label,
                    'value' => $key === 'payer' ? str_replace('_', '-', $filters[$key]) : $filters[$key],
                    'remove_url' => $request->fullUrlWithQuery([$key => null, 'page' => null]),
                ];
            }
        }

        $period = $request->period();
        if ($period !== 'all') {
            $chips[] = [
                'label' => 'Period',
                'value' => $period === 'custom'
                    ? ($filters['start_date'] ?: '…').' → '.($filters['end_date'] ?: '…')
                    : AdminPaymentListRequest::PERIODS[$period],
                'remove_url' => $request->fullUrlWithQuery(['period' => null, 'start_date' => null, 'end_date' => null, 'page' => null]),
            ];
        }

        return $chips;
    }

    /**
     * Stop spreadsheet applications from running a cell as a formula.
     */
    private static function csvCell(mixed $value): string
    {
        $cell = (string) $value;

        return $cell !== '' && str_contains("=+-@\t\r", $cell[0]) ? "'".$cell : $cell;
    }
}
