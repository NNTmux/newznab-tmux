@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site->home_link ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/XXX') }}" class="hover:text-blue-600">XXX</a></li>
                @if(!empty($categorytitle) && $categorytitle !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500">{{ $categorytitle }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/XXX/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Actors Filter -->
                    <div>
                        <label for="actors" class="block text-sm font-medium text-gray-700 mb-1">Actors</label>
                        <input type="text"
                               id="actors"
                               name="actors"
                               value="{{ $actors ?? '' }}"
                               placeholder="Search by actors"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Director Filter -->
                    <div>
                        <label for="director" class="block text-sm font-medium text-gray-700 mb-1">Director</label>
                        <input type="text"
                               id="director"
                               name="director"
                               value="{{ $director ?? '' }}"
                               placeholder="Search by director"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Genre Filter -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 mb-1">Genre</label>
                        <select id="genre"
                                name="genre"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Genres</option>
                            @foreach($genres ?? [] as $g)
                                <option value="{{ $g }}" {{ ($genre ?? '') == $g ? 'selected' : '' }}>
                                    {{ $g }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fa fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/XXX/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <div class="mb-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fa fa-film mr-2 text-blue-600"></i>
                    {{ $catname ?? 'All' }} XXX
                </h2>
                <span class="text-sm text-gray-600">
                    {{ $results->total() }} results found
                </span>
            </div>

            <!-- XXX Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 mb-6">
                @foreach($resultsadd as $result)
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200">
                        <a href="{{ url('/details/' . $result->guid) }}" class="block relative">
                            @if(!empty($result->cover))
                                <img src="{{ url('/covers/xxx/' . $result->cover) }}"
                                     alt="{{ $result->title ?? $result->searchname }}"
                                     class="w-full h-48 object-cover"
                                     onerror="this.src='{{ url('/images/no-cover.png') }}'">
                            @else
                                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                    <i class="fa fa-film text-4xl text-gray-400"></i>
                                </div>
                            @endif
                            @if(!empty($result->failed) && $result->failed > 0)
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-1 bg-red-600 text-white text-xs rounded-full shadow-lg" title="{{ $result->failed }} user(s) reported download failure">
                                        <i class="fa fa-exclamation-triangle mr-1"></i>Failed
                                    </span>
                                </div>
                            @endif
                            <div class="p-3">
                                <h3 class="font-semibold text-sm text-gray-800 line-clamp-2 mb-1" title="{{ $result->title ?? $result->searchname }}">
                                    {{ $result->title ?? $result->searchname }}
                                </h3>
                                @if(!empty($result->releasedate))
                                    <p class="text-xs text-gray-500">{{ date('Y', strtotime($result->releasedate)) }}</p>
                                @elseif(!empty($result->year))
                                    <p class="text-xs text-gray-500">{{ $result->year }}</p>
                                @endif
                                @if(!empty($result->genre))
                                    <div class="text-xs text-blue-600 mt-1 line-clamp-1">
                                        {!! $result->genre !!}
                                    </div>
                                @endif
                                @if(!empty($result->director))
                                    <div class="text-xs text-gray-600 mt-1 line-clamp-1" title="Director">
                                        <i class="fa fa-user-circle mr-1"></i>{!! $result->director !!}
                                    </div>
                                @endif
                            </div>
                        </a>
                        <div class="px-3 pb-3 flex gap-1">
                            <a href="{{ url('/getnzb?id=' . $result->guid) }}"
                               class="flex-1 px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 text-center"
                               title="Download NZB">
                                <i class="fa fa-download"></i>
                            </a>
                            <a href="{{ url('/details/' . $result->guid) }}"
                               class="flex-1 px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 text-center"
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
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                <i class="fa fa-film text-yellow-600 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">No content found</h3>
                <p class="text-gray-600 mb-4">Try adjusting your search filters or browse all content.</p>
                <a href="{{ url('/XXX/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-film mr-2"></i> Browse All XXX
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

