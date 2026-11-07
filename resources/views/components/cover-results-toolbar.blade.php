@props([
    'results',
    'icon',
    'title',
    'covgroup',
    'category' => 'All',
    'parentcat',
    'searchPlaceholder',
    'searchCategory' => null,
    'totalLabel' => 'results found',
    'categoryItems' => [],
    'ordering' => null,
])

@php
    $totalResults = is_object($results) && method_exists($results, 'total') ? $results->total() : count($results);
@endphp

<div class="catalog-results-toolbar mb-4 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:flex-row lg:items-center lg:justify-between sm:px-5">
    <div class="flex flex-wrap items-center gap-4">
        <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">
            <i class="{{ $icon }} mr-2 text-link dark:text-primary-400" aria-hidden="true"></i>
            {{ $title }}
        </h2>
        <x-view-toggle
            current-view="covers"
            :covgroup="$covgroup"
            :category="$category"
            :parentcat="$parentcat"
            :shows="false"
        />
        @if($categoryItems !== [])
            <x-link-dropdown label="Category" icon="fas fa-folder-open" :items="$categoryItems" />
        @endif
        @if($ordering)
            <x-sort-dropdown :ordering="$ordering" />
        @endif
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <x-inline-search :placeholder="$searchPlaceholder" :category="$searchCategory" />
        <span class="text-sm text-gray-600 dark:text-gray-400">
            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ number_format($totalResults) }}</span> {{ $totalLabel }}
        </span>
    </div>
</div>
