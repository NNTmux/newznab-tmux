@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/Audio') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Audio</a></li>
                @if(!empty($categorytitle) && $categorytitle !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500 dark:text-gray-400">{{ $categorytitle }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Artist Filter -->
                    <div>
                        <label for="artist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
                        <input type="text"
                               id="artist"
                               name="artist"
                               value="{{ $artist ?? '' }}"
                               placeholder="Search by artist"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Genre Filter -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                        <select id="genre"
                                name="genre"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Genres</option>
                            @foreach($genres ?? [] as $g)
                                <option value="{{ $g->id }}" {{ ($genre ?? '') == $g->id ? 'selected' : '' }}>
                                    {{ $g->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                        <select id="year"
                                name="year"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Years</option>
                            @foreach($years ?? [] as $y)
                                <option value="{{ $y }}" {{ ($year ?? '') == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800">
                        <i class="fa fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>


        <!-- Results -->
        @if(count($results) > 0)
            <div class="mb-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-music mr-2 text-blue-600 dark:text-blue-400"></i>
                    {{ $catname ?? 'All' }} Albums
                </h2>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $results->total() }} results found
                </span>
            </div>

            <!-- Album Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 mb-6">
                @foreach($resultsadd as $result)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <a href="{{ url('/details/' . $result->guid) }}" class="block relative">
                            @if(!empty($result->cover))
                                <img src="{{ url('/covers/music/' . $result->cover) }}"
                                     alt="{{ $result->artist ?? '' }} - {{ $result->title ?? '' }}"
                                     class="w-full h-48 object-cover"
                                     data-fallback-src="{{ url('/images/no-cover.png') }}">
                            @else
                                <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <i class="fa fa-music text-4xl text-gray-400"></i>
                                </div>
                            @endif
                            @if(!empty($result->failed) && $result->failed > 0)
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 bg-red-600 dark:bg-red-700 text-white text-xs rounded-full shadow-lg" title="{{ $result->failed }} user(s) reported download failure">
                                        <i class="fa fa-exclamation-triangle mr-1"></i>Failed
                                    </span>
                                </div>
                            @endif
                            <div class="p-3">
                                <h3 class="font-semibold text-sm text-gray-800 dark:text-gray-200 break-words break-all" title="{{ $result->title ?? $result->searchname }}">
                                    {{ $result->title ?? $result->searchname }}
                                </h3>
                                <p class="text-xs text-gray-600 dark:text-gray-400 truncate" title="{{ $result->artist ?? '' }}">
                                    {{ $result->artist ?? 'Unknown Artist' }}
                                </p>
                                @if(!empty($result->year))
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $result->year }}</p>
                                @endif
                                @if(!empty($result->genre))
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1 truncate">{{ $result->genre }}</p>
                                @endif
                            </div>
                        </a>
                        <div class="px-3 pb-3 flex gap-1">
                            <a href="{{ url('/getnzb?id=' . $result->guid) }}"
                               class="flex-1 px-2 py-1 bg-green-600 dark:bg-green-700 text-white text-xs rounded hover:bg-green-700 dark:hover:bg-green-800 text-center"
                               title="Download NZB">
                                <i class="fa fa-download"></i>
                            </a>
                            <a href="{{ url('/details/' . $result->guid) }}"
                               class="flex-1 px-2 py-1 bg-blue-600 dark:bg-blue-700 text-white text-xs rounded hover:bg-blue-700 dark:hover:bg-blue-800 text-center"
                               title="View Details">
                                <i class="fa fa-info-circle"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $results->links() }}
            </div>
        @else
            <!-- No Results -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-8 text-center">
                <i class="fa fa-music text-yellow-600 dark:text-yellow-500 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No albums found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your search filters or browse all music.</p>
                <a href="{{ url('/browse/Audio/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800">
                    <i class="fa fa-music mr-2"></i> Browse All Audio
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

