@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site->home_link ?? '/') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 inline-flex items-center">
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
    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
        <form method="get" action="{{ route('Movies') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input type="text" id="title" name="title" value="{{ $title ?? '' }}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                    <select id="genre" name="genre" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">All</option>
                        @if(isset($genres))
                            @foreach($genres as $gen)
                                <option value="{{ $gen }}" {{ (isset($genre) && $genre == $gen) ? 'selected' : '' }}>{{ $gen }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                    <select id="year" name="year" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">All</option>
                        @if(isset($years))
                            @foreach($years as $yr)
                                <option value="{{ $yr }}" {{ (isset($year) && $year == $yr) ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                <div>
                    <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rating</label>
                    <select id="rating" name="rating" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
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
                <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700 dark:hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                <div class="text-sm text-gray-700 dark:text-gray-300 mb-3 sm:mb-0">
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
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="flex flex-col md:flex-row">
                            <!-- Movie Poster -->
                            <div class="flex-shrink-0">
                                @if($guid)
                                    <a href="{{ url('/details/' . $guid) }}" class="block">
                                        @if(isset($result->cover) && $result->cover)
                                            <img src="{{ $result->cover }}" alt="{{ $result->title }}" class="w-48 h-72 object-cover rounded" style="width: 192px; height: 288px;">
                                        @else
                                            <div class="w-48 h-72 bg-gray-200 dark:bg-gray-700 flex items-center justify-center rounded" style="width: 192px; height: 288px;">
                                                <i class="fas fa-film text-gray-400 text-3xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    @if(isset($result->cover) && $result->cover)
                                        <img src="{{ $result->cover }}" alt="{{ $result->title }}" class="w-48 h-72 object-cover rounded" style="width: 192px; height: 288px;">
                                    @else
                                        <div class="w-48 h-72 bg-gray-200 dark:bg-gray-700 flex items-center justify-center rounded" style="width: 192px; height: 288px;">
                                            <i class="fas fa-film text-gray-400 text-3xl"></i>
                                        </div>
                                    @endif
                                @endif
                            </div>

                            <!-- Movie Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        @if(isset($result->imdbid) && $result->imdbid)
                                            <a href="{{ route('movie.view', ['imdbid' => $result->imdbid]) }}" class="hover:text-blue-600">
                                                <h3 class="text-xl font-bold text-gray-900">{{ $result->title }}</h3>
                                            </a>
                                        @else
                                            <h3 class="text-xl font-bold text-gray-900">{{ $result->title }}</h3>
                                        @endif

                                        @if(!empty($result->grp_release_failed))
                                            @php
                                                $failedCounts = array_filter(explode(',', $result->grp_release_failed));
                                                $totalFailed = array_sum($failedCounts);
                                            @endphp
                                            @if($totalFailed > 0)
                                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200 mt-1">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                                </div>
                                            @endif
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
                                        </div>

                                        <!-- External Links -->
                                        <div class="flex items-center gap-3 mt-2 text-xs">
                                            @if(isset($result->imdbid) && $result->imdbid)
                                                <a href="https://www.imdb.com/title/tt{{ $result->imdbid }}" target="_blank" class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 rounded hover:bg-yellow-200 transition">
                                                    <i class="fab fa-imdb mr-1"></i> IMDb
                                                </a>
                                            @endif
                                            @if(isset($result->tmdbid) && $result->tmdbid)
                                                <a href="https://www.themoviedb.org/movie/{{ $result->tmdbid }}" target="_blank" class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 transition">
                                                    <i class="fas fa-film mr-1"></i> TMDb
                                                </a>
                                            @endif
                                            @if(isset($result->traktid) && $result->traktid)
                                                <a href="https://trakt.tv/movies/{{ $result->traktid }}" target="_blank" class="inline-flex items-center px-2 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200 transition">
                                                    <i class="fas fa-heart mr-1"></i> Trakt
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if(isset($result->plot) && $result->plot)
                                    <p class="text-gray-700 dark:text-gray-300 text-sm mt-3 line-clamp-3">{{ $result->plot }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->genre) && $result->genre)
                                        <div class="text-gray-600">
                                            <strong>Genre:</strong> {!! $result->genre !!}
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->director) && $result->director)
                                        <div class="text-gray-600">
                                            <strong>Director:</strong> {!! $result->director !!}
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    @if(isset($result->actors) && $result->actors)
                                        <div class="text-gray-600">
                                            <strong>Actors:</strong> {!! $result->actors !!}
                                        </div>
                                    @endif
                                </div>

                                <!-- Release Information -->
                                @if($guid)
                                    @php
                                        $releaseNames = isset($result->grp_release_name) ? explode('#', $result->grp_release_name) : [];
                                        $releaseSizes = isset($result->grp_release_size) ? explode(',', $result->grp_release_size) : [];
                                        $releaseGuids = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid) : [];
                                        $releasePostDates = isset($result->grp_release_postdate) ? explode(',', $result->grp_release_postdate) : [];
                                        $releaseAddDates = isset($result->grp_release_adddate) ? explode(',', $result->grp_release_adddate) : [];

                                        // Limit to maximum 2 releases
                                        $maxReleases = 2;
                                        $totalReleases = count($releaseNames);
                                        $releaseNames = array_slice($releaseNames, 0, $maxReleases);
                                        $releaseSizes = array_slice($releaseSizes, 0, $maxReleases);
                                        $releaseGuids = array_slice($releaseGuids, 0, $maxReleases);
                                        $releasePostDates = array_slice($releasePostDates, 0, $maxReleases);
                                        $releaseAddDates = array_slice($releaseAddDates, 0, $maxReleases);
                                    @endphp

                                    @if(!empty($releaseNames[0]))
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                                Available Releases
                                                @if($totalReleases > $maxReleases)
                                                    <span class="text-xs font-normal text-gray-500">(Showing {{ $maxReleases }} of {{ $totalReleases }})</span>
                                                @endif
                                            </h4>
                                            <div class="space-y-2">
                                                @foreach($releaseNames as $index => $releaseName)
                                                    @if($releaseName && isset($releaseGuids[$index]))
                                                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200">
                                                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2">
                                                                <div class="flex-1 min-w-0">
                                                                    <a href="{{ url('/details/' . $releaseGuids[$index]) }}" class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:text-blue-400 font-medium block truncate" title="{{ $releaseName }}">
                                                                        {{ $releaseName }}
                                                                    </a>
                                                                    <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                                                                        @if(isset($releaseSizes[$index]))
                                                                            <span>
                                                                                <i class="fas fa-hdd mr-1"></i>{{ number_format($releaseSizes[$index] / 1073741824, 2) }} GB
                                                                            </span>
                                                                        @endif
                                                                        @if(isset($releasePostDates[$index]))
                                                                            <span>
                                                                                <i class="fas fa-calendar-alt mr-1"></i>Posted: {{ \Carbon\Carbon::parse($releasePostDates[$index])->format('M d, Y') }}
                                                                            </span>
                                                                        @endif
                                                                        @if(isset($releaseAddDates[$index]))
                                                                            <span>
                                                                                <i class="fas fa-plus-circle mr-1"></i>Added: {{ \Carbon\Carbon::parse($releaseAddDates[$index])->diffForHumans() }}
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="flex gap-2 flex-shrink-0">
                                                                    <a href="{{ url('/getnzb/' . $releaseGuids[$index]) }}" class="inline-flex items-center px-3 py-1 bg-green-600 dark:bg-green-700 text-white text-xs font-medium rounded hover:bg-green-700 dark:hover:bg-green-800 transition">
                                                                        <i class="fas fa-download mr-1"></i> Download
                                                                    </a>
                                                                    <button onclick="addToCart('{{ $releaseGuids[$index] }}')" class="inline-flex items-center px-3 py-1 bg-blue-600 dark:bg-blue-700 text-white text-xs font-medium rounded hover:bg-blue-700 dark:hover:bg-blue-800 transition">
                                                                        <i class="fas fa-shopping-cart mr-1"></i> Cart
                                                                    </button>
                                                                    <a href="{{ url('/details/' . $releaseGuids[$index]) }}" class="inline-flex items-center px-3 py-1 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-700 transition">
                                                                        <i class="fas fa-info-circle mr-1"></i> Details
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
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
            <p class="text-gray-600 dark:text-gray-400 text-lg">No movies found.</p>
        </div>
    @endif
</div>

@push('scripts')
<script>
function addToCart(guid) {
    fetch('{{ url("/cart/add") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: guid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.showToast('Added to cart successfully!', 'success');
        } else {
            window.showToast('Failed to add to cart', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.showToast('An error occurred', 'error');
    });
}
</script>
@endpush
@endsection

