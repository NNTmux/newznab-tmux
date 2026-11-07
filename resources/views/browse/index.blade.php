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
    // Pages that reuse this view (e.g. My show releases) can replace the heading.
    $heading = array_merge([
        'title' => $browseTitle,
        'eyebrow' => 'Discover and download',
        'description' => 'Browse the latest indexed releases, refine the list, and send selections to your download basket.',
        'icon' => 'fas fa-compass',
    ], $pageHeading ?? []);
    $showsRssUrl = !empty($shows) && auth()->check() ? url('/rss/myshows?dl=1&i='.auth()->id().'&api_token='.auth()->user()->api_token) : null;

    // Main + sub category filters (not shown on group browsing, which has no menu data).
    $activeParent = isset($browseMenu) ? collect($browseMenu)->first(fn ($parent) => strcasecmp((string) $parent['title'], (string) ($parentcat ?? '')) === 0) : null;
    $mainCategoryItems = isset($browseMenu) ? \App\Support\Navigation\ListFilters::mainCategoryItems($browseMenu, $activeParent['title'] ?? null) : [];
    $subCategoryItems = $activeParent ? \App\Support\Navigation\ListFilters::browseSubcategoryItems($activeParent, $currentCategoryLabel !== 'All' ? $currentCategoryLabel : null) : [];
@endphp
<x-page-header :title="$heading['title']" :current="isset($pageHeading) ? null : $currentCategoryLabel" :eyebrow="$heading['eyebrow']" :description="$heading['description']" :icon="$heading['icon']">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
        @unless(isset($pageHeading))
            <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $currentCategoryLabel }}</span>
        @endunless
    </x-slot:stats>
    @if($showsRssUrl)
        <x-slot:actions>
            <a href="{{ route('series') }}" class="workspace-hero__action"><i class="fas fa-tv" aria-hidden="true"></i>Series list</a>
            <a href="{{ route('trending-tv') }}" class="workspace-hero__action"><i class="fas fa-fire" aria-hidden="true"></i>Trending</a>
            <a href="{{ route('myshows') }}" class="workspace-hero__action"><i class="fas fa-bookmark" aria-hidden="true"></i>Manage my shows</a>
            <a href="{{ $showsRssUrl }}" class="workspace-hero__action"><i class="fas fa-rss" aria-hidden="true"></i>RSS feed</a>
        </x-slot:actions>
    @endif
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
        @if(isset($pageHeading) && !empty($shows))
            <x-empty-state icon="fas fa-tv" title="No releases yet"
                           message="Nothing has been posted for the shows you follow. New episodes show up here and in your RSS feed."
                           :action-url="route('myshows')" action-label="Manage my shows" action-icon="fas fa-bookmark" />
        @else
            <x-empty-state
                icon="fas fa-search"
                title="No releases found"
                message="Try adjusting your search criteria or browse other categories."
            />
        @endif
    @endif

    {{-- All modals (preview, mediainfo, filelist, NFO) are included globally via layouts.main --}}
</div>
@endsection
