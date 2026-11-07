@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
@php
    $currentCategoryLabel = match (true) {
        is_object($catname ?? null) => $catname->title ?? 'All',
        is_string($catname ?? null) && strtolower($catname) !== 'all' => $catname,
        default => 'All',
    };
    $browseTitle = !empty($parentcat) && $parentcat !== 'All' ? $parentcat : 'Browse releases';

    // Main + sub category filters (not shown on group browsing, which has no menu data).
    $activeParent = isset($browseMenu) ? collect($browseMenu)->first(fn ($parent) => strcasecmp((string) $parent['title'], (string) ($parentcat ?? '')) === 0) : null;
    $mainCategoryItems = isset($browseMenu) ? \App\Support\Navigation\ListFilters::mainCategoryItems($browseMenu, $activeParent['title'] ?? null) : [];
    $subCategoryItems = $activeParent ? \App\Support\Navigation\ListFilters::browseSubcategoryItems($activeParent, $currentCategoryLabel !== 'All' ? $currentCategoryLabel : null) : [];
@endphp
<x-page-header :title="$browseTitle" :current="$currentCategoryLabel" eyebrow="Discover and download" description="Browse the latest indexed releases, refine the list, and send selections to your download basket." icon="fas fa-compass">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
        <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $currentCategoryLabel }}</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm transition-colors duration-200">

    @if($results->count() > 0)
        @php
            $searchPlaceholder = 'Search';
            if (!empty($parentcat) && $parentcat !== 'All') {
                $searchPlaceholder .= ' in ' . $parentcat;
                if (!empty($catname) && $catname !== 'All' && $catname !== 'all') {
                    $searchPlaceholder .= ' ' . $catname;
                }
            }
            $searchPlaceholder .= '...';
        @endphp

        <x-release-results-panel :results="$results" :show-thumbs="true" date-field="adddate" :show-top-pagination="true">
            <x-slot:beforeActions>
                @if($mainCategoryItems !== [])
                    <div class="flex flex-wrap items-center gap-2">
                        <x-link-dropdown label="Main category" icon="fas fa-sitemap" :items="$mainCategoryItems" />
                        @if($subCategoryItems !== [])
                            <x-link-dropdown label="Category" icon="fas fa-folder-open" :items="$subCategoryItems" />
                        @endif
                    </div>
                @endif

                @if(isset($shows))
                    <div class="flex flex-wrap gap-2 text-sm">
                        <a href="{{ route('series') }}" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="View available TV series">Series List</a>
                        <span class="text-gray-400 dark:text-gray-500">|</span>
                        <a href="{{ route('trending-tv') }}" class="text-orange-600 dark:text-orange-400 hover:text-orange-800 dark:hover:text-orange-300" title="View trending TV shows"><i aria-hidden="true" class="fas fa-fire mr-1"></i>Trending TV</a>
                        <span class="text-gray-400 dark:text-gray-500">|</span>
                        <a href="{{ route('myshows') }}" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="Manage your shows">Manage My Shows</a>
                        <span class="text-gray-400 dark:text-gray-500">|</span>
                        <a href="{{ url('/rss/myshows?dl=1&i=' . auth()->id() . '&api_token=' . auth()->user()->api_token) }}" class="text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300" title="RSS Feed">RSS Feed</a>
                    </div>
                @endif

                @if(isset($covgroup) && $covgroup != '' || isset($shows) && $shows)
                    <x-view-toggle
                        current-view="list"
                        :covgroup="$covgroup ?? null"
                        :category="$catname ?? 'All'"
                        :parentcat="$parentcat ?? null"
                        :shows="$shows ?? false"
                    />
                @endif
            </x-slot:beforeActions>

            <x-slot:toolbarRight>
                <x-inline-search :placeholder="$searchPlaceholder" :category="$category ?? null" />
            </x-slot:toolbarRight>
        </x-release-results-panel>
    @else
        @if($mainCategoryItems !== [])
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-4 sm:px-6">
                <x-link-dropdown label="Main category" icon="fas fa-sitemap" :items="$mainCategoryItems" />
                @if($subCategoryItems !== [])
                    <x-link-dropdown label="Category" icon="fas fa-folder-open" :items="$subCategoryItems" />
                @endif
            </div>
        @endif
        <x-empty-state
            icon="fas fa-search"
            title="No releases found"
            message="Try adjusting your search criteria or browse other categories."
        />
    @endif

    {{-- All modals (preview, mediainfo, filelist, NFO) are included globally via layouts.main --}}
</div>
@endsection
