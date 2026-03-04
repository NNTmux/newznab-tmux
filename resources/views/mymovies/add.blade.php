<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <!-- Header -->
        <div class="bg-linear-to-r from-blue-600 to-blue-700 px-6 py-4">
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

            {{ html()->form()->method('POST')->action(url("mymovies?id=do{$type}"))->id('mymovies')->class('space-y-6')->open() }}
                <input type="hidden" name="imdb" value="{{ $imdbid }}"/>
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Choose Categories:</label>
                    <div class="flex flex-wrap gap-3" id="category-container">
                        @foreach($cat_ids ?? [] as $index => $cat_id)
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:bg-gray-800 transition-all duration-200 has-checked:bg-blue-50 has-checked:border-blue-500 has-checked:text-blue-700">
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
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="bg-linear-to-r from-blue-600 to-blue-700 px-6 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fa fa-film mr-2"></i>My Movies
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-blue-100">
                        <li><a href="{{ url($site['home_link']) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">My Movies</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                <div class="flex items-start">
                    <i class="fa fa-info-circle text-blue-600 dark:text-blue-400 mt-0.5 mr-3"></i>
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        Using 'My Movies' you can search for movies and add them to a wishlist. If the movie becomes available it will be added to an
                        <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="font-semibold underline hover:text-blue-600 dark:hover:text-blue-300">
                            <i class="fa fa-rss mr-1"></i>RSS Feed
                        </a>
                        you can use to automatically download. You can
                        <a href="{{ route('mymovies') }}" class="font-semibold underline hover:text-blue-600 dark:hover:text-blue-300">
                            <i class="fa fa-list mr-1"></i>Manage Your Movie List
                        </a>
                        to remove old items.
                    </p>
                </div>
            </div>

            <div class="flex justify-between items-center mb-4">
                <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="btn btn-secondary btn-sm">
                    <i class="fa fa-rss mr-2"></i>RSS Feed
                </a>
            </div>

            @if(count($movies ?? []) > 0)
                <!-- Desktop Table -->
                <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">
                                <th class="px-4 py-3 w-36">Cover</th>
                                <th class="px-4 py-3">Information</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Added</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($movies as $movie)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-3">
                                        <img class="rounded-lg shadow-sm max-w-[120px]"
                                             src="{{ url('/covers/movies/' . (($movie['cover'] ?? 0) == 1 ? $movie['imdbid'] . '-cover.jpg' : 'no-cover.jpg')) }}"
                                             alt="{{ e($movie['title'] ?? '') }}"/>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="mb-2">
                                            <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-blue-600 dark:hover:text-blue-400 transition" title="View movie details">
                                                {{ e($movie['title'] ?? '') }} ({{ $movie['year'] ?? '' }})
                                            </a>
                                            @if(!empty($movie['tagline']))
                                                <div class="italic text-gray-500 dark:text-gray-400 text-xs mt-1">{{ e($movie['tagline']) }}</div>
                                            @endif
                                        </div>
                                        @if(!empty($movie['plot']))
                                            <p class="text-gray-600 dark:text-gray-300 text-sm mb-2">{{ e($movie['plot']) }}</p>
                                        @endif
                                        <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400 mt-2">
                                            @if(!empty($movie['genre']))
                                                <span><span class="font-semibold"><i class="fa fa-tag mr-1"></i>Genre:</span> {{ e($movie['genre']) }}</span>
                                            @endif
                                            @if(!empty($movie['director']))
                                                <span><span class="font-semibold"><i class="fa fa-video mr-1"></i>Director:</span> {{ e($movie['director']) }}</span>
                                            @endif
                                        </div>
                                        @if(!empty($movie['actors']))
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <span class="font-semibold"><i class="fa fa-users mr-1"></i>Starring:</span> {{ e($movie['actors']) }}
                                            </div>
                                        @endif
                                        <div class="mt-2">
                                            <a class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200" target="_blank"
                                               href="{{ $site['dereferrer_link'] }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}" title="View on IMDB">
                                                <i class="fa fa-external-link mr-1"></i>IMDB
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i class="fa fa-folder-open mr-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs whitespace-nowrap" title="Added on {{ $movie['created_at'] ?? '' }}">
                                        <i class="fa fa-calendar mr-1"></i>
                                        {{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a class="btn btn-warning btn-sm" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit Categories">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a class="btn btn-danger btn-sm" href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}" title="Remove from My Movies">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden space-y-4">
                    @foreach($movies as $movie)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex gap-3">
                                <img class="rounded-lg shadow-sm w-20 h-auto shrink-0"
                                     src="{{ url('/covers/movies/' . (($movie['cover'] ?? 0) == 1 ? $movie['imdbid'] . '-cover.jpg' : 'no-cover.jpg')) }}"
                                     alt="{{ e($movie['title'] ?? '') }}"/>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-blue-600 dark:hover:text-blue-400 transition text-sm">
                                        {{ e($movie['title'] ?? '') }} ({{ $movie['year'] ?? '' }})
                                    </a>
                                    @if(!empty($movie['tagline']))
                                        <div class="italic text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ e($movie['tagline']) }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i class="fa fa-folder-open mr-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <i class="fa fa-calendar mr-1"></i>{{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @if(!empty($movie['plot']))
                                <p class="text-gray-600 dark:text-gray-300 text-xs mt-3">{{ e($movie['plot']) }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <a class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-yellow-100 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200" target="_blank"
                                   href="{{ $site['dereferrer_link'] }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}">
                                    <i class="fa fa-external-link mr-1"></i>IMDB
                                </a>
                                <div class="flex gap-2">
                                    <a class="btn btn-warning btn-sm" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a class="btn btn-danger btn-sm" href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}" title="Remove">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <div class="flex items-center">
                        <i class="fa fa-info-circle text-blue-600 dark:text-blue-400 mr-3"></i>
                        <span class="text-blue-800 dark:text-blue-200 text-sm">No movies bookmarked yet. Add movies from movie pages.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

