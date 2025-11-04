<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fa fa-film mr-2"></i>{{ ucfirst($type ?? 'add') }} Movie to Watchlist
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-blue-100">
                        <li><a href="{{ url($site['home_link']) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li><a href="{{ url('/mymovies') }}" class="hover:text-white transition">My Movies</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">{{ ucfirst($type ?? 'add') }} Movie</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <img class="rounded-lg shadow-md w-24 h-auto"
                         src="{{ url("/covers/movies/{$imdbid}-cover.jpg") }}"
                         data-fallback-src="{{ url('/covers/movies/no-cover.jpg') }}"
                         alt="{{ e($movie['title'] ?? '') }}" />

                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                            {{ ucfirst($type ?? 'add') }} "{{ e($movie['title'] ?? '') }}" to watchlist
                        </h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Select categories below to organize this movie in your collection.</p>
                    </div>
                </div>

                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fa fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-3"></i>
                        <p class="text-sm text-gray-700">
                            Adding movies to your watchlist will notify you through your
                            <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                               class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 underline inline-flex items-center">
                                <i class="fa fa-rss mr-1"></i>RSS Feed
                            </a>
                            when they become available.
                        </p>
                    </div>
                </div>
            </div>

            {{ html()->form('POST', url("mymovies?id=do{$type}"))->id('mymovies')->class('space-y-6')->open() }}
                <input type="hidden" name="imdb" value="{{ $imdbid }}"/>
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Choose Categories:</label>
                    <div class="flex flex-wrap gap-3" id="category-container">
                        @foreach($cat_ids ?? [] as $index => $cat_id)
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:bg-gray-800 transition-all duration-200 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-500 has-[:checked]:text-blue-700">
                                <input type="checkbox"
                                       id="category_{{ $cat_id }}"
                                       name="category[]"
                                       value="{{ $cat_id }}"
                                       class="mr-2 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500"
                                       @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                                <span class="text-sm font-medium">{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button class="px-6 py-3 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
                            type="submit" name="{{ $type ?? 'add' }}">
                        <i class="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }} mr-2"></i>{{ ucfirst($type ?? 'add') }} Movie
                    </button>
                    <a href="{{ url('/mymovies') }}"
                       class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:bg-gray-900 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium">
                        <i class="fa fa-arrow-left mr-2"></i>Back to My Movies
                    </a>
                </div>
            {{ html()->form()->close() }}
        </div>
    </div>
</div>
<div class="card card-default shadow-sm mb-4">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h3 class="mb-0"><i class="fa fa-film me-2 text-primary"></i>My Movies</h3>
            <div class="breadcrumb-wrapper">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 py-0">
                        <li class="breadcrumb-item"><a href="{{ url($site['home_link']) }}">Home</a></li>
                        <li class="breadcrumb-item active">My Movies</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <i class="fa fa-info-circle me-2"></i>
            Using 'My Movies' you can search for movies, and add them to a wishlist. If the movie becomes available it will be added to an
            <strong><a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="alert-link">
                <i class="fa fa-rss me-1"></i>RSS Feed
            </a></strong>
            you can use to automatically download. You can
            <strong><a href="{{ route('mymovies') }}" class="alert-link">
                <i class="fa fa-list me-1"></i>Manage Your Movie List
            </a></strong>
            to remove old items.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="btn btn-outline-secondary">
                <i class="fa fa-rss me-2"></i>RSS Feed
            </a>
        </div>

        @if(count($movies ?? []) > 0)
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width:140px">Cover</th>
                            <th>Information</th>
                            <th>Category</th>
                            <th>Added</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($movies as $movie)
                            <tr>
                                <td>
                                    <div class="text-center">
                                        <img class="img-fluid rounded shadow-sm" style="max-width:120px"
                                             src="{{ url('/covers/movies/' . (($movie['cover'] ?? 0) == 1 ? $movie['imdbid'] . '-cover.jpg' : 'no-cover.jpg')) }}"
                                             alt="{{ e($movie['title'] ?? '') }}"/>
                                    </div>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <h5 class="mb-1">
                                            <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-decoration-none" data-bs-toggle="tooltip" data-bs-placement="top" title="View movie details">
                                                {{ e($movie['title'] ?? '') }} ({{ $movie['year'] ?? '' }})
                                            </a>
                                        </h5>

                                        @if(!empty($movie['tagline']))
                                            <div class="fst-italic text-muted mb-2">{{ e($movie['tagline']) }}</div>
                                        @endif
                                    </div>

                                    @if(!empty($movie['plot']))
                                        <p class="mb-2">{{ e($movie['plot']) }}</p>
                                    @endif

                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        @if(!empty($movie['genre']))
                                            <div>
                                                <span class="fw-bold text-secondary"><i class="fa fa-tag me-1"></i>Genre:</span> {{ e($movie['genre']) }}
                                            </div>
                                        @endif

                                        @if(!empty($movie['director']))
                                            <div>
                                                <span class="fw-bold text-secondary"><i class="fa fa-video-camera me-1"></i>Director:</span> {{ e($movie['director']) }}
                                            </div>
                                        @endif

                                        @if(!empty($movie['actors']))
                                            <div class="w-100 mt-1">
                                                <span class="fw-bold text-secondary"><i class="fa fa-users me-1"></i>Starring:</span> {{ e($movie['actors']) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-2">
                                        <a class="badge bg-warning text-dark" target="_blank"
                                           href="{{ $site['dereferrer_link'] }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="View on IMDB">
                                            <i class="fa fa-external-link me-1"></i>IMDB
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary rounded-pill">
                                        <i class="fa fa-folder-open me-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center" title="Added on {{ $movie['created_at'] ?? '' }}">
                                        <i class="fa fa-calendar text-muted me-2"></i>
                                        {{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-warning mymovies"
                                           href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" rel="edit"
                                           name="movies{{ $movie['imdbid'] }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Categories">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <a class="btn btn-sm btn-danger mymovies"
                                           href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}" rel="remove"
                                           name="movies{{ $movie['imdbid'] }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove from My Movies">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-info">
                <i class="fa fa-info-circle me-2"></i>No movies bookmarked yet. Add movies from movie pages.
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});
</script>

