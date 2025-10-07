@extends('layouts.main')
@section('content')
<div class="bg-white rounded-lg shadow-sm mb-6 dark:bg-gray-800">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 rounded-t-lg dark:bg-gray-700 dark:border-gray-600">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
            <i class="fa fa-code mr-2 text-blue-600 dark:text-blue-400"></i>{{ $title }}
        </h3>
    </div>
    <div class="p-6">
        <p class="text-lg text-gray-700 mb-6 dark:text-gray-300">
            Here lives the documentation for the API for accessing NZB and index data. API functions can be called by either
            logged in users, or by providing an API key.
        </p>
        @if($loggedin ?? false)
            <div class="bg-gray-50 rounded-lg p-6 mb-6 border border-gray-200 dark:bg-gray-700 dark:border-gray-600">
                <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white flex items-center">
                    <i class="fa fa-key mr-2 text-gray-600 dark:text-gray-400"></i>Your API Credentials
                </h4>
                <div class="flex rounded-md shadow-sm">
                    <input type="text" class="flex-1 rounded-l-md border-gray-300 font-mono text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white" value="apikey={{ $userdata->api_token }}" readonly id="apikeyInput">
                    <button class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" type="button" id="copyApiKey" title="Copy to clipboard">
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>
        @endif
        <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white flex items-center">
            <i class="fa fa-plug mr-2 text-gray-600 dark:text-gray-400"></i>Available Functions
        </h4>
        <p class="text-gray-700 mb-4 dark:text-gray-300">Use the parameter <code class="px-2 py-1 bg-gray-100 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">?t=</code> to specify the function being called.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="w-1/6 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Function</th>
                        <th scope="col" class="w-1/2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Description</th>
                        <th scope="col" class="w-1/3 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Example</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Capabilities</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Reports the capabilities of the server. Includes information about the server name,
                                available search categories and version number of the newznab protocol being used.
                            </span>
                            <div class="text-gray-500 text-sm mt-1 dark:text-gray-400">
                                <i class="fa fa-info-circle mr-1"></i>No credentials required
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ url('/api/v1/api?t=caps') }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=caps</code>
                            </a>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Search</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query. You can filter by site category by including
                                a comma separated list of categories.
                            </span>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">OPTIONS</span>
                                <div class="mt-1 ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    <code class="px-2 py-1 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">extended=1</code> - Return extended information in results
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-2">
                                @if($loggedin ?? false)
                                <a href="{{ url('/api/v1/api?t=search&q=linux&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">?t=search&amp;q=linux</code>
                                </a>
                                <a href="{{ url('/api/v1/api?t=search&cat=' . $catClass::GAME_ROOT . ',' . $catClass::MOVIE_ROOT . '&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                    <i class="fa fa-external-link-alt mr-1"></i>
                                    <code class="text-blue-700 dark:text-blue-400">?t=search&amp;cat={{ $catClass::GAME_ROOT }},{{ $catClass::MOVIE_ROOT }}</code>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">TV Search</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query, category, or TV ID. Filter by season, episode, or various database IDs.
                            </span>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">ID OPTIONS</span>
                                <div class="mt-1 ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">rid=25056</code> - TVRage<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tvdbid=153021</code> - TVDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">traktid=1393</code> - Trakt<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tvmazeid=73</code> - TVMaze<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">imdbid=1520211</code> - IMDB<br>
                                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">tmdbid=1402</code> - TMDB
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($loggedin ?? false)
                            <a href="{{ url('/api/v1/api?t=tvsearch&q=law%20and%20order&season=7&ep=12&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=tvsearch&amp;q=law and order&amp;season=7&amp;ep=12</code>
                            </a>
                            @endif
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Movies</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns a list of NZBs matching a query, an IMDB ID and optionally a category.
                            </span>
                            <div class="mt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">OPTIONS</span>
                                <div class="mt-1 ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    <code class="px-2 py-1 bg-gray-100 rounded text-xs text-red-600 dark:bg-gray-700 dark:text-red-400">extended=1</code> - Return extended information in results
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($loggedin ?? false)
                            <a href="{{ url('/api/v1/api?t=movie&imdbid=1418646&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=movie&amp;imdbid=1418646</code>
                            </a>
                            @endif
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Details</strong></td>
                        <td class="px-6 py-4"><span class="text-gray-700 dark:text-gray-300">Returns detailed information about an NZB.</span></td>
                        <td class="px-6 py-4">
                            @if($loggedin ?? false)
                            <a href="{{ url('/api/v1/api?t=details&id=9ca52909ba9b9e5e6758d815fef4ecda&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=details&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                            </a>
                            @endif
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Info</strong></td>
                        <td class="px-6 py-4">
                            <span class="text-gray-700 dark:text-gray-300">
                                Returns NFO contents for an NZB. Retrieve the NFO as file by specifying o=file in the request URI.
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($loggedin ?? false)
                            <a href="{{ url('/api/v1/api?t=info&id=9ca52909ba9b9e5e6758d815fef4ecda&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=info&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                            </a>
                            @endif
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><strong class="text-gray-900 dark:text-white">Get</strong></td>
                        <td class="px-6 py-4"><span class="text-gray-700 dark:text-gray-300">Downloads the NZB file associated with an ID.</span></td>
                        <td class="px-6 py-4">
                            @if($loggedin ?? false)
                            <a href="{{ url('/api/v1/api?t=get&id=9ca52909ba9b9e5e6758d815fef4ecda&apikey=' . $userdata->api_token) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600">
                                <i class="fa fa-external-link-alt mr-1"></i>
                                <code class="text-blue-700 dark:text-blue-400">?t=get&amp;id=9ca52909ba9b9e5e6758d815fef4ecda</code>
                            </a>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <h4 class="text-lg font-semibold mt-6 mb-3 text-gray-900 dark:text-white flex items-center">
            <i class="fa fa-file-code mr-2 text-gray-600 dark:text-gray-400"></i>Output Format
        </h4>
        <p class="text-gray-700 mb-4 dark:text-gray-300">Select your preferred output format (not applicable to functions which return an NZB/NFO file).</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm h-full dark:bg-gray-800 dark:border-gray-600">
                <div class="p-6">
                    <h5 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white flex items-center">
                        <i class="fa fa-code mr-2 text-blue-600 dark:text-blue-400"></i>XML (default)
                    </h5>
                    <p class="text-gray-700 mb-3 dark:text-gray-300">Returns the data in an XML document.</p>
                    <code class="block bg-gray-100 p-2 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">?t=search&amp;q=linux&amp;o=xml</code>
                </div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm h-full dark:bg-gray-800 dark:border-gray-600">
                <div class="p-6">
                    <h5 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white flex items-center">
                        <i class="fa fa-brackets-curly mr-2 text-blue-600 dark:text-blue-400"></i>JSON
                    </h5>
                    <p class="text-gray-700 mb-3 dark:text-gray-300">Returns the data in a JSON object.</p>
                    <code class="block bg-gray-100 p-2 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">?t=search&amp;q=linux&amp;o=json</code>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyBtn = document.getElementById('copyApiKey');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const apiKeyInput = document.getElementById('apikeyInput');
            apiKeyInput.select();
            apiKeyInput.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(apiKeyInput.value);
            const originalText = copyBtn.innerHTML;
            copyBtn.innerHTML = '<i class="fa fa-check"></i>';
            copyBtn.classList.add('text-green-600');
            setTimeout(function() {
                copyBtn.innerHTML = originalText;
                copyBtn.classList.remove('text-green-600');
            }, 2000);
        });
    }
});
</script>
@endsection
