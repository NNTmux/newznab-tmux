{{-- Admin stat card for dashboards --}}
@props([
    'label',
    'value',
    'icon' => 'fas fa-chart-bar',
    'color' => 'primary',
    'footer' => null,
    'statKey' => null,
])

@php
    $colorMap = [
        'primary' => ['bg' => 'bg-primary-100 dark:bg-primary-900', 'text' => 'text-link dark:text-primary-300'],
        'success' => ['bg' => 'bg-success-100 dark:bg-success-900', 'text' => 'text-success-700 dark:text-success-300'],
        'danger' => ['bg' => 'bg-danger-100 dark:bg-danger-900', 'text' => 'text-danger-700 dark:text-danger-300'],
        'warning' => ['bg' => 'bg-warning-100 dark:bg-warning-900', 'text' => 'text-warning-700 dark:text-warning-300'],
        'info' => ['bg' => 'bg-info-100 dark:bg-info-900', 'text' => 'text-info-700 dark:text-info-300'],
        'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900', 'text' => 'text-violet-700 dark:text-violet-300'],
        'orange' => ['bg' => 'bg-orange-100 dark:bg-orange-900', 'text' => 'text-orange-700 dark:text-orange-300'],
        'pink' => ['bg' => 'bg-pink-100 dark:bg-pink-900', 'text' => 'text-pink-700 dark:text-pink-300'],
        'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900', 'text' => 'text-indigo-700 dark:text-indigo-300'],
    ];
    $legacy = ['blue' => 'primary', 'green' => 'success', 'red' => 'danger', 'yellow' => 'warning', 'purple' => 'violet'];
    $colors = $colorMap[$legacy[$color] ?? $color] ?? $colorMap['primary'];
@endphp

<div {{ $attributes->merge(['class' => 'admin-stat-card surface-panel rounded-2xl border p-5 shadow-sm sm:p-6']) }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="mb-1 text-sm text-fg-muted">{{ $label }}</p>
            <p class="text-3xl font-bold tracking-tight text-fg tabular-nums" @if($statKey) data-stat="{{ $statKey }}" @endif>{{ $value }}</p>
        </div>
        <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $colors['bg'] }}">
            <i class="{{ $icon }} text-xl {{ $colors['text'] }}" aria-hidden="true"></i>
        </div>
    </div>
    @if(isset($footerSlot))
        {{ $footerSlot }}
    @elseif($footer)
        <div class="mt-4">
            <span class="text-sm text-fg-muted">{{ $footer }}</span>
        </div>
    @endif
</div>
