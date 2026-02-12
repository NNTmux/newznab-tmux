@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm mb-6 dark:bg-gray-800">
    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg dark:bg-gray-700 dark:border-gray-600">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-code mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>{{ $title }}
        </h3>
    </div>
    <div class="p-6">
        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6 dark:text-gray-300">
            Here lives the documentation for the API v2 for accessing NZB and index data. API functions can be called by providing an API token.
        </p>

        @auth
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6 border border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                    <i class="fa fa-key mr-2 text-gray-600 dark:text-gray-400"></i>Your API Credentials
                </h4>
                <div class="flex rounded-md shadow-sm" x-data="copyToClipboard()">
                    <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white" value="api_token={{ auth()->user()->api_token }}" readonly id="apikeyInput">
                    <button class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" type="button" x-on:click="copy('apikeyInput')" title="Copy to clipboard" x-bind:class="copied ? 'text-green-600' : ''">
                        <i class="fa" x-bind:class="copied ? 'fa-check' : 'fa-copy'"></i>
                    </button>
                </div>
            </div>
        @endauth

        <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-plug mr-2 text-gray-600 dark:text-gray-400"></i>Available Functions
        </h4>

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
                            <a href="{{ url('/api/v2/capabilities') }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">capabilities</code>
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
                                <a href="{{ url('/api/v2/search?id=linux&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">search?id=linux</code>
                                </a>
                                <a href="{{ url('/api/v2/search?cat=' . $catClass::GAME_ROOT . ',' . $catClass::MOVIE_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">search?cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">ID OPTIONS</span>
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
                                <a href="{{ url('/api/v2/tv?id=law%20and%20order&season=7&ep=12&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">tv?id=law and order&amp;season=7&amp;ep=12</code>
                                </a>
                                <a href="{{ url('/api/v2/tv?rid=2204&cat=' . $catClass::GAME_ROOT . ',' . $catClass::MOVIE_ROOT . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">tv?rid=2204&amp;cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
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
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">ID OPTIONS</span>
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
                                <a href="{{ url('/api/v2/movies?imdbid=1418646&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">movies?imdbid=1418646</code>
                                </a>
                                <a href="{{ url('/api/v2/movies?imdbid=1418646&cat=' . $catClass::MOVIE_SD . ',' . $catClass::MOVIE_HD . '&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">movies?imdbid=1418646&amp;cat={{ $catClass::MOVIE_SD }},{{ $catClass::MOVIE_HD }}</code>
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
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-gray-100 dark:text-white">Details</strong></td>
                        <td class="px-6 py-4"><span class="text-gray-700 dark:text-gray-300">Returns detailed information about an NZB.</span></td>
                        <td class="px-6 py-4">
                            @auth
                            <a href="{{ url('/api/v2/details?id=9ca52909ba9b9e5e6758d815fef4ecda&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">details?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
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
                            <a href="{{ url('/api/v2/getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">getnzb?id=9ca52909ba9b9e5e6758d815fef4ecda</code>
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

        <h4 class="text-lg font-semibold mt-6 mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-file-code mr-2 text-gray-600 dark:text-gray-400"></i>Output Format
        </h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm mb-4 dark:bg-gray-700 dark:border-gray-600">
            <div class="p-6">
                <div class="flex items-center">
                    <i class="fa fa-brackets-curly mr-3 text-blue-600 dark:text-blue-400 text-3xl dark:text-blue-400"></i>
                    <div>
                        <h5 class="text-lg font-semibold mb-1 text-gray-900 dark:text-gray-100 dark:text-white">JSON Format</h5>
                        <p class="mb-0 text-gray-700 dark:text-gray-300">All information is returned in JSON format.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-300">
            <i class="fa fa-info-circle mr-2"></i>
            <strong>Note:</strong> When using these API endpoints in your applications, always send your API token with each request.
        </div>
    </div>
</div>
@endsection
