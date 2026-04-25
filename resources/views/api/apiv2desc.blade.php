@extends('layouts.main')

@section('content')
<div class="surface-panel rounded-xl shadow-sm mb-6">
    <div class="surface-panel-alt px-6 py-4 border-b rounded-t-lg">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center">
            <i class="fa fa-code mr-2 text-primary-600 dark:text-primary-400"></i>{{ $title }}
        </h3>
    </div>
    <div class="p-6">
        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6 dark:text-gray-300">
            Here lives the documentation for the API v2 for accessing NZB and index data. API functions can be called by providing an API token.
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
            The <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">capabilities</code> endpoint is public. Other v2 endpoints return JSON errors with matching HTTP statuses:
            400 for missing <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">api_token</code>, 401 for invalid or unverified tokens,
            403 for suspended accounts, and 429 for request limits.
        </p>

        @auth
            <div class="surface-panel-alt rounded-lg p-6 mb-6 border">
                <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 flex items-center">
                    <i class="fa fa-key mr-2 text-gray-600 dark:text-gray-400"></i>Your API Credentials
                </h4>
                <div class="flex rounded-md shadow-sm" x-data="copyToClipboard()">
                    <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white" value="api_token={{ auth()->user()->api_token }}" readonly id="apikeyInput">
                    <button class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" type="button" @click="copy('apikeyInput')" title="Copy to clipboard" :class="copied ? 'text-green-600' : ''">
                        <i class="fa" :class="copied ? 'fa-check' : 'fa-copy'"></i>
                    </button>
                </div>
            </div>
        @endauth

        <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 flex items-center">
            <i class="fa fa-plug mr-2 text-gray-600 dark:text-gray-400"></i>Available Functions
        </h4>
        <p class="text-gray-700 dark:text-gray-300 mb-4 dark:text-gray-300">
            Beyond <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">search</code>, <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">tv</code>, and <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">movies</code>, JSON endpoints include <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">audio</code> (music), <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">books</code>, and <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">anime</code>. The <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:text-gray-700 dark:text-red-400">capabilities</code> response lists <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">audio-search</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">book-search</code>, and <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">anime-search</code>.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Function</th>
                        <th scope="col" class="w-1/2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Description</th>
                        <th scope="col" class="w-1/3 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Example</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Capabilities</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Reports the capabilities of the server. Includes information about the server name,
                                available search categories and version number of the nntmux being used.
                            </span>
                            <div class="text-gray-500 text-sm mt-1 dark:text-gray-400">
                                <i class="fa fa-info-circle mr-1"></i>No credentials required
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ url('/api/v2/capabilities') }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-primary-700 dark:text-primary-400">capabilities</code>
                            </a>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Search</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query. You can filter by site category by including
                                a comma separated list of categories.
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                @auth
                                <a href="{{ url('/api/v2/search?id=linux&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">search?id=linux</code>
                                </a>
                                <a href="{{ url('/api/v2/search?cat=' . $catClass::GAME_ROOT . ',' . $catClass::MOVIE_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">search?cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
                                </a>
                                @else
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>search?id=linux</code>
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>search?cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
                                </span>
                                @endauth
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">TV Search</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query, category, TVRageID, season or episode.
                            </span>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200">ID OPTIONS</span>
                                <div class="mt-1 ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">rid=25056</code> - TVRage<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tvdbid=153021</code> - TVDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">traktid=1393</code> - Trakt<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tvmazeid=73</code> - TVMaze<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">imdbid=1520211</code> - IMDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tmdbid=1402</code> - TMDB
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                @auth
                                <a href="{{ url('/api/v2/tv?id=law%20and%20order&season=7&ep=12&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">tv?id=law and order&amp;season=7&amp;ep=12</code>
                                </a>
                                <a href="{{ url('/api/v2/tv?rid=2204&cat=' . $catClass::GAME_ROOT . ',' . $catClass::MOVIE_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">tv?rid=2204&amp;cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
                                </a>
                                @else
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>tv?id=law and order&amp;season=7&amp;ep=12</code>
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>tv?rid=2204&amp;cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
                                </span>
                                @endauth
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Movies</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query, an ID (IMDB, TMDB, or Trakt) and optionally a category.
                            </span>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900/50 dark:text-primary-200">ID OPTIONS</span>
                                <div class="mt-1 ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">imdbid=1418646</code> - IMDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tmdbid=43418</code> - TMDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">traktid=29200</code> - Trakt
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                @auth
                                <a href="{{ url('/api/v2/movies?imdbid=1418646&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">movies?imdbid=1418646</code>
                                </a>
                                <a href="{{ url('/api/v2/movies?imdbid=1418646&cat=' . $catClass::MOVIE_SD . ',' . $catClass::MOVIE_HD . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">movies?imdbid=1418646&amp;cat={{ $catClass::MOVIE_SD }},{{ $catClass::MOVIE_HD }}</code>
                                </a>
                                @else
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>movies?imdbid=1418646</code>
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>movies?imdbid=1418646&amp;cat={{ $catClass::MOVIE_SD }},{{ $catClass::MOVIE_HD }}</code>
                                </span>
                                @endauth
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Audio (music)</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Music search: pass the text query as <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">id</code> (same pattern as <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">search</code>).
                                Optional: <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">cat</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">maxage</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">minsize</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">group</code>, offset/limit.
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @auth
                            <a href="{{ url('/api/v2/audio?id=' . rawurlencode('pink floyd') . '&cat=' . $catClass::MUSIC_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-primary-700 dark:text-primary-400">audio?id=…&amp;cat={{ $catClass::MUSIC_ROOT }}</code>
                            </a>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                <code>audio?id=&lt;artist or title&gt;&amp;cat={{ $catClass::MUSIC_ROOT }}</code>
                            </span>
                            @endauth
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Books</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Book search: query text in <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">id</code>. Optional <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">cat</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">maxage</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">minsize</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">group</code>.
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @auth
                            <a href="{{ url('/api/v2/books?id=' . rawurlencode('science fiction') . '&cat=' . $catClass::BOOKS_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-primary-700 dark:text-primary-400">books?id=…&amp;cat={{ $catClass::BOOKS_ROOT }}</code>
                            </a>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                <code>books?id=&lt;author or title&gt;&amp;cat={{ $catClass::BOOKS_ROOT }}</code>
                            </span>
                            @endauth
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Anime</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Provide at least one of: <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">id</code> (title search), <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">anidbid</code>, or <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">anilistid</code>.
                                Optional: <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">cat</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">maxage</code>.
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                @auth
                                <a href="{{ url('/api/v2/anime?id=' . rawurlencode('attack on titan') . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">anime?id=attack on titan</code>
                                </a>
                                <a href="{{ url('/api/v2/anime?anilistid=21&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-primary-700 dark:text-primary-400">anime?anilistid=21</code>
                                </a>
                                @else
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>anime?id=&lt;title&gt;</code>
                                </span>
                                <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                    <code>anime?anidbid=&lt;id&gt;</code> or <code>anime?anilistid=&lt;id&gt;</code>
                                </span>
                                @endauth
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Details</strong></td>
                        <td class="px-6 py-4"><span class="text-gray-700 dark:text-gray-300">Returns detailed information about an NZB.</span></td>
                        <td class="px-6 py-4">
                            @auth
                            <a href="{{ url('/api/v2/details?id=9ca52909ba9b9e5e6758d815fef4ecda&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-primary-700 dark:text-primary-400">details?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                            </a>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                <code>details?id=&lt;guid&gt;</code>
                            </span>
                            @endauth
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Get NZB</strong></td>
                        <td class="px-6 py-4"><span class="text-gray-700 dark:text-gray-300">Downloads the NZB file associated with an ID.</span></td>
                        <td class="px-6 py-4">
                            @auth
                            <a href="{{ url('/api/v2/getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-primary-700 dark:text-primary-400">getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                            </a>
                            @else
                            <span class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700">
                                <code>getnzb?id=&lt;guid&gt;</code>
                            </span>
                            @endauth
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h4 class="text-lg font-semibold mt-6 mb-3 text-gray-900 dark:text-gray-100 flex items-center">
            <i class="fa fa-sort-amount-down mr-2 text-gray-600 dark:text-gray-400"></i>Sorting Results (v2)
        </h4>
        <p class="text-gray-700 dark:text-gray-300 mb-4 dark:text-gray-300">
            Search endpoints support <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">sort=field_direction</code>.
            Allowed fields: <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">cat</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">name</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">size</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">files</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">stats</code>, <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">posted</code>. Direction is <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">asc</code> or <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">desc</code>.
        </p>
        <div class="surface-panel-alt rounded-lg border shadow-sm mb-4">
            <div class="p-6">
                <h5 class="text-base font-semibold mb-2 text-gray-900 dark:text-gray-100">Sort request examples</h5>
                <div class="flex flex-col gap-2">
                    @auth
                        <a href="{{ url('/api/v2/search?id=ubuntu&sort=posted_desc&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                            <i class="fa fa-external-link-alt mr-1"></i>
                            <code class="text-primary-700 dark:text-primary-400">search?id=ubuntu&amp;sort=posted_desc</code>
                        </a>
                        <a href="{{ url('/api/v2/search?id=ubuntu&sort=name_asc&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                            <i class="fa fa-external-link-alt mr-1"></i>
                            <code class="text-primary-700 dark:text-primary-400">search?id=ubuntu&amp;sort=name_asc</code>
                        </a>
                        <a href="{{ url('/api/v2/movies?imdbid=1418646&sort=size_desc&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-primary-300 rounded text-xs font-medium text-primary-700 bg-white dark:bg-gray-800 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-primary-400 dark:border-primary-600 dark:hover:bg-gray-600">
                            <i class="fa fa-external-link-alt mr-1"></i>
                            <code class="text-primary-700 dark:text-primary-400">movies?imdbid=1418646&amp;sort=size_desc</code>
                        </a>
                    @else
                        <code class="block bg-gray-100 dark:bg-gray-800 p-2 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">search?id=ubuntu&amp;sort=posted_desc</code>
                        <code class="block bg-gray-100 dark:bg-gray-800 p-2 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">search?id=ubuntu&amp;sort=name_asc</code>
                        <code class="block bg-gray-100 dark:bg-gray-800 p-2 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">movies?imdbid=1418646&amp;sort=size_desc</code>
                    @endauth
                </div>
            </div>
        </div>
        <div class="surface-panel-alt rounded-lg border shadow-sm mb-4">
            <div class="p-6">
                <h5 class="text-base font-semibold mb-2 text-gray-900 dark:text-gray-100">JSON sort response snippet (<code class="text-xs">sort=size_desc</code>)</h5>
                <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs text-gray-800 dark:text-gray-200 overflow-x-auto"><code>{
  "Total": 2,
  "Results": [
    { "title": "Ubuntu ISO x64", "size": 734003200 },
    { "title": "Ubuntu ISO x86", "size": 367001600 }
  ]
}</code></pre>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 mb-0"><code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">Results</code> are largest-to-smallest when <code class="px-1 bg-gray-100 dark:bg-gray-800 rounded text-xs">sort=size_desc</code>.</p>
            </div>
        </div>

        <h4 class="text-lg font-semibold mt-6 mb-3 text-gray-900 dark:text-gray-100 flex items-center">
            <i class="fa fa-file-code mr-2 text-gray-600 dark:text-gray-400"></i>Output Format
        </h4>
        <div class="surface-panel-alt rounded-lg border shadow-sm mb-4">
            <div class="p-6">
                <div class="flex items-center">
                    <i class="fa fa-brackets-curly mr-3 text-primary-600 dark:text-primary-400 text-3xl"></i>
                    <div>
                        <h5 class="text-lg font-semibold mb-1 text-gray-900 dark:text-gray-100">JSON Format</h5>
                        <p class="mb-0 text-gray-700 dark:text-gray-300">All information is returned in JSON format.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="surface-panel-alt border rounded-lg p-4 text-gray-700 dark:text-gray-300">
            <i class="fa fa-info-circle mr-2 text-primary-500"></i>
            <strong>Note:</strong> When using these API endpoints in your applications, always send your API token with each request.
        </div>
    </div>
</div>
@endsection
