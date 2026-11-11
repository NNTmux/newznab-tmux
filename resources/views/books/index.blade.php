@extends('layouts.main')

@section('content')
<x-page-header title="Books" :current="!empty($categorytitle) ? $categorytitle : 'All books'" eyebrow="Discover and download" description="Browse books, compare releases, and narrow the library by author, title, or category." icon="fas fa-book-open">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-book" aria-hidden="true"></i>{{ number_format($results->total()) }} books</span>
        <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $categorytitle ?: 'All books' }}</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm">

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/Books/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Author Filter -->
                    <div>
                        <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Author</label>
                        <x-input type="text"
                               id="author"
                               name="author"
                               value="{{ $author ?? '' }}"
                               placeholder="Search by author" />
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <x-input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title" />
                    </div>

                    {{-- Category is chosen with the Category dropdown in the results toolbar. --}}
                    <input type="hidden" name="t" value="{{ $category ?? '' }}">
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                        <i aria-hidden="true" class="fa-solid fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/Books/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300">
                        <i aria-hidden="true" class="fa-solid fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <x-cover-results-toolbar
                :results="$results"
                icon="fa-solid fa-book"
                :title="($catname ?? 'All') . ' Books'"
                covgroup="books"
                :category="$categorytitle ?? 'All'"
                parentcat="Books"
                search-placeholder="Search in Books..."
                :search-category="$category ?? null"
                :category-items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, route('Books'), 'All books', true)"
                :ordering="$ordering ?? null"
            />

            <!-- Books Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 mb-6">
                @foreach($resultsadd as $result)
                    @php
                        $releases = $result->releases ?? [];
                        $firstRelease = !empty($releases) ? $releases[0] : null;
                        $guid = $firstRelease->guid ?? null;
                        $totalFailed = collect($releases)->sum(fn($r) => (int)($r->failed_count ?? 0));
                    @endphp
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <a href="{{ $guid ? url('/details/' . $guid) : '#' }}" class="block relative">
                            @if(!empty($result->cover))
                                <img width="320" height="480" src="{{ getImageAssetUrl('book', (string) $result->id, asset('assets/images/no-cover.png')) }}"
                                     alt="{{ $result->title }}"
                                     class="w-full h-64 object-cover"
                                     loading="lazy"
                                     data-fallback-src="{{ url('/images/no-cover.png') }}">
                            @else
                                <div class="w-full h-64 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <i aria-hidden="true" class="fa-solid fa-book text-4xl text-gray-400"></i>
                                </div>
                            @endif
                            @if($totalFailed > 0)
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 bg-danger-600 dark:bg-danger-700 text-white text-xs rounded-full shadow-lg" title="{{ $totalFailed }} user(s) reported download failure">
                                        <i aria-hidden="true" class="fa-solid fa-exclamation-triangle mr-1"></i>Failed
                                    </span>
                                </div>
                            @endif
                            <div class="p-3">
                                <h3 class="font-semibold text-sm text-gray-800 dark:text-gray-200 line-clamp-2 mb-1 wrap-break-word break-all" title="{{ $result->title }}">
                                    {{ $result->title }}
                                </h3>
                                @if(!empty($result->author))
                                    <p class="text-xs text-gray-600 dark:text-gray-400 truncate mb-1" title="{{ $result->author }}">
                                        {{ $result->author }}
                                    </p>
                                @endif
                                @if(!empty($result->publishdate))
                                    <p class="text-xs text-gray-500">{{ date('Y', strtotime($result->publishdate)) }}</p>
                                @endif
                            </div>
                        </a>
                        @if($guid)
                            <div class="px-3 pb-3 flex gap-1">
                                <a aria-label="Download NZB" href="{{ route('getnzb.guid', $guid) }}"
                                   class="flex-1 px-2 py-1 bg-success-600 dark:bg-success-700 text-white text-xs rounded hover:bg-success-700 dark:hover:bg-success-800 text-center"
                                   title="Download NZB">
                                    <i aria-hidden="true" class="fa-solid fa-download"></i>
                                </a>
                                <a aria-label="View Details" href="{{ url('/details/' . $guid) }}"
                                   class="flex-1 px-2 py-1 bg-accent dark:bg-accent-hover text-white text-xs rounded hover:bg-accent-hover dark:hover:bg-primary-800 text-center"
                                   title="View Details">
                                    <i aria-hidden="true" class="fa-solid fa-info-circle"></i>
                                </a>
                                <button type="button" aria-label="Add to basket" title="Add to basket" data-guid="{{ $guid }}"
                                        class="add-to-cart flex-1 px-2 py-1 border border-line bg-surface text-fg text-xs rounded hover:bg-surface-hover text-center">
                                    <i aria-hidden="true" class="icon_cart fa-solid fa-shopping-basket"></i>
                                </button>
                                @if(!empty($firstRelease->id))
                                    <x-report-button :release-id="$firstRelease->id" :reported-count="(int) ($firstRelease->total_report_count ?? $firstRelease->report_count ?? 0)" />
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $results->links() }}
            </div>
        @else
            <x-empty-state
                icon="fa-solid fa-book"
                title="No books found"
                message="Try adjusting your search filters or browse all books."
                :action-url="url('/Books/All')"
                action-label="Browse All Books"
                action-icon="fa-solid fa-book"
            />
        @endif
    </div>
</div>
@endsection
