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

<div class="inline-search-widget min-w-0" data-base-url="{{ $baseUrl }}">
    <div class="inline-search-widget__field relative min-w-0">
        <div class="inline-search-widget__icon pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <i class="fas fa-search text-sm text-gray-400 dark:text-gray-500" aria-hidden="true"></i>
        </div>
        <input type="text"
               data-role="inline-search-input"
               placeholder="{{ $placeholder }}"
               class="inline-search-widget__input min-h-10 w-full rounded-xl border border-gray-300 bg-white py-2 pl-10 pr-3 text-sm text-gray-900 shadow-sm transition placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
               aria-label="{{ $placeholder }}"
               autocomplete="off">
    </div>
    <button type="button"
            data-role="inline-search-btn"
            class="inline-search-widget__button inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent text-sm text-white shadow-sm transition hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-accent-hover dark:hover:bg-accent"
            aria-label="Search">
        <i class="fas fa-search" aria-hidden="true"></i>
    </button>
</div>
