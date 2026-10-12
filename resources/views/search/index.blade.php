@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
@php
    $validatedQuery = $listQuery ?? null;
    $filterValue = static function (string $key, string $fallback = '') use ($validatedQuery): string {
        $value = old($key, $validatedQuery[$key] ?? request($key, $fallback));
        return is_scalar($value) ? (string) $value : '';
    };
    $searchValue = $filterValue('search', $filterValue('subject', $filterValue('id', $filterValue('searchadvr'))));
    $selectedCategory = $filterValue('t', $filterValue('searchadvcat'));
    $searchQuery = array_filter($validatedQuery ?? request()->query(), is_scalar(...));
    unset($searchQuery['page']);
    $sortValue = $filterValue('ob', 'posted_desc');
    $allowedSorts = ['name_asc', 'name_desc', 'cat_asc', 'cat_desc', 'added_asc', 'added_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc', 'comments_asc', 'comments_desc'];
    $sortValue = in_array($sortValue, $allowedSorts, true) ? $sortValue : 'posted_desc';
    $advancedSearch = $filterValue('search_type') === 'adv';
    $advancedFields = [
        'group' => ['label' => 'Usenet Group', 'type' => 'text', 'alias' => 'searchadvgroups'],
        'searchadvposter' => ['label' => 'Poster', 'type' => 'text'],
        'added_from' => ['label' => 'Added From (inclusive)', 'type' => 'date'],
        'added_to' => ['label' => 'Added To (inclusive)', 'type' => 'date'],
        'minage' => ['label' => 'Min Posted Age (days)', 'type' => 'number', 'alias' => 'searchadvdaysnew'],
        'maxage' => ['label' => 'Max Posted Age (days)', 'type' => 'number', 'alias' => 'searchadvdaysold'],
        'minsize' => ['label' => 'Min Size (MB)', 'type' => 'number', 'alias' => 'searchadvsizefrom'],
        'maxsize' => ['label' => 'Max Size (MB)', 'type' => 'number', 'alias' => 'searchadvsizeto'],
        'minfiles' => ['label' => 'Min Files', 'type' => 'number'],
        'maxfiles' => ['label' => 'Max Files', 'type' => 'number'],
        'mingrabs' => ['label' => 'Min Grabs', 'type' => 'number'],
        'maxgrabs' => ['label' => 'Max Grabs', 'type' => 'number'],
        'mincomments' => ['label' => 'Min Comments', 'type' => 'number'],
        'maxcomments' => ['label' => 'Max Comments', 'type' => 'number'],
    ];
@endphp
<div class="surface-panel rounded-xl shadow-sm">
    <x-breadcrumb :items="[
        ['label' => 'Home', 'url' => url($site->homeLink ?? '/'), 'icon' => 'fas fa-home'],
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
                           value="{{ $searchValue }}"
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
                        <a href="{{ route('search', array_merge($searchQuery, ['search' => $spellSuggestion])) }}"
                           class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            {{ $spellSuggestion }}
                        </a>
                        <span class="text-gray-500 dark:text-gray-500">?</span>
                    </div>
                @endif
            </div>

            <!-- Category -->
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

        <input type="hidden" name="ob" value="{{ $sortValue }}">
        @foreach(['searchadvsubject', 'searchadvfilename'] as $legacyField)
            @php($legacyValue = $filterValue($legacyField))
            @if($legacyValue !== '')
                <input type="hidden" name="{{ $legacyField }}" value="{{ $legacyValue }}">
            @endif
        @endforeach
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200" role="alert">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if($advancedSearch)
            <input type="hidden" name="search_type" value="adv">
            <div class="surface-panel-alt rounded-lg p-4 mb-4 border">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Advanced Filters</h3>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Leave bounds blank for no limit. Minimums must not exceed maximums. Posted age is measured in days; size is measured in MB.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($advancedFields as $field => $definition)
                        <div>
                            <label for="{{ $field }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $definition['label'] }}</label>
                            <input type="{{ $definition['type'] }}" id="{{ $field }}" name="{{ $field }}"
                                   value="{{ $filterValue($field, isset($definition['alias']) ? $filterValue($definition['alias']) : '') }}"
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                                   @if($definition['type'] === 'number') min="0" step="{{ in_array($field, ['minsize', 'maxsize'], true) ? 'any' : '1' }}" @endif>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            @foreach($advancedFields as $field => $definition)
                @if(isset($searchQuery[$field]))
                    <input type="hidden" name="{{ $field }}" value="{{ $searchQuery[$field] }}">
                @endif
            @endforeach
        @endif

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2">
            <x-button type="submit" size="lg" icon="fas fa-search">Search</x-button>
            <x-button-link href="{{ route('search', array_merge($searchQuery, ['search_type' => 'adv'])) }}" size="lg" icon="fas fa-sliders-h" aria-label="Advanced search filters">Advanced Filters</x-button-link>
            <a href="{{ route('search') }}" class="inline-flex justify-center rounded-lg bg-gray-300 px-6 py-3 text-base font-semibold text-gray-700 transition hover:bg-gray-400 dark:bg-gray-600 dark:text-gray-100 dark:hover:bg-gray-700">
                Clear
            </a>
        </div>
    </form>

    <!-- Search Results -->
    @if(isset($results) && ((is_array($results) && count($results) > 0) || (is_object($results) && $results->count() > 0)))
        <x-release-results-panel :results="$results" date-field="adddate" :query-params="$searchQuery">
            <x-slot:summary>
                <span class="font-semibold">{{ is_object($results) ? $results->total() : count($results) }}</span> results found
                @if(is_object($results))
                    - Page {{ $results->currentPage() }} of {{ $results->lastPage() }}
                @endif
            </x-slot:summary>
        </x-release-results-panel>
    @elseif($searchSubmitted ?? ($searchValue !== '' || $advancedSearch))
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
