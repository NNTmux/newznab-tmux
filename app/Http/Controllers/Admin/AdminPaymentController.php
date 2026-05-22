<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Controllers\BtcPaymentController;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentController extends BasePageController
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_SORT_COLUMNS = [
        'id',
        'created_at',
        'username',
        'email',
        'invoice_amount',
        'payment_status',
        'invoice_status',
        'order_id',
    ];

    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Payments';

        $filters = [
            'username' => $request->string('username')->trim()->value(),
            'email' => $request->string('email')->trim()->value(),
            'payment_status' => $request->string('payment_status')->trim()->value(),
            'invoice_status' => $request->string('invoice_status')->trim()->value(),
            'start_date' => $request->string('start_date')->trim()->value(),
            'end_date' => $request->string('end_date')->trim()->value(),
        ];

        $sort = $request->string('sort')->value();
        if (! \in_array($sort, self::ALLOWED_SORT_COLUMNS, true)) {
            $sort = 'created_at';
        }

        $order = strtolower($request->string('order')->value()) === 'asc' ? 'asc' : 'desc';

        $payments = Payment::query()
            ->filter($filters)
            ->orderBy($sort, $order)
            ->paginate((int) config('nntmux.items_per_page'))
            ->withQueryString();

        $summary = Payment::query()
            ->filter($filters)
            ->selectRaw("
                COALESCE(NULLIF(payment_method, ''), 'Unknown') AS method,
                COUNT(*) AS tx_count,
                COALESCE(SUM(CAST(NULLIF(invoice_amount, '') AS DECIMAL(20,8))), 0) AS invoice_total,
                COALESCE(SUM(CAST(NULLIF(payment_value, '') AS DECIMAL(20,8))), 0) AS value_total
            ")
            ->groupBy('method')
            ->orderBy('method')
            ->get();

        $summaryTotals = [
            'tx_count' => (int) $summary->sum('tx_count'),
            'invoice_total' => (float) $summary->sum(fn ($r) => (float) $r->invoice_total),
            'value_total' => (float) $summary->sum(fn ($r) => (float) $r->value_total),
        ];

        $paymentStatuses = BtcPaymentController::paymentStatusesForAdminFilter();
        $invoiceStatuses = BtcPaymentController::invoiceStatusesForAdminFilter();

        $this->viewData = array_merge($this->viewData, [
            'payments' => $payments,
            'summary' => $summary,
            'summaryTotals' => $summaryTotals,
            'filters' => $filters,
            'sort' => $sort,
            'order' => $order,
            'paymentStatuses' => $paymentStatuses,
            'invoiceStatuses' => $invoiceStatuses,
            'title' => $title,
            'meta_title' => $meta_title,
            'page_title' => $title,
        ]);

        return view('admin.payments.index', $this->viewData);
    }
}
