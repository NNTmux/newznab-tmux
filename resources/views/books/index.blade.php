@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/Books') }}" class="hover:text-blue-600">Books</a></li>
                @if(!empty($categorytitle) && $categorytitle !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500">{{ $categorytitle }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/Books/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Author Filter -->
                    <div>
                        <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Author</label>
                        <input type="text"
                               id="author"
                               name="author"
                               value="{{ $author ?? '' }}"
                               placeholder="Search by author"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select id="category"
                                name="t"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Categories</option>
                            @foreach($catlist ?? [] as $cat)
                                <option value="{{ $cat['id'] }}" {{ ($category ?? '') == $cat['id'] ? 'selected' : '' }}>
                                    {{ $cat['title'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700">
                        <i class="fa fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/Books/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        <i class="fa fa-book mr-2 text-blue-600"></i>
                        {{ $catname ?? 'All' }} Books
                    </h2>
                    <x-view-toggle
                        current-view="covers"
                        covgroup="books"
                        :category="$categorytitle ?? 'All'"
                        parentcat="Books"
                        :shows="false"
                    />
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $results->total() }} results found
                </span>
            </div>

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
                                <img src="{{ url('/covers/book/' . $result->cover) }}"
                                     alt="{{ $result->title }}"
                                     class="w-full h-64 object-cover"
                                     data-fallback-src="{{ url('/images/no-cover.png') }}">
                            @else
                                <div class="w-full h-64 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fa fa-book text-4xl text-gray-400"></i>
                                </div>
                            @endif
                            @if($totalFailed > 0)
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 bg-red-600 dark:bg-red-700 text-white text-xs rounded-full shadow-lg" title="{{ $totalFailed }} user(s) reported download failure">
                                        <i class="fa fa-exclamation-triangle mr-1"></i>Failed
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
                                <a href="{{ url('/getnzb?id=' . $guid) }}"
                                   class="flex-1 px-2 py-1 bg-green-600 dark:bg-green-700 text-white text-xs rounded hover:bg-green-700 dark:hover:bg-green-800 text-center"
                                   title="Download NZB">
                                    <i class="fa fa-download"></i>
                                </a>
                                <a href="{{ url('/details/' . $guid) }}"
                                   class="flex-1 px-2 py-1 bg-blue-600 dark:bg-blue-700 text-white text-xs rounded hover:bg-blue-700 dark:hover:bg-blue-800 text-center"
                                   title="View Details">
                                    <i class="fa fa-info-circle"></i>
                                </a>
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
            <!-- No Results -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                <i class="fa fa-book text-yellow-600 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No books found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your search filters or browse all books.</p>
                <a href="{{ url('/Books/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-book mr-2"></i> Browse All Books
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

