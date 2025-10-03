@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site->home_link ?? '/') }}" class="text-gray-700 hover:text-blue-600 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">Movies</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Movies Filter Section -->
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
        <form method="get" action="{{ route('Movies') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input type="text" id="title" name="title" value="{{ $title ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label for="genre" class="block text-sm font-medium text-gray-700 mb-1">Genre</label>
                    <select id="genre" name="genre" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        @if(isset($genres))
                            @foreach($genres as $gen)
                                <option value="{{ $gen }}" {{ (isset($genre) && $genre == $gen) ? 'selected' : '' }}>{{ $gen }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <select id="year" name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        @if(isset($years))
                            @foreach($years as $yr)
                                <option value="{{ $yr }}" {{ (isset($year) && $year == $yr) ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                    <select id="rating" name="rating" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        @if(isset($ratings))
                            @foreach($ratings as $rate)
                                <option value="{{ $rate }}" {{ (isset($rating) && $rating == $rate) ? 'selected' : '' }}>{{ $rate }}+</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
        </form>
    </div>

    <!-- Movies List -->
    @if(isset($results) && $results->count() > 0)
        <div class="px-6 py-4">
            <!-- Results Summary and Pagination -->
            <div class="flex flex-col sm:flex-row justify-between items-center mb-4 pb-4 border-b border-gray-200">
                <div class="text-sm text-gray-700 mb-3 sm:mb-0">
                    Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} movies
                </div>
                <div>
                    {{ $results->links() }}
                </div>
            </div>

            <div class="space-y-4">
                @foreach($results as $result)
                    @php
                        // Get the first GUID from the comma-separated list
                        $guid = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid)[0] : null;
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="flex flex-col md:flex-row">
                            <!-- Movie Poster -->
                            <div class="md:w-48 flex-shrink-0">
                                @if($guid)
                                    <a href="{{ url('/details/' . $guid) }}" class="block">
                                        @if(isset($result->cover) && $result->cover)
                                            <img src="{{ $result->cover }}" alt="{{ $result->title }}" class="w-full h-64 md:h-full object-cover">
                                        @else
                                            <div class="w-full h-64 md:h-full bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-film text-gray-400 text-4xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    @if(isset($result->cover) && $result->cover)
                                        <img src="{{ $result->cover }}" alt="{{ $result->title }}" class="w-full h-64 md:h-full object-cover">
                                    @else
                                        <div class="w-full h-64 md:h-full bg-gray-200 flex items-center justify-center">
                                            <i class="fas fa-film text-gray-400 text-4xl"></i>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- Movie Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        @if($guid)
                                            <a href="{{ url('/details/' . $guid) }}" class="hover:text-blue-600">
                                                <h3 class="text-xl font-bold text-gray-900">{{ $result->title }}</h3>
                                            </a>
                                        @else
                                            <h3 class="text-xl font-bold text-gray-900">{{ $result->title }}</h3>
                                        @endif

                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600">
                                            @if(isset($result->year) && $result->year)
                                                <span><i class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                                            @endif
                                            @if(isset($result->rating) && $result->rating)
                                                <span class="text-yellow-600">
                                                    <i class="fas fa-star mr-1"></i> {{ $result->rating }}
                                                </span>
                                            @endif
                                            @if(isset($result->imdbid) && $result->imdbid)
                                                <a href="https://www.imdb.com/title/tt{{ $result->imdbid }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fab fa-imdb mr-1"></i> IMDb
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if(isset($result->plot) && $result->plot)
                                    <p class="text-gray-700 text-sm mt-2 line-clamp-3">{{ $result->plot }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->genre) && $result->genre)
                                        <div class="text-gray-600">
                                            <strong>Genre:</strong> {!! $result->genre !!}
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->director) && $result->director)
                                        <div class="text-gray-600">
                                            <strong>Director:</strong> {!! $result->director !!}
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->actors) && $result->actors)
                                        <div class="text-gray-600">
                                            <strong>Actors:</strong> {!! $result->actors !!}
                                        </div>
                                    @endif
                                </div>

                                @if($guid)
                                    <div class="mt-4">
                                        <a href="{{ url('/details/' . $guid) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <i class="fas fa-info-circle mr-2"></i> View Details
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $results->links() }}
            </div>
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-film text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 text-lg">No movies found.</p>
        </div>
    @endif
</div>
@endsection

