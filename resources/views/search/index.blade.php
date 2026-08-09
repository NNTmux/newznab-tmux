@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    <x-breadcrumb :items="[
        ['label' => 'Home', 'url' => url($site['home_link'] ?? '/'), 'icon' => 'fas fa-home'],
        ['label' => 'Search'],
    ]" />

    <x-page-header title="Search Releases" description="Find exactly what you're looking for" icon="fas fa-search" />

    <!-- Search Form -->
    <form method="GET" action="{{ route('search') }}" class="px-6 pb-6" id="searchForm">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Search Query with Autocomplete -->
            <div class="lg:col-span-2 relative">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Terms</label>
                <div class="relative">
                    <input type="text"
                           id="search"
                           name="search"
                           value="{{ request('search') }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                           placeholder="Enter search terms..."
                           autocomplete="off"
                           @if(isset($autocompleteEnabled) && $autocompleteEnabled) data-autocomplete="true" @endif>
                    <!-- Autocomplete dropdown -->
                    <div id="autocomplete-dropdown" class="hidden absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    </div>
                </div>

                <!-- Spell Suggestion ("Did you mean?") -->
                @if(isset($spellSuggestion) && !empty($spellSuggestion))
                    <div class="mt-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Did you mean: </span>
                        <a href="{{ route('search', array_merge(request()->except('search'), ['search' => $spellSuggestion])) }}"
                           class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            {{ $spellSuggestion }}
                        </a>
                        <span class="text-gray-500 dark:text-gray-500">?</span>
                    </div>
                @endif
            </div>

            <!-- Category -->
            @php
                $selectedCategory = request('t', request('searchadvcat', ''));
            @endphp
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                <select id="category"
                        name="t"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <option value="">All Categories</option>
                    @if(isset($parentcatlist))
                        @foreach($parentcatlist as $parentcat)
                            @php
                                $parentId = is_object($parentcat) ? $parentcat->id : ($parentcat['id'] ?? '');
                                $parentTitle = is_object($parentcat) ? $parentcat->title : ($parentcat['title'] ?? 'Category');
                                $subcategories = is_object($parentcat) ? $parentcat->categories : ($parentcat['categories'] ?? []);
                            @endphp
                            <option value="{{ $parentId }}" class="font-semibold" {{ (string) $selectedCategory === (string) $parentId ? 'selected' : '' }}>
                                {{ $parentTitle }}
                            </option>
                            @foreach($subcategories as $subcat)
                                @php
                                    $subcatId = is_object($subcat) ? $subcat->id : ($subcat['id'] ?? '');
                                    $subcatTitle = is_object($subcat) ? $subcat->title : ($subcat['title'] ?? '');
                                @endphp
                                <option value="{{ $subcatId }}" {{ (string) $selectedCategory === (string) $subcatId ? 'selected' : '' }}>
                                    &nbsp;&nbsp;{{ $subcatTitle }}
                                </option>
                            @endforeach
                        @endforeach
                    @elseif(isset($catlist))
                        @foreach($catlist as $catId => $catTitle)
                            <option value="{{ $catId }}" {{ (string) $selectedCategory === (string) $catId ? 'selected' : '' }}>
                                {{ $catTitle }}
                            </option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        @if(request('search_type') == 'adv')
            <!-- Hidden field to maintain advanced search mode -->
            <input type="hidden" name="search_type" value="adv">
            <!-- Advanced Search Options -->
            <div class="surface-panel-alt rounded-lg p-4 mb-4 border">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Advanced Options</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="group" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Usenet Group</label>
                        <input type="text" id="group" name="group" value="{{ request('group') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               placeholder="e.g., alt.binaries.teevee">
                    </div>

                    <div>
                        <label for="minage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Age (days)</label>
                        <input type="number" id="minage" name="minage" value="{{ request('minage') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="maxage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Age (days)</label>
                        <input type="number" id="maxage" name="maxage" value="{{ request('maxage') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="minsize" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min Size (MB)</label>
                        <input type="number" id="minsize" name="minsize" value="{{ request('minsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="maxsize" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Size (MB)</label>
                        <input type="number" id="maxsize" name="maxsize" value="{{ request('maxsize') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               min="0">
                    </div>

                    <div>
                        <label for="poster" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Poster</label>
                        <input type="text" id="poster" name="searchadvposter" value="{{ request('searchadvposter') }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                               placeholder="e.g., poster@example.com">
                    </div>
                </div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2">
            <x-button type="submit" size="lg" icon="fas fa-search">Search</x-button>
            <x-button-link href="{{ url('/search?search_type=adv') }}" size="lg" icon="fas fa-sliders-h">Advanced Search</x-button-link>
            <x-button-link href="{{ route('search') }}" variant="muted" size="lg" class="justify-center">Clear</x-button-link>
        </div>
    </form>

    <!-- Search Results -->
    @if(isset($results) && ((is_array($results) && count($results) > 0) || (is_object($results) && $results->count() > 0)))
        <x-release-results-panel :results="$results" date-field="adddate">
            <x-slot:summary>
                <span class="font-semibold">{{ is_object($results) ? $results->total() : count($results) }}</span> results found
                @if(is_object($results))
                    - Page {{ $results->currentPage() }} of {{ $results->lastPage() }}
                @endif
            </x-slot:summary>
        </x-release-results-panel>
    @elseif(request()->has('search'))
        <x-empty-state
            icon="fas fa-search"
            title="No results found"
            message="Try adjusting your search terms or using different filters."
        />
    @else
        <x-empty-state
            icon="fas fa-search"
            title="Start Your Search"
            message="Enter search terms above to find releases."
        />
    @endif

    {{-- All modals (preview, mediainfo, filelist, NFO) are included globally via layouts.main --}}
</div>
@endsection
