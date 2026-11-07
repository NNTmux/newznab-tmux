<div class="max-w-4xl mx-auto">
    <x-page-header :title="ucfirst($type ?? 'add') . ' movie'" eyebrow="Movie watchlist" description="Choose how this movie should be organized in your saved collection." icon="fas fa-film">
        <x-slot:actions>
            <a href="{{ url('/mymovies') }}" class="workspace-hero__action"><i class="fas fa-arrow-left" aria-hidden="true"></i>My movies</a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-6">
            <div class="mb-6">
                <div class="flex items-center gap-4 mb-4">
                    <img width="96" height="144" class="rounded-lg shadow-md w-24 h-auto aspect-[2/3] object-cover"
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

                <div class="bg-info-50 dark:bg-info-950/40 border-l-4 border-info-500 rounded-lg p-4">
                    <div class="flex items-start">
                        <i aria-hidden="true" class="fa-solid fa-info-circle text-info-600 dark:text-info-400 mt-0.5 mr-3"></i>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Adding movies to your watchlist will notify you through your
                            <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}"
                               class="font-semibold text-info-600 dark:text-info-400 hover:text-info-800 dark:hover:text-info-300 underline inline-flex items-center">
                                <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>RSS Feed
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
                            <label class="inline-flex items-center px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-all duration-200 has-checked:bg-primary-50 has-checked:border-primary-500 has-checked:text-link">
                                <input aria-label="Select item" type="checkbox"
                                       id="category_{{ $cat_id }}"
                                       name="category[]"
                                       value="{{ $cat_id }}"
                                       class="mr-2 rounded text-link dark:text-primary-400 focus:ring-primary-500"
                                       @if(in_array($cat_id, $cat_selected ?? [])) checked @endif>
                                <span class="text-sm font-medium">{{ $cat_names[$cat_id] ?? '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button class="px-6 py-3 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover dark:hover:bg-primary-800 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium"
                            type="submit" name="{{ $type ?? 'add' }}">
                        <i aria-hidden="true" class="fa {{ ($type ?? 'add') == 'add' ? 'fa-plus' : 'fa-edit' }} mr-2"></i>{{ ucfirst($type ?? 'add') }} Movie
                    </button>
                    <a href="{{ url('/mymovies') }}"
                       class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-900 hover:border-gray-400 shadow-md hover:shadow-lg transition-all duration-200 inline-flex items-center font-medium">
                        <i aria-hidden="true" class="fa-solid fa-arrow-left mr-2"></i>Back to My Movies
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="max-w-4xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="p-6">
            <div class="mb-6 p-4 bg-info-50 dark:bg-info-900/30 border border-info-200 dark:border-info-700 rounded-lg">
                <div class="flex items-start">
                    <i aria-hidden="true" class="fa-solid fa-info-circle text-info-600 dark:text-info-400 mt-0.5 mr-3"></i>
                    <p class="text-sm text-info-800 dark:text-info-200">
                        Using 'My Movies' you can search for movies and add them to a wishlist. If the movie becomes available it will be added to an
                        <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="font-semibold underline hover:text-info-600 dark:hover:text-info-300">
                            <i aria-hidden="true" class="fa-solid fa-rss mr-1"></i>RSS Feed
                        </a>
                        you can use to automatically download. You can
                        <a href="{{ route('mymovies') }}" class="font-semibold underline hover:text-info-600 dark:hover:text-info-300">
                            <i aria-hidden="true" class="fa-solid fa-list mr-1"></i>Manage Your Movie List
                        </a>
                        to remove old items.
                    </p>
                </div>
            </div>

            <div class="flex justify-between items-center mb-4">
                <a href="{{ url("/rss/mymovies?dl=1&i={$userdata->id}&api_token={$userdata->api_token}") }}" class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 border border-line bg-surface text-fg shadow-raised hover:bg-surface-hover">
                    <i aria-hidden="true" class="fa-solid fa-rss mr-2"></i>RSS Feed
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
                                        <img width="120" height="180" class="rounded-lg shadow-sm max-w-[120px]"
                                             src="{{ ($movie['cover'] ?? 0) == 1 ? getImageAssetUrl('movies', $movie['imdbid'] . '-cover', url('/covers/movies/no-cover.jpg')) : url('/covers/movies/no-cover.jpg') }}"
                                             loading="lazy"
                                             alt="{{ e($movie['title'] ?? '') }}"/>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="mb-2">
                                            <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-link dark:hover:text-primary-400 transition" title="View movie details">
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
                                                <span><span class="font-semibold"><i aria-hidden="true" class="fa-solid fa-tag mr-1"></i>Genre:</span> {{ e($movie['genre']) }}</span>
                                            @endif
                                            @if(!empty($movie['director']))
                                                <span><span class="font-semibold"><i aria-hidden="true" class="fa-solid fa-video mr-1"></i>Director:</span> {{ e($movie['director']) }}</span>
                                            @endif
                                        </div>
                                        @if(!empty($movie['actors']))
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <span class="font-semibold"><i aria-hidden="true" class="fa-solid fa-users mr-1"></i>Starring:</span> {{ e($movie['actors']) }}
                                            </div>
                                        @endif
                                        <div class="mt-2">
                                            <a class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-warning-100 dark:bg-warning-900/50 text-warning-800 dark:text-warning-200" target="_blank"
                                               href="{{ $site->dereferrerLink }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}" title="View on IMDB">
                                                <i aria-hidden="true" class="fa-solid fa-external-link mr-1"></i>IMDB
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i aria-hidden="true" class="fa-solid fa-folder-open mr-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs whitespace-nowrap" title="Added on {{ $movie['created_at'] ?? '' }}">
                                        <i aria-hidden="true" class="fa-solid fa-calendar mr-1"></i>
                                        {{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a aria-label="Edit Categories" class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 bg-warning-400 text-warning-950 hover:bg-warning-500" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit Categories">
                                                <i aria-hidden="true" class="fa-solid fa-edit"></i>
                                            </a>
                                            <form method="POST" action="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}">
                                                @csrf
                                                <button aria-label="Remove from My Movies" type="submit" class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 bg-danger-600 text-white hover:bg-danger-700" title="Remove from My Movies">
                                                    <i aria-hidden="true" class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
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
                                <img width="80" height="120" class="rounded-lg shadow-sm w-20 h-auto shrink-0 aspect-[2/3] object-cover"
                                     src="{{ ($movie['cover'] ?? 0) == 1 ? getImageAssetUrl('movies', $movie['imdbid'] . '-cover', url('/covers/movies/no-cover.jpg')) : url('/covers/movies/no-cover.jpg') }}"
                                     loading="lazy"
                                     alt="{{ e($movie['title'] ?? '') }}"/>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ url("/Movies?imdb={$movie['imdbid']}") }}" class="text-gray-900 dark:text-gray-100 font-semibold hover:text-link dark:hover:text-primary-400 transition text-sm">
                                        {{ e($movie['title'] ?? '') }} ({{ $movie['year'] ?? '' }})
                                    </a>
                                    @if(!empty($movie['tagline']))
                                        <div class="italic text-gray-500 dark:text-gray-400 text-xs mt-0.5">{{ e($movie['tagline']) }}</div>
                                    @endif
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i aria-hidden="true" class="fa-solid fa-folder-open mr-1"></i>{{ !empty($movie['categoryNames']) ? e($movie['categoryNames']) : 'All' }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <i aria-hidden="true" class="fa-solid fa-calendar mr-1"></i>{{ isset($movie['created_at']) ? date('M d, Y', strtotime($movie['created_at'])) : '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @if(!empty($movie['plot']))
                                <p class="text-gray-600 dark:text-gray-300 text-xs mt-3">{{ e($movie['plot']) }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <a class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-warning-100 dark:bg-warning-900/50 text-warning-800 dark:text-warning-200" target="_blank"
                                   href="{{ $site->dereferrerLink }}http://www.imdb.com/title/tt{{ $movie['imdbid'] }}">
                                    <i aria-hidden="true" class="fa-solid fa-external-link mr-1"></i>IMDB
                                </a>
                                <div class="flex gap-2">
                                    <a aria-label="Edit" class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 bg-warning-400 text-warning-950 hover:bg-warning-500" href="{{ url("/mymovies?id=edit&imdb={$movie['imdbid']}") }}" title="Edit">
                                        <i aria-hidden="true" class="fa-solid fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ url("/mymovies?id=delete&imdb={$movie['imdbid']}") }}">
                                        @csrf
                                        <button aria-label="Remove" type="submit" class="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 bg-danger-600 text-white hover:bg-danger-700" title="Remove">
                                            <i aria-hidden="true" class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 bg-info-50 dark:bg-info-900/30 border border-info-200 dark:border-info-700 rounded-lg">
                    <div class="flex items-center">
                        <i aria-hidden="true" class="fa-solid fa-info-circle text-info-600 dark:text-info-400 mr-3"></i>
                        <span class="text-info-800 dark:text-info-200 text-sm">No movies bookmarked yet. Add movies from movie pages.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
