@extends('layouts.admin')

@section('title', $title ?? 'Payments')

@use('App\Models\Payment')

@section('content')
@php
    $summary = $report['summary'];
    $trend = $report['trend'];
    $previous = $summary['previous'];
    $amount = static fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $coinAmount = static fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.') ?: '0';
    $changeClass = static fn (?float $change): string => match (true) {
        $change === null, $change == 0.0 => 'text-fg-muted',
        $change > 0 => 'text-success-700 dark:text-success-300',
        default => 'text-danger-700 dark:text-danger-300',
    };
    $changeIcon = static fn (?float $change): string => match (true) {
        $change === null, $change == 0.0 => 'fa-minus',
        $change > 0 => 'fa-arrow-trend-up',
        default => 'fa-arrow-trend-down',
    };
    $sortLink = static fn (string $column): string => request()->fullUrlWithQuery([
        'sort' => $column,
        'order' => $sort === $column && $order === 'asc' ? 'desc' : 'asc',
        'page' => null,
    ]);
    $sortState = static fn (string $column): ?string => $sort === $column ? $order : null;
    $stateBadges = [
        Payment::STATE_SETTLED => ['tone' => 'success', 'label' => 'Settled', 'title' => 'The role upgrade was applied'],
        Payment::STATE_UNSETTLED => ['tone' => 'warning', 'label' => 'Unsettled', 'title' => 'Paid, but the role upgrade has not been applied'],
        Payment::STATE_LEGACY => ['tone' => 'neutral', 'label' => 'Legacy', 'title' => 'Recorded before invoice settlement was tracked'],
    ];
    $field = 'w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-400 dark:focus:border-primary-400 dark:focus:ring-primary-400';
    $fieldLabel = 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $panel = 'admin-card surface-panel rounded-2xl border p-5 shadow-sm';
    $panelTitle = 'flex items-center gap-2 text-base font-semibold text-fg';
    $barTrack = 'h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700';
@endphp
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-credit-card" subtitle="BTCPay payments, revenue and payers.">
        <x-slot:actions>
            <x-admin.button :href="$exportUrl" tone="gray" icon="fas fa-file-csv">Export CSV</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Key numbers for the filtered payments --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.stat-card label="Revenue" :value="$amount($summary['revenue'])" icon="fas fa-coins" color="success">
            <x-slot:footerSlot>
                @if($previous)
                    <p class="mt-4 text-sm {{ $changeClass($previous['revenue_change']) }}">
                        <i aria-hidden="true" class="fas {{ $changeIcon($previous['revenue_change']) }} mr-1"></i>
                        {{ $previous['revenue_change'] === null ? 'No revenue' : ($previous['revenue_change'] > 0 ? '+' : '').$previous['revenue_change'].'%' }}
                        vs previous period ({{ $amount($previous['revenue']) }})
                    </p>
                @else
                    <p class="mt-4 text-sm text-fg-muted">Settled and legacy invoices</p>
                @endif
            </x-slot:footerSlot>
        </x-admin.stat-card>
        <x-admin.stat-card label="Payments" :value="number_format($summary['payments'])" icon="fas fa-receipt" color="primary">
            <x-slot:footerSlot>
                @if($previous)
                    <p class="mt-4 text-sm {{ $changeClass($previous['payments_change']) }}">
                        <i aria-hidden="true" class="fas {{ $changeIcon($previous['payments_change']) }} mr-1"></i>
                        {{ $previous['payments_change'] === null ? 'None' : ($previous['payments_change'] > 0 ? '+' : '').$previous['payments_change'].'%' }}
                        vs previous period ({{ number_format($previous['payments']) }})
                    </p>
                @else
                    <p class="mt-4 text-sm text-fg-muted">{{ number_format($summary['legacy']) }} legacy</p>
                @endif
            </x-slot:footerSlot>
        </x-admin.stat-card>
        <x-admin.stat-card label="Payers" :value="number_format($summary['payers'])" icon="fas fa-users" color="info"
                           :footer="number_format($summary['returning']).' returning '.Str::plural('payment', $summary['returning'])" />
        <x-admin.stat-card label="Average payment" :value="$amount($summary['average'])" icon="fas fa-scale-balanced" color="violet"
                           footer="Per paid invoice" />
        <x-admin.stat-card label="Unsettled" :value="number_format($summary['unsettled'])" icon="fas fa-hourglass-half" :color="$summary['unsettled'] > 0 ? 'warning' : 'success'">
            <x-slot:footerSlot>
                <p class="mt-4 text-sm text-fg-muted">
                    @if($summary['unsettled'] > 0)
                        {{ $amount($summary['unsettled_amount']) }} not counted as revenue ·
                        <a href="{{ request()->fullUrlWithQuery(['invoice_status' => Payment::STATE_UNSETTLED, 'page' => null]) }}" class="font-medium text-link hover:underline dark:text-primary-400">Show</a>
                    @else
                        Every role upgrade was applied
                    @endif
                </p>
            </x-slot:footerSlot>
        </x-admin.stat-card>
    </div>

    @if($payerCard)
        {{-- One payer's history, shown while drilling down --}}
        <section class="{{ $panel }}" aria-labelledby="payments-payer">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="payments-payer" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-user text-link dark:text-primary-400"></i> {{ $payerCard['username'] }}</h2>
                <div class="flex flex-wrap items-center gap-2">
                    @if($payerCard['user'])
                        <x-admin.badge tone="primary">{{ $payerCard['user']->role?->name ?? 'No role' }}</x-admin.badge>
                        <x-admin.button :href="route('admin.user-edit', ['id' => $payerCard['user']->id])" tone="gray" icon="fas fa-user-pen">Open user</x-admin.button>
                    @else
                        <x-admin.badge tone="neutral">No matching user</x-admin.badge>
                    @endif
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm lg:grid-cols-4">
                <div><dt class="text-fg-muted">Total paid</dt><dd class="text-lg font-semibold tabular-nums text-fg">{{ $amount($payerCard['revenue']) }}</dd></div>
                <div><dt class="text-fg-muted">Payments</dt><dd class="text-lg font-semibold tabular-nums text-fg">{{ number_format($payerCard['payments']) }}</dd></div>
                <div><dt class="text-fg-muted">First payment</dt><dd class="text-lg font-semibold text-fg">{{ $payerCard['first_at']?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="text-fg-muted">Last payment</dt><dd class="text-lg font-semibold text-fg">{{ $payerCard['last_at']?->format('Y-m-d') ?? '—' }}</dd></div>
            </dl>
        </section>
    @endif

    @if($trend['labels'] !== [])
        {{-- Revenue and payment count over time --}}
        <section class="{{ $panel }}" aria-labelledby="payments-trend">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="payments-trend" class="{{ $panelTitle }}"><i aria-hidden="true" class="fas fa-chart-column text-link dark:text-primary-400"></i> Revenue and payments per {{ $trend['unit'] }}</h2>
                <span class="text-sm text-fg-muted">Follows the filters below</span>
            </div>
            <div class="chart-container mt-4" x-data="adminPayments" data-series="{{ json_encode($trend) }}">
                <canvas role="img" aria-label="Revenue and number of payments per {{ $trend['unit'] }}"></canvas>
            </div>
        </section>
    @endif

    @if($report['plans'] !== [])
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            @foreach(['plans' => ['By plan', 'fas fa-tags', 'Plan', 'bg-primary-500'], 'coins' => ['By coin', 'fas fa-bitcoin-sign', 'Coin', 'bg-violet-500']] as $key => [$heading, $icon, $column, $barColor])
                <section class="{{ $panel }}" aria-labelledby="payments-{{ $key }}">
                    <h2 id="payments-{{ $key }}" class="{{ $panelTitle }}"><i aria-hidden="true" class="{{ $icon }} text-link dark:text-primary-400"></i> {{ $heading }}</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr>
                                    <x-admin.th>{{ $column }}</x-admin.th>
                                    <x-admin.th align="right">Payments</x-admin.th>
                                    @if($key === 'coins')
                                        <x-admin.th align="right">Received</x-admin.th>
                                    @endif
                                    <x-admin.th align="right">Revenue</x-admin.th>
                                    <x-admin.th width="40">Share</x-admin.th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report[$key] as $row)
                                    <tr class="border-t border-gray-100 dark:border-gray-700">
                                        <td class="px-4 py-2 font-medium text-fg">
                                            <a href="{{ request()->fullUrlWithQuery([$key === 'plans' ? 'plan' : 'coin' => $row['label'], 'page' => null]) }}" class="hover:underline">{{ $row['label'] }}</a>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-fg">{{ number_format($row['payments']) }}</td>
                                        @if($key === 'coins')
                                            <td class="px-4 py-2 text-right tabular-nums text-fg-muted">{{ $coinAmount($row['received']) }}</td>
                                        @endif
                                        <td class="px-4 py-2 text-right font-semibold tabular-nums text-fg">{{ $amount($row['revenue']) }}</td>
                                        <td class="px-4 py-2">
                                            <span class="flex items-center gap-2">
                                                <span aria-hidden="true" class="{{ $barTrack }}"><span class="block h-full rounded-full {{ $barColor }}" style="width: {{ $row['share'] }}%"></span></span>
                                                <span class="w-12 text-right text-xs tabular-nums text-fg-muted">{{ $row['share'] }}%</span>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <x-admin.card>
        <x-admin.filter-panel>
            <form method="get" action="{{ route('admin.payment-list') }}" x-data="periodFilter({{ $period === 'custom' ? 'true' : 'false' }})" x-ref="periodForm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="filter-q" class="{{ $fieldLabel }}">Reference</label>
                        <input type="text" name="q" id="filter-q" value="{{ $filters['q'] }}" placeholder="Order, invoice or payment ID" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="filter-username" class="{{ $fieldLabel }}">Username</label>
                        <input type="text" name="username" id="filter-username" value="{{ $filters['username'] }}" placeholder="Contains…" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="filter-email" class="{{ $fieldLabel }}">Email</label>
                        <input type="text" name="email" id="filter-email" value="{{ $filters['email'] }}" placeholder="Contains…" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="filter-plan" class="{{ $fieldLabel }}">Plan</label>
                        <select name="plan" id="filter-plan" class="{{ $field }}">
                            <option value="">All plans</option>
                            @foreach($options['plans'] as $plan)
                                <option value="{{ $plan }}" @selected($filters['plan'] === $plan)>{{ $plan }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-coin" class="{{ $fieldLabel }}">Coin</label>
                        <select name="coin" id="filter-coin" class="{{ $field }}">
                            <option value="">All coins</option>
                            @foreach($options['coins'] as $coin)
                                <option value="{{ $coin }}" @selected(strcasecmp($filters['coin'], $coin) === 0)>{{ $coin }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="filter-invoice-status" class="{{ $fieldLabel }}">State</label>
                        <select name="invoice_status" id="filter-invoice-status" class="{{ $field }}">
                            <option value="">All states</option>
                            @foreach($stateBadges as $state => $badge)
                                <option value="{{ $state }}" @selected($filters['invoice_status'] === $state)>{{ $badge['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(count($options['payment_statuses']) > 1)
                        <div>
                            <label for="filter-payment-status" class="{{ $fieldLabel }}">Payment status</label>
                            <select name="payment_status" id="filter-payment-status" class="{{ $field }}">
                                <option value="">All</option>
                                @foreach($options['payment_statuses'] as $paymentStatus)
                                    <option value="{{ $paymentStatus }}" @selected($filters['payment_status'] === $paymentStatus)>{{ $paymentStatus }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="filter-payer" class="{{ $fieldLabel }}">Payer type</label>
                        <select name="payer" id="filter-payer" class="{{ $field }}">
                            <option value="">All payers</option>
                            <option value="returning" @selected($filters['payer'] === 'returning')>Returning</option>
                            <option value="first_time" @selected($filters['payer'] === 'first_time')>First payment</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-account" class="{{ $fieldLabel }}">Account</label>
                        <select name="account" id="filter-account" class="{{ $field }}">
                            <option value="">Any</option>
                            <option value="linked" @selected($filters['account'] === 'linked')>Has a user account</option>
                            <option value="missing" @selected($filters['account'] === 'missing')>No user account</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter-amount-min" class="{{ $fieldLabel }}">Min amount</label>
                        <input type="number" min="0" step="any" name="amount_min" id="filter-amount-min" value="{{ $filters['amount_min'] }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="filter-amount-max" class="{{ $fieldLabel }}">Max amount</label>
                        <input type="number" min="0" step="any" name="amount_max" id="filter-amount-max" value="{{ $filters['amount_max'] }}" class="{{ $field }}">
                    </div>
                    <div>
                        <label for="filter-period" class="{{ $fieldLabel }}">Period</label>
                        <select name="period" id="filter-period" class="{{ $field }}" @change="onPeriodChange()">
                            @foreach($periods as $value => $label)
                                <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="showCustom" x-cloak>
                        <label for="filter-start-date" class="{{ $fieldLabel }}">From date</label>
                        <input type="date" name="start_date" id="filter-start-date" value="{{ $period === 'custom' ? $filters['start_date'] : '' }}" class="{{ $field }}">
                    </div>
                    <div x-show="showCustom" x-cloak>
                        <label for="filter-end-date" class="{{ $fieldLabel }}">To date</label>
                        <input type="date" name="end_date" id="filter-end-date" value="{{ $period === 'custom' ? $filters['end_date'] : '' }}" class="{{ $field }}">
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="order" value="{{ $order }}">
                    @if($filters['user'] !== '')
                        <input type="hidden" name="user" value="{{ $filters['user'] }}">
                    @endif
                    <x-admin.button type="submit" icon="fas fa-filter">Apply filters</x-admin.button>
                    <x-admin.button :href="route('admin.payment-list')" tone="gray" icon="fas fa-times">Reset</x-admin.button>
                </div>
            </form>
        </x-admin.filter-panel>

        @if($errors->any())
            <div class="mx-6 mt-4 rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-900">
                <p class="text-danger-800 dark:text-danger-200">
                    <i aria-hidden="true" class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
                </p>
            </div>
        @endif

        @if($activeFilters !== [])
            <ul class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3 text-sm sm:px-6 dark:border-gray-700" aria-label="Active filters">
                @foreach($activeFilters as $chip)
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 py-1 pl-3 pr-1.5 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                        <span class="text-fg-muted">{{ $chip['label'] }}:</span>
                        <span class="font-medium">{{ $chip['value'] }}</span>
                        <a href="{{ $chip['remove_url'] }}" aria-label="Remove {{ $chip['label'] }} filter" class="inline-flex h-5 w-5 items-center justify-center rounded-full hover:bg-gray-300 dark:hover:bg-gray-600">
                            <i aria-hidden="true" class="fas fa-times text-xs"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if($payments->count() > 0)
            <x-admin.data-table>
                <x-slot:head>
                    <x-admin.th align="center" width="16" :sort="$sortState('id')" :href="$sortLink('id')">ID</x-admin.th>
                    <x-admin.th :sort="$sortState('created_at')" :href="$sortLink('created_at')">Date</x-admin.th>
                    <x-admin.th :sort="$sortState('username')" :href="$sortLink('username')">Payer</x-admin.th>
                    <x-admin.th :sort="$sortState('item_description')" :href="$sortLink('item_description')">Plan</x-admin.th>
                    <x-admin.th align="right" :sort="$sortState('invoice_amount')" :href="$sortLink('invoice_amount')">Amount</x-admin.th>
                    <x-admin.th :sort="$sortState('payment_method')" :href="$sortLink('payment_method')">Coin</x-admin.th>
                    <x-admin.th align="right">Received</x-admin.th>
                    <x-admin.th :sort="$sortState('invoice_status')" :href="$sortLink('invoice_status')">State</x-admin.th>
                    <x-admin.th :sort="$sortState('order_id')" :href="$sortLink('order_id')">Order ID</x-admin.th>
                </x-slot:head>

                @foreach($payments as $payment)
                    @php
                        $payerKey = strtolower((string) $payment->username);
                        $history = $histories[$payerKey] ?? null;
                        $accountId = $accounts[$payerKey] ?? null;
                        $badge = $stateBadges[$payment->invoiceState()];
                    @endphp
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="whitespace-nowrap text-center font-semibold text-gray-900 dark:text-gray-100">{{ $payment->id }}</td>
                        <td class="whitespace-nowrap text-gray-700 dark:text-gray-300">
                            {{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <a href="{{ route('admin.payment-list', ['user' => $payment->username]) }}" class="font-medium text-gray-900 hover:underline dark:text-gray-100" title="All payments by {{ $payment->username }}">{{ $payment->username }}</a>
                                @if($accountId)
                                    <a href="{{ route('admin.user-edit', ['id' => $accountId]) }}" aria-label="Open user {{ $payment->username }}" class="text-fg-muted hover:text-link dark:hover:text-primary-400">
                                        <i aria-hidden="true" class="fas fa-user-pen text-xs"></i>
                                    </a>
                                @endif
                                @if($history && $history['first_id'] !== (int) $payment->id)
                                    <x-admin.badge tone="info" title="{{ $history['payments'] }} payments by this payer">Returning</x-admin.badge>
                                @endif
                            </span>
                            <span class="block text-xs text-gray-600 dark:text-gray-400">{{ $payment->email }}</span>
                        </td>
                        <td class="max-w-xs truncate text-gray-700 dark:text-gray-300" title="{{ $payment->item_description }}">{{ Str::limit($payment->item_description, 48) }}</td>
                        <td class="whitespace-nowrap text-right font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $payment->invoice_amount }}</td>
                        <td class="whitespace-nowrap text-gray-700 dark:text-gray-300" title="{{ $payment->payment_method }}">{{ Payment::coinFor($payment->payment_method) }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums text-gray-600 dark:text-gray-400">{{ $payment->payment_value }}</td>
                        <td class="whitespace-nowrap">
                            <x-admin.badge :tone="$badge['tone']" :title="$badge['title']">{{ $badge['label'] }}</x-admin.badge>
                        </td>
                        <td class="whitespace-nowrap font-mono text-gray-600 dark:text-gray-400" title="Invoice {{ $payment->invoice_id }}">{{ Str::limit($payment->order_id, 24) }}</td>
                    </tr>
                @endforeach
            </x-admin.data-table>

            <x-admin.pagination :paginator="$payments" />

            <div class="surface-panel-alt border-t border-gray-200 px-6 py-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                Total: <strong class="text-gray-900 dark:text-gray-100">{{ number_format($payments->total()) }}</strong> {{ Str::plural('payment', $payments->total()) }} (this filter)
            </div>
        @else
            <x-admin.empty-state icon="fas fa-credit-card" title="No payments found" message="No payment records match your filters, or none have been recorded yet.">
                <x-admin.button :href="route('admin.payment-list')" tone="gray" icon="fas fa-redo">Clear filters</x-admin.button>
            </x-admin.empty-state>
        @endif
    </x-admin.card>
</div>
@endsection
