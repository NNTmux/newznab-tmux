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
])

@php
    $totalResults = is_object($results) && method_exists($results, 'total') ? $results->total() : count($results);
@endphp

<div class="mb-4 flex flex-wrap justify-between items-center gap-4">
    <div class="flex items-center gap-4">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            <i class="{{ $icon }} mr-2 text-primary-600 dark:text-primary-400"></i>
            {{ $title }}
        </h2>
        <x-view-toggle
            current-view="covers"
            :covgroup="$covgroup"
            :category="$category"
            :parentcat="$parentcat"
            :shows="false"
        />
    </div>
    <div class="flex items-center gap-4">
        <x-inline-search :placeholder="$searchPlaceholder" :category="$searchCategory" />
        <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ $totalResults }} {{ $totalLabel }}
        </span>
    </div>
</div>
