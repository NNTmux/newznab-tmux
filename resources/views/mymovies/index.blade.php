<style>
    .header-gradient {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    }
    .info-card-gradient {
        background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
    }
    .table-header-gradient {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    }
    .movie-poster-shadow {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .category-badge {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }
    .empty-state-bg {
        background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
    }
</style>

<div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="header-gradient rounded-xl shadow-lg mb-6 overflow-hidden">
        <div class="px-8 py-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fa fa-film mr-3"></i>My Movies
                    </h1>
                    <p class="text-blue-100 mt-2">Manage your movie wishlist and get automatic updates</p>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-blue-100">
                        <li><a href="{{ url($site->home_link) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">My Movies</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- RSS Feed Info Card -->
    <div class="info-card-gradient border-l-4 border-blue-500 rounded-lg p-5 mb-6 shadow">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-500 text-white">
                    <i class="fa fa-rss text-lg"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Automatic Downloads via RSS</h3>
                <p class="text-sm text-gray-700">
                    Using 'My Movies' you can search for movies and add them to a wishlist. If the movie becomes available it will be added to your
                    <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                       class="font-semibold text-blue-600 hover:text-blue-800 underline inline-flex items-center">
                        <i class="fa fa-rss mr-1"></i>personal RSS feed
                    </a>
                    for automatic downloading.
                </p>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-3 mb-6">
        <a class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
           href="{{ url('/browse/Movies') }}"
           title="Browse all available movies">
            <i class="fa fa-list mr-2"></i>Browse All Movies
        </a>
        <a class="px-6 py-3 bg-white text-gray-700 border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
           href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
           title="All movies in your watchlist as an RSS feed">
            <i class="fa fa-rss mr-2 text-orange-500"></i>RSS Feed
        </a>
    </div>

    <!-- Movies Table/Cards -->
    @if(count($movies ?? []) > 0)
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <!-- Table Header -->
            <div class="table-header-gradient px-6 py-4 border-b-2 border-gray-300">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fa fa-bookmark mr-2 text-blue-600"></i>
                        Your Movies <span class="ml-2 px-3 py-1 bg-blue-600 text-white text-sm rounded-full font-bold">{{ count($movies) }}</span>
                    </h2>
                </div>
            </div>

            <!-- Desktop Table View -->
            <div class="hidden lg:block">
                <div class="divide-y divide-gray-200">
                    @foreach($movies as $movie)
                        <div class="p-6 hover:bg-blue-50 transition-colors duration-150">
                            <div class="flex gap-6">
                                <!-- Movie Poster -->
                                <div class="flex-shrink-0">
                                    <img class="rounded-lg movie-poster-shadow w-32 h-48 object-cover"
                                         src="{{ url('/covers/movies/' . (($movie['cover'] ?? 0) == 1 ? $movie['imdbid'] . '-cover.jpg' : 'no-cover.jpg')) }}"
                                         alt="{{ e($movie['title'] ?? '') }}"/>
                                </div>

                                <!-- Movie Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <h3 class="text-xl font-bold text-gray-900 mb-1">
                                                <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}"
                                                   class="hover:text-blue-600 transition-colors">
                                                    {{ e($movie['title'] ?? '') }}
                                                    @if(!empty($movie['year']))
                                                        <span class="text-gray-500 font-normal">({{ $movie['year'] }})</span>
                                                    @endif
                                                </a>
                                            </h3>
                                            @if(!empty($movie['tagline']))
                                                <p class="text-sm italic text-gray-600 mb-2">{{ e($movie['tagline']) }}</p>
                                            @endif
                                        </div>
                                        <div class="flex gap-2 ml-4">
                                            <a class="inline-flex items-center px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 shadow hover:shadow-md transition-all duration-200 text-sm font-medium"
                                               href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}"
                                               title="Edit Categories">
                                                <i class="fa fa-edit mr-1.5"></i>Edit
                                            </a>
                                            <a class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 shadow hover:shadow-md transition-all duration-200 text-sm font-medium confirm_action"
                                               href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}"
                                               title="Remove from My Movies">
                                                <i class="fa fa-trash mr-1.5"></i>Delete
                                            </a>
                                        </div>
                                    </div>

                                    @if(!empty($movie['plot']))
                                        <p class="text-sm text-gray-700 mb-3 line-clamp-3">{{ e($movie['plot']) }}</p>
                                    @endif

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                        @if(!empty($movie['genre']))
                                            <div class="flex items-center text-sm">
                                                <span class="font-semibold text-gray-700 mr-2"><i class="fa fa-tag mr-1 text-blue-600"></i>Genre:</span>
                                                <span class="text-gray-600">{{ e($movie['genre']) }}</span>
                                            </div>
                                        @endif

                                        @if(!empty($movie['director']))
                                            <div class="flex items-center text-sm">
                                                <span class="font-semibold text-gray-700 mr-2"><i class="fa fa-video-camera mr-1 text-blue-600"></i>Director:</span>
                                                <span class="text-gray-600">{{ e($movie['director']) }}</span>
                                            </div>
                                        @endif
                                    </div>

                                    @if(!empty($movie['actors']))
                                        <div class="flex items-start text-sm mb-3">
                                            <span class="font-semibold text-gray-700 mr-2 flex-shrink-0"><i class="fa fa-users mr-1 text-blue-600"></i>Starring:</span>
                                            <span class="text-gray-600">{{ e($movie['actors']) }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-4 pt-3 border-t border-gray-200">
                                        <span class="inline-flex items-center px-3 py-1.5 category-badge text-blue-800 text-xs font-semibold rounded-full border border-blue-200">
                                            <i class="fa fa-folder-open mr-1.5"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All Categories' }}
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center">
                                            <i class="fa fa-calendar mr-1"></i>Added {{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                        </span>
                                        <a class="inline-flex items-center px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-xs font-semibold rounded-full transition-colors"
                                           target="_blank"
                                           href="{{ $site->dereferrer_link }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}"
                                           title="View on IMDB">
                                            <i class="fa fa-external-link mr-1"></i>IMDB
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Mobile/Tablet Card View -->
            <div class="lg:hidden">
                @foreach($movies as $movie)
                    <div class="p-6 border-b border-gray-200 hover:bg-blue-50 transition-colors duration-150">
                        <div class="flex gap-4 mb-4">
                            <div class="flex-shrink-0">
                                <img class="rounded-lg movie-poster-shadow w-24 h-36 object-cover"
                                     src="{{ url('/covers/movies/' . (($movie['cover'] ?? 0) == 1 ? $movie['imdbid'] . '-cover.jpg' : 'no-cover.jpg')) }}"
                                     alt="{{ e($movie['title'] ?? '') }}"/>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-gray-900 mb-1">
                                    <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}"
                                       class="hover:text-blue-600">
                                        {{ e($movie['title'] ?? '') }}
                                        @if(!empty($movie['year']))
                                            <span class="text-gray-500 text-sm">({{ $movie['year'] }})</span>
                                        @endif
                                    </a>
                                </h3>
                                @if(!empty($movie['tagline']))
                                    <p class="text-xs italic text-gray-600 mb-2">{{ e($movie['tagline']) }}</p>
                                @endif
                                <span class="inline-flex items-center px-2 py-1 category-badge text-blue-800 text-xs font-semibold rounded-full border border-blue-200">
                                    <i class="fa fa-folder-open mr-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                </span>
                            </div>
                        </div>

                        @if(!empty($movie['plot']))
                            <p class="text-sm text-gray-700 mb-3 line-clamp-3">{{ e($movie['plot']) }}</p>
                        @endif

                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div class="flex gap-2">
                                <a class="inline-flex items-center px-3 py-1.5 bg-yellow-400 hover:bg-yellow-500 text-gray-900 text-xs font-semibold rounded-full transition-colors"
                                   target="_blank"
                                   href="{{ $site->dereferrer_link }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}">
                                    <i class="fa fa-external-link mr-1"></i>IMDB
                                </a>
                                <span class="text-xs text-gray-500 flex items-center">
                                    <i class="fa fa-calendar mr-1"></i>{{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <a class="px-3 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium shadow"
                                   href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}"
                                   title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm font-medium shadow confirm_action"
                                   href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}"
                                   title="Remove">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
            <div class="text-center py-16 px-6">
                <div class="mx-auto h-24 w-24 empty-state-bg rounded-full flex items-center justify-center mb-6 shadow-sm">
                    <i class="fa fa-film text-5xl text-blue-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">No Movies in Watchlist</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    You haven't bookmarked any movies yet. Search for movies and add them to your watchlist.
                </p>
                <a href="{{ url('/browse/Movies') }}"
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md hover:shadow-lg transition-all duration-200 font-medium">
                    <i class="fa fa-search mr-2"></i>Browse Movies
                </a>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirm before removing movies
    document.querySelectorAll('.confirm_action').forEach(element => {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this movie from your watchlist?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

