<!-- Breadcrumb -->
<div class="mb-6">
    <nav aria-label="breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ url($site['home_link']) }}" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:text-blue-400 transition-colors">
                    Home
                </a>
            </li>
            <li class="text-gray-400">/</li>
            <li>
                <a href="{{ url('/browse') }}" class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:text-blue-400 transition-colors">
                    Browse
                </a>
            </li>
            <li class="text-gray-400">/</li>
            <li class="text-gray-900 dark:text-gray-100 font-medium">Groups</li>
        </ol>
    </nav>
</div>

{!! $site['adbrowse'] ?? '' !!}

<!-- Search Filter -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
    <div class="p-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <form method="get" action="{{ url('/browsegroup') }}">
            <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-search text-gray-400"></i>
                        </div>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search group names..."
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i>Search
                    </button>
                    @if(!empty($search))
                        <a href="{{ url('/browsegroup') }}" class="btn bg-gray-500 text-white border-gray-500 hover:bg-gray-600">
                            <i class="fa fa-times"></i>Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

@if(isset($results) && count($results) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:justify-between md:items-center gap-3">
            <h5 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Browse Groups</h5>
            <div class="flex items-center">
                {{ $results->onEachSide(3)->links() }}
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Description
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Last Updated
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($results as $result)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a
                                    href="{{ url('/browse/group?g=' . $result->name) }}"
                                    title="Browse releases from {{ str_replace('alt.binaries', 'a.b', $result->name) }}"
                                    class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors"
                                >
                                    {{ str_replace('alt.binaries', 'a.b', $result->name) }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                {{ $result->description ?? '' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                <i class="fa fa-clock-o text-gray-400 mr-2"></i>
                                <span title="{{ $result->last_updated ?? '' }}">
                                    {{ isset($result->last_updated) ? \Carbon\Carbon::parse($result->last_updated)->diffForHumans() : 'N/A' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-medium">Found {{ $results->total() }} groups</span>
                    @if(!empty($search))
                        <span class="ml-2">for "<strong class="text-gray-900 dark:text-gray-100">{{ $search }}</strong>"</span>
                    @endif
                </div>
                <div>
                    {{ $results->onEachSide(3)->links() }}
                </div>
            </div>
        </div>
    </div>
@else
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
        <div class="flex items-start">
            <i class="fa fa-info-circle text-blue-400 text-xl mr-3 mt-0.5"></i>
            <div class="text-blue-800">
                @if(!empty($search))
                    No groups found matching "<strong>{{ $search }}</strong>".
                    <a href="{{ url('/browsegroup') }}" class="font-medium underline hover:text-blue-900">Show all groups</a>
                @else
                    No groups found.
                @endif
            </div>
        </div>
    </div>
@endif

