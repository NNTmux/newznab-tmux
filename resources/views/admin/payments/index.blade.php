@extends('layouts.admin')

@section('title', $title ?? 'Payments')

@section('content')
<div class="space-y-6">
    <x-admin.card>
        <x-admin.page-header :title="$title" icon="fas fa-credit-card">
        </x-admin.page-header>

        <x-admin.info-alert>
            BTCPay webhook records appear here after invoices are settled. Use filters to find a user or status.
        </x-admin.info-alert>

        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
            <form method="get" action="{{ route('admin.payment-list') }}" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="filter-username" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                        <input type="text" name="username" id="filter-username" value="{{ $filters['username'] }}"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-blue-400 dark:focus:ring-blue-400"
                               placeholder="Contains…">
                    </div>
                    <div>
                        <label for="filter-email" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="text" name="email" id="filter-email" value="{{ $filters['email'] }}"
                               class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-blue-400 dark:focus:ring-blue-400"
                               placeholder="Contains…">
                    </div>
                    <div>
                        <label for="filter-payment-status" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment status</label>
                        <select name="payment_status" id="filter-payment-status"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                            <option value="">All</option>
                            @foreach($paymentStatuses as $ps)
                                <option value="{{ $ps }}" @selected($filters['payment_status'] === $ps)>{{ $ps }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-invoice-status" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice status</label>
                        <select name="invoice_status" id="filter-invoice-status"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:focus:border-blue-400 dark:focus:ring-blue-400">
                            <option value="">All</option>
                            @foreach($invoiceStatuses as $is)
                                <option value="{{ $is }}" @selected($filters['invoice_status'] === $is)>{{ $is }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="order" value="{{ $order }}">
                    <button type="submit" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-800">
                        <i class="fas fa-filter mr-2"></i>Apply filters
                    </button>
                    <a href="{{ route('admin.payment-list') }}" class="inline-flex items-center rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        @if($payments->count() > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th align="center" width="16">
                        <div class="flex items-center justify-center gap-2">
                            <span>ID</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'id', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'id' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-numeric-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'id', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'id' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-numeric-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Date</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'created_at' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-numeric-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'created_at', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'created_at' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-numeric-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Username</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'username', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'username' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'username', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'username' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Email</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'email' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'email', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'email' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>Item</x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Order ID</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'order_id', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'order_id' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'order_id', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'order_id' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Amount</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_amount', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'invoice_amount' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-numeric-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_amount', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'invoice_amount' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-numeric-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>Method</x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Pay. status</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_status', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'payment_status' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'payment_status', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'payment_status' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                    <x-admin.th>
                        <div class="flex items-center gap-2">
                            <span>Inv. status</span>
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_status', 'order' => 'asc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'invoice_status' && $order === 'asc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Ascending">
                                    <i class="fas fa-sort-alpha-down text-xs"></i>
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['sort' => 'invoice_status', 'order' => 'desc', 'page' => null]) }}" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 {{ ($sort === 'invoice_status' && $order === 'desc') ? 'text-blue-600 dark:text-blue-400' : '' }}" title="Sort Descending">
                                    <i class="fas fa-sort-alpha-down-alt text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </x-admin.th>
                </x-slot:head>

                @foreach($payments as $payment)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $payment->id }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                            {{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $payment->username }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ $payment->email }}</td>
                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate" title="{{ $payment->item_description }}">{{ Str::limit($payment->item_description, 48) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 dark:text-gray-400">{{ Str::limit($payment->order_id, 24) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $payment->invoice_amount }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($payment->payment_method, 20) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if(strcasecmp((string) $payment->payment_status, \App\Models\Payment::PAYMENT_STATUS_SETTLED) === 0)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    {{ $payment->payment_status }}
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 dark:bg-amber-900 text-amber-800 dark:text-amber-200">
                                    {{ $payment->payment_status }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $inv = $payment->invoice_status ?? \App\Models\Payment::INVOICE_STATUS_PENDING;
                            @endphp
                            @if(strcasecmp((string) $inv, \App\Models\Payment::INVOICE_STATUS_SETTLED) === 0)
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                    {{ $inv }}
                                </span>
                            @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                    {{ $inv }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-admin.data-table>

            <div class="mt-4 px-6">
                {{ $payments->withQueryString()->links() }}
            </div>

            <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <span>Total: <strong class="text-gray-900 dark:text-gray-100">{{ $payments->total() }}</strong> payments (this filter)</span>
                </div>
            </div>
        @else
            <x-empty-state
                icon="fas fa-credit-card"
                title="No payments found"
                message="No payment records match your filters, or none have been recorded yet."
                :actionUrl="route('admin.payment-list')"
                actionLabel="Clear filters"
                actionIcon="fas fa-redo"
            />
        @endif
    </x-admin.card>
</div>
@endsection
