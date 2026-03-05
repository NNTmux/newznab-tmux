{{-- Admin stat card for dashboards --}}
@props([
    'label',
    'value',
    'icon' => 'fas fa-chart-bar',
    'color' => 'blue',
    'footer' => null,
])

@php
    $colorMap = [
        'blue'   => ['bg' => 'bg-blue-100 dark:bg-blue-900', 'text' => 'text-blue-600 dark:text-blue-400'],
        'green'  => ['bg' => 'bg-green-100 dark:bg-green-900', 'text' => 'text-green-600 dark:text-green-400'],
        'red'    => ['bg' => 'bg-red-100 dark:bg-red-900', 'text' => 'text-red-600 dark:text-red-400'],
        'purple' => ['bg' => 'bg-purple-100 dark:bg-purple-900', 'text' => 'text-purple-600 dark:text-purple-400'],
        'yellow' => ['bg' => 'bg-yellow-100 dark:bg-yellow-900', 'text' => 'text-yellow-600 dark:text-yellow-400'],
        'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900', 'text' => 'text-indigo-600 dark:text-indigo-400'],
    ];
    $colors = $colorMap[$color] ?? $colorMap['blue'];
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">{{ $label }}</p>
            <p class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $value }}</p>
        </div>
        <div class="w-12 h-12 {{ $colors['bg'] }} rounded-lg flex items-center justify-center">
            <i class="{{ $icon }} text-2xl {{ $colors['text'] }}"></i>
        </div>
    </div>
    @if($footer || isset($footerSlot))
        <div class="mt-4">
            @if(isset($footerSlot))
                {{ $footerSlot }}
            @else
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $footer }}</span>
            @endif
        </div>
    @endif
</div>

