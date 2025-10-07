@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-6 dark:bg-gray-800">
    <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 rounded-t-lg dark:bg-gray-700 dark:border-gray-600">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-rss mr-2 text-orange-500 dark:text-orange-400"></i>{{ $title }}
        </h3>
    </div>
    <div class="p-6">
        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6 dark:text-gray-300">
            Here you can find RSS feeds for various categories and content types. These feeds provide either descriptions or
            direct NZB downloads based on your preferences.
        </p>

        @if($loggedin ?? false)
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6 mb-6 border border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                    <i class="fa fa-key mr-2 text-gray-600 dark:text-gray-400 dark:text-gray-400"></i>Your API Token
                </h4>
                <div class="flex rounded-md shadow-sm">
                    <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white" value="api_token={{ $userdata->api_token }}" readonly id="apiTokenInput">
                    <button class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500" type="button" id="copyApiToken" title="Copy to clipboard">
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>
        @endif

        <h4 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-cog mr-2 text-gray-600 dark:text-gray-400 dark:text-gray-400"></i>RSS Configuration Options
        </h4>
        <div class="overflow-x-auto mb-6">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="w-1/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Parameter</th>
                        <th scope="col" class="w-1/2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Description</th>
                        <th scope="col" class="w-3/10 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Example</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">api_token</code></td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300 dark:text-gray-300">Add this to your feed URL to allow NZB downloads without logging in</td>
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">&amp;api_token={{ $userdata->api_token ?? 'YOUR_TOKEN' }}</code></td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">del=1</code></td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300 dark:text-gray-300">Remove NZB from your cart after download</td>
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">&amp;del=1</code></td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">dl=1</code></td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300 dark:text-gray-300">Change the default link to download an NZB</td>
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">&amp;dl=1</code></td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">num=50</code></td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300 dark:text-gray-300">Change the number of results returned (default: 25, max: 100)</td>
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">&amp;num=50</code></td>
                    </tr>
                    <tr class="hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-700">
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">airdate=20</code></td>
                        <td class="px-6 py-4 text-gray-700 dark:text-gray-300 dark:text-gray-300">Return TV shows only aired in the last x days (default: all)</td>
                        <td class="px-6 py-4"><code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded text-sm text-red-600 dark:bg-gray-700 dark:text-red-400">&amp;airdate=20</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-lg p-4 mb-4 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-300">
            <i class="fa fa-info-circle mr-2"></i>
            Most NZB clients which support NZB RSS feeds will appreciate the full URL, with download link and your user token.
            The feeds include additional attributes to help provide better filtering in your NZB client, such as size, group,
            and categorization.
        </div>

        <div class="bg-indigo-50 border border-indigo-200 text-indigo-800 rounded-lg p-4 mb-6 dark:bg-indigo-900 dark:border-indigo-700 dark:text-indigo-300">
            <i class="fa fa-lightbulb-o mr-2"></i>
            <strong>Pro Tip:</strong> If you want to chain multiple categories together or do more advanced searching, use the
            <a href="{{ url('/apihelp') }}" class="underline hover:text-indigo-900 dark:hover:text-indigo-200">API</a>, which returns its data in an RSS-compatible format.
        </div>

        <h4 class="text-lg font-semibold mt-6 mb-3 text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
            <i class="fa fa-list mr-2 text-gray-600 dark:text-gray-400 dark:text-gray-400"></i>Available Feeds
        </h4>

        <!-- General Feeds -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm mb-6 dark:bg-gray-800 dark:border-gray-600">
            <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                <h5 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                    <i class="fa fa-globe mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>General Feeds
                </h5>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-3">
                        <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                            <i class="fa fa-rss mr-2 text-orange-500 dark:text-orange-400"></i>Full Site Feed
                        </strong>
                        <a href="{{ url('/rss/full-feed?dl=1&api_token=' . ($userdata->api_token ?? '')) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                            <i class="fa fa-external-link mr-1"></i>Open Feed
                        </a>
                    </div>
                    <div class="flex rounded-md shadow-sm">
                        <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/full-feed?dl=1&api_token=' . ($userdata->api_token ?? '')) }}" readonly id="fullFeedUrl">
                        <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="fullFeedUrl">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-gray-500 text-xs mt-2 block dark:text-gray-400">You can define limit and num parameters, which will decide how many items to show and what offset to use (default values: limit 100 and offset 0).</small>
                </div>

                <div class="p-4">
                    <div class="flex justify-between items-center mb-3">
                        <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                            <i class="fa fa-shopping-basket mr-2 text-blue-500 dark:text-blue-400"></i>My Cart Feed
                        </strong>
                        <a href="{{ url('/rss/cart?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                            <i class="fa fa-external-link mr-1"></i>Open Feed
                        </a>
                    </div>
                    <div class="flex rounded-md shadow-sm">
                        <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/cart?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" readonly id="cartFeedUrl">
                        <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="cartFeedUrl">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex justify-between items-center mb-3">
                        <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                            <i class="fa fa-tv mr-2 text-green-600 dark:text-green-400"></i>My Shows Feed
                        </strong>
                        <a href="{{ url('/rss/myshows?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                            <i class="fa fa-external-link mr-1"></i>Open Feed
                        </a>
                    </div>
                    <div class="flex rounded-md shadow-sm">
                        <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/myshows?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" readonly id="myShowsFeedUrl">
                        <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="myShowsFeedUrl">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex justify-between items-center mb-3">
                        <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                            <i class="fa fa-film mr-2 text-red-600 dark:text-red-400"></i>My Movies Feed
                        </strong>
                        <a href="{{ url('/rss/mymovies?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                            <i class="fa fa-external-link mr-1"></i>Open Feed
                        </a>
                    </div>
                    <div class="flex rounded-md shadow-sm">
                        <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/mymovies?dl=1&api_token=' . ($userdata->api_token ?? '') . '&del=1') }}" readonly id="myMoviesFeedUrl">
                        <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="myMoviesFeedUrl">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Feeds -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm dark:bg-gray-800 dark:border-gray-600">
                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                    <h5 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                        <i class="fa fa-folder mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>Parent Categories
                    </h5>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($parentcategorylist ?? [] as $category)
                        <div class="p-4">
                            <div class="flex justify-between items-center mb-3">
                                <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                                    <i class="fa fa-folder-open mr-2 text-yellow-600 dark:text-yellow-400"></i>{{ $category['title'] }}
                                </strong>
                                <a href="{{ url('/rss/category?id=' . $category['id'] . '&dl=1&api_token=' . ($userdata->api_token ?? '')) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                                    <i class="fa fa-external-link mr-1"></i>Open Feed
                                </a>
                            </div>
                            <div class="flex rounded-md shadow-sm">
                                <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/category?id=' . $category['id'] . '&dl=1&api_token=' . ($userdata->api_token ?? '')) }}" readonly id="parentCat{{ $category['id'] }}Url">
                                <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="parentCat{{ $category['id'] }}Url">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                            <i class="fa fa-folder-open text-2xl mb-2"></i>
                            <p>No parent categories available</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm dark:bg-gray-800 dark:border-gray-600">
                <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-b border-gray-200 dark:border-gray-700 dark:bg-gray-700 dark:border-gray-600">
                    <h5 class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                        <i class="fa fa-folder-open mr-2 text-blue-600 dark:text-blue-400 dark:text-blue-400"></i>Sub Categories
                    </h5>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-[500px] overflow-y-auto">
                    @forelse($categorylist ?? [] as $category)
                        @if(!empty($category['title']))
                            <div class="p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <strong class="text-gray-900 dark:text-gray-100 dark:text-white flex items-center">
                                        <i class="fa fa-tag mr-2 text-blue-500 dark:text-blue-400"></i>{{ $category['title'] }}
                                    </strong>
                                    <a href="{{ url('/rss/category?id=' . $category['id'] . '&dl=1&api_token=' . ($userdata->api_token ?? '')) }}" class="inline-flex items-center px-3 py-1.5 border border-blue-300 rounded text-xs font-medium text-blue-700 bg-white dark:bg-gray-800 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-blue-400 dark:border-blue-600 dark:hover:bg-gray-600" target="_blank">
                                        <i class="fa fa-external-link mr-1"></i>Open Feed
                                    </a>
                                </div>
                                <div class="flex rounded-md shadow-sm">
                                    <input type="text" class="flex-1 rounded-l-md border-gray-300 dark:border-gray-600 font-mono text-xs focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="{{ url('/rss/category?id=' . $category['id'] . '&dl=1&api_token=' . ($userdata->api_token ?? '')) }}" readonly id="subCat{{ $category['id'] }}Url">
                                    <button class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500 copy-btn" type="button" data-copy-target="subCat{{ $category['id'] }}Url">
                                        <i class="fa fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                            <i class="fa fa-tags text-2xl mb-2"></i>
                            <p>No sub categories available</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Copy to clipboard functionality using modern Clipboard API
        document.querySelectorAll('.copy-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-copy-target');
                const input = document.getElementById(targetId);
                if (input) {
                    input.select();
                    input.setSelectionRange(0, 99999);
                    navigator.clipboard.writeText(input.value).then(() => {
                        // Visual feedback
                        const icon = this.querySelector('i');
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check');
                        this.classList.add('text-green-600');
                        setTimeout(() => {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-copy');
                            this.classList.remove('text-green-600');
                        }, 2000);
                    }).catch(() => {
                        // Fallback for older browsers
                        document.execCommand('copy');
                        const icon = this.querySelector('i');
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check');
                        this.classList.add('text-green-600');
                        setTimeout(() => {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-copy');
                            this.classList.remove('text-green-600');
                        }, 2000);
                    });
                }
            });
        });

        // API Token copy button
        const copyApiTokenBtn = document.getElementById('copyApiToken');
        if (copyApiTokenBtn) {
            copyApiTokenBtn.addEventListener('click', function() {
                const input = document.getElementById('apiTokenInput');
                if (input) {
                    input.select();
                    input.setSelectionRange(0, 99999);
                    navigator.clipboard.writeText(input.value).then(() => {
                        // Visual feedback
                        const icon = this.querySelector('i');
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check');
                        this.classList.add('text-green-600');
                        setTimeout(() => {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-copy');
                            this.classList.remove('text-green-600');
                        }, 2000);
                    }).catch(() => {
                        // Fallback for older browsers
                        document.execCommand('copy');
                        const icon = this.querySelector('i');
                        icon.classList.remove('fa-copy');
                        icon.classList.add('fa-check');
                        this.classList.add('text-green-600');
                        setTimeout(() => {
                            icon.classList.remove('fa-check');
                            icon.classList.add('fa-copy');
                            this.classList.remove('text-green-600');
                        }, 2000);
                    });
                }
            });
        }
    });
</script>
@endpush
@endsection

