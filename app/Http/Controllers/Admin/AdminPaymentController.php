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

        $paymentStatuses = BtcPaymentController::paymentStatusesForAdminFilter();
        $invoiceStatuses = BtcPaymentController::invoiceStatusesForAdminFilter();

        $this->viewData = array_merge($this->viewData, [
            'payments' => $payments,
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
