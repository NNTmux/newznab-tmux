@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <x-page-header title="Adult" :current="!empty($categorytitle) ? $categorytitle : 'All releases'" eyebrow="Browse the catalog" description="Explore adult releases by category, search the current collection, and choose your preferred ordering." icon="fas fa-user-lock">
        <x-slot:stats>
            <span class="workspace-hero__stat"><i class="fas fa-film" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
            <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $categorytitle ?: 'All releases' }}</span>
        </x-slot:stats>
    </x-page-header>

    <!-- Category and order -->
    <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-4 sm:px-6">
        <x-view-toggle current-view="covers" covgroup="xxx" :category="!empty($categorytitle) ? $categorytitle : 'All'" parentcat="XXX" :shows="false" />
        <x-link-dropdown label="Category" icon="fas fa-folder-open" :items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, url('/XXX'), 'All categories')" />
        <x-sort-dropdown :ordering="$ordering ?? []" />
        <div class="ml-auto">
            <x-inline-search :placeholder="'Search in XXX'.(!empty($categorytitle) && $categorytitle !== 'All' ? ' '.$categorytitle : '').'...'" :category="$category ?? null" />
        </div>
    </div>

    @if($results->count() > 0)
        <x-release-results-panel :results="$results" date-field="adddate" :show-sort="false" />
    @else
        <x-empty-state
            icon="fas fa-film"
            title="No content found"
            message="Try a different category or browse all XXX."
            :action-url="url('/XXX/All')"
            action-label="Browse All XXX"
            action-icon="fas fa-film"
        />
    @endif
</div>
@endsection
