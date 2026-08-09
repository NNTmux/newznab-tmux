<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <!-- Header -->
        <div class="bg-linear-to-r from-primary-600 to-primary-700 px-6 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fa fa-film mr-2"></i>{{ ucfirst($type ?? 'add') }} Movie to Watchlist
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-primary-100">
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
                         src="{{ getImageAssetUrl('movies', $imdbid . '-cover', url('/covers/movies/no-cover.jpg')) }}"
                         data-fallback-src="{{ url('/covers/movies/no-cover.jpg') }}"
                         loading="lazy"
                         alt="{{ e($movie['title'] ?? '') }}" />

                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                            {{ ucfirst($type ?? 'add') }} "{{ e($movie['title'] ?? '') }}" to watchlist
                        </h4>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Select categories below to organize this movie in your collection.</p>
                    </div>
                </div>

                <div class="bg-primary-50 border-l-4 border-primary-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fa fa-info-circle text-primary-600 dark:text-primary-400 mt-0.5 mr-3"></i>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Adding movies to your watchlist will notify you through your
                            <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                               class="font-semibold text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 underline inline-flex items-center">
                                <i class="fa fa-rss mr-1"></i>RSS Feed
                            </a>
                            when they become available.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ url("mymovies?id=do{$type}") }}" id="mymovies" class="space-y-6">
                @csrf
                <input type="hidden" name="imdb" value="{{ $imdbid }}"/>
                @if(!empty($from))
                    <input type="hidden" name="from" value="{{ $from }}" />
                @endif

                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">Choose Categories:</label>
                    <div class="flex flex-wrap gap-3" id="category-container">
                        @foreach($cat_ids ?? [] as $index => $cat_id)
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:bg-gray-800 transition-all duration-200 has-checked:bg-primary-50 has-checked:border-primary-500 has-checked:text-primary-700">
                                <input type="checkbox"
                                       id="category_{{ $cat_id }}"
                                       name="category[]"
                                       value="{{ $cat_id }}"
                                       class="mr-2 rounded text-primary-600 dark:text-primary-400 focus:ring-primary-500"
                                       @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                                <span class="text-sm font-medium">{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <x-button size="lg" class="shadow-md hover:shadow-lg"
                              type="submit" name="{{ $type ?? 'add' }}"
                              icon="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }}">{{ ucfirst($type ?? 'add') }} Movie</x-button>
                    <x-button-link href="{{ url('/mymovies') }}"
                                   variant="secondary" size="lg" class="shadow-md hover:shadow-lg"
                                   icon="fa fa-arrow-left">Back to My Movies</x-button-link>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="bg-linear-to-r from-primary-600 to-primary-700 px-6 py-4">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fa fa-film mr-2"></i>My Movies
                </h3>
                <nav aria-label="breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm text-primary-100">
                        <li><a href="{{ url($site['home_link']) }}" class="hover:text-white transition">Home</a></li>
                        <li><i class="fas fa-chevron-right text-xs"></i></li>
                        <li class="text-white font-medium">My Movies</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="p-6">
            <div class="mb-6 p-4 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 rounded-lg">
                <div class="flex items-start">
                    <i class="fa fa-info-circle text-primary-600 dark:text-primary-400 mt-0.5 mr-3"></i>
                    <p class="text-sm text-primary-800 dark:text-primary-200">
                        Using 'My Movies' you can search for movies and add them to a wishlist. If the movie becomes available it will be added to an
                        <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="font-semibold underline hover:text-primary-600 dark:hover:text-primary-300">
                            <i class="fa fa-rss mr-1"></i>RSS Feed
                        </a>
                        you can use to automatically download. You can
                        <a href="{{ route('mymovies') }}" class="font-semibold underline hover:text-primary-600 dark:hover:text-primary-300">
                            <i class="fa fa-list mr-1"></i>Manage Your Movie List
                        </a>
                        to remove old items.
                    </p>
                </div>
            </div>

            <div class="flex justify-between items-center mb-4">
                <x-button-link href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" variant="secondary" size="sm" icon="fa fa-rss">RSS Feed</x-button-link>
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
                                             src="{{ ($movie['cover'] ?? 0) == 1 ? getImageAssetUrl('movies', $movie['imdbid'] . '-cover', url('/covers/movies/no-cover.jpg')) : url('/covers/movies/no-cover.jpg') }}"
                                             loading="lazy"
                                             alt="{{ e($movie['title'] ?? '') }}"/>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="mb-2">
                                            <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-primary-600 dark:hover:text-primary-400 transition" title="View movie details">
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
                                            <x-button-link variant="warning" size="sm" icon="fa fa-edit" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit Categories" aria-label="Edit Categories"></x-button-link>
                                            <x-button-link variant="danger" size="sm" icon="fa fa-trash" href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}" title="Remove from My Movies" aria-label="Remove from My Movies"></x-button-link>
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
                                     src="{{ ($movie['cover'] ?? 0) == 1 ? getImageAssetUrl('movies', $movie['imdbid'] . '-cover', url('/covers/movies/no-cover.jpg')) : url('/covers/movies/no-cover.jpg') }}"
                                     loading="lazy"
                                     alt="{{ e($movie['title'] ?? '') }}"/>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-primary-600 dark:hover:text-primary-400 transition text-sm">
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
                                    <x-button-link variant="warning" size="sm" icon="fa fa-edit" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit" aria-label="Edit"></x-button-link>
                                    <x-button-link variant="danger" size="sm" icon="fa fa-trash" href="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}" title="Remove" aria-label="Remove"></x-button-link>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 rounded-lg">
                    <div class="flex items-center">
                        <i class="fa fa-info-circle text-primary-600 dark:text-primary-400 mr-3"></i>
                        <span class="text-primary-800 dark:text-primary-200 text-sm">No movies bookmarked yet. Add movies from movie pages.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
