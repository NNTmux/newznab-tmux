@props([
    'action' => null,
    'placeholder' => 'Quick search...',
    'category' => null,
])

@php
    $baseUrl = $action ?? route('search');
    if ($category !== null && (int) $category > 0) {
        $baseUrl .= (str_contains($baseUrl, '?') ? '&' : '?') . 't=' . (int) $category;
    }
@endphp

<div class="inline-search-widget flex items-center gap-2" data-base-url="{{ $baseUrl }}">
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400 dark:text-gray-500 text-sm"></i>
        </div>
        <input type="text"
               data-role="inline-search-input"
               placeholder="{{ $placeholder }}"
               class="w-48 lg:w-56 pl-9 pr-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition"
               autocomplete="off">
    </div>
    <x-button type="button"
            size="sm"
            data-role="inline-search-btn"
            aria-label="Search"
            icon="fas fa-search"></x-button>
</div>

