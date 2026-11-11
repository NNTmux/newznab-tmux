<x-page-header title="Browse groups" eyebrow="Explore Usenet" description="Search indexed groups and jump directly into their available releases." icon="fas fa-layer-group">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-list" aria-hidden="true"></i>{{ isset($results) ? number_format($results->total()) : 0 }} groups</span>
        @if(!empty($search))
            <span class="workspace-hero__stat"><i class="fas fa-filter" aria-hidden="true"></i>Filtered by {{ $search }}</span>
        @endif
    </x-slot:stats>
</x-page-header>


<!-- Search Filter -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
    <div class="p-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <form method="get" action="{{ url('/browsegroup') }}">
            <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i aria-hidden="true" class="fa-solid fa-search text-gray-400"></i>
                        </div>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="Search group names..."
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
                        />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-control px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 bg-accent text-on-accent shadow-raised hover:bg-accent-hover">
                        <i aria-hidden="true" class="fa-solid fa-search"></i>Search
                    </button>
                    @if(!empty($search))
                        <a href="{{ url('/browsegroup') }}" class="inline-flex min-h-11 items-center gap-2 rounded-control px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 border border-line bg-surface text-fg shadow-raised hover:bg-surface-hover">
                            <i aria-hidden="true" class="fa-solid fa-times"></i>Clear
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
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Browse Groups</h2>
            <div class="flex items-center">
                {{ $results->onEachSide(3)->links() }}
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Description
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
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
                                    class="font-semibold text-link dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 transition-colors"
                                >
                                    {{ str_replace('alt.binaries', 'a.b', $result->name) }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                {{ $result->description ?? '' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-600 dark:text-gray-400">
                                <i aria-hidden="true" class="fa-regular fa-clock text-gray-400 mr-2"></i>
                                <span title="{{ $result->last_updated ?? '' }}">
                                    {{ isset($result->last_updated) ? userDateDiffForHumans($result->last_updated) : 'N/A' }}
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
    <div class="bg-info-50 dark:bg-info-950/40 border-l-4 border-info-400 p-4 rounded">
        <div class="flex items-start">
            <i aria-hidden="true" class="fa-solid fa-info-circle text-info-400 text-xl mr-3 mt-0.5"></i>
            <div class="text-info-800 dark:text-info-200">
                @if(!empty($search))
                    No groups found matching "<strong>{{ $search }}</strong>".
                    <a href="{{ url('/browsegroup') }}" class="font-medium underline hover:text-info-900">Show all groups</a>
                @else
                    No groups found.
                @endif
            </div>
        </div>
    </div>
@endif
