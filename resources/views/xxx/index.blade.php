@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/XXX') }}" class="hover:text-blue-600">XXX</a></li>
                @if(!empty($categorytitle) && $categorytitle !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500">{{ $categorytitle }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/XXX/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Actors Filter -->
                    <div>
                        <label for="actors" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Actors</label>
                        <input type="text"
                               id="actors"
                               name="actors"
                               value="{{ $actors ?? '' }}"
                               placeholder="Search by actors"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Director Filter -->
                    <div>
                        <label for="director" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Director</label>
                        <input type="text"
                               id="director"
                               name="director"
                               value="{{ $director ?? '' }}"
                               placeholder="Search by director"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Genre Filter -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                        <select id="genre"
                                name="genre"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Genres</option>
                            @foreach($genres ?? [] as $g)
                                <option value="{{ $g }}" {{ ($genre ?? '') == $g ? 'selected' : '' }}>
                                    {{ $g }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-md hover:bg-blue-700">
                        <i class="fa fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/XXX/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        <i class="fa fa-film mr-2 text-blue-600"></i>
                        {{ $catname ?? 'All' }} XXX
                    </h2>
                    <x-view-toggle
                        current-view="covers"
                        covgroup="xxx"
                        :category="$categorytitle ?? 'All'"
                        parentcat="XXX"
                        :shows="false"
                    />
                </div>
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $results->total() }} results found
                </span>
            </div>

            <!-- XXX List Table -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider hidden md:table-cell">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider hidden lg:table-cell">Posted</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider hidden xl:table-cell">Size</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 dark:text-gray-300 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($results as $result)
                            @php
                                // Extract first values from comma-separated grouped fields
                                $guid = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid)[0] : null;
                                $searchname = isset($result->grp_release_name) ? explode('#', $result->grp_release_name)[0] : ($result->title ?? '');
                                $postdate = isset($result->grp_release_postdate) ? explode(',', $result->grp_release_postdate)[0] : null;
                                $size = isset($result->grp_release_size) ? explode(',', $result->grp_release_size)[0] : 0;
                                $failedCounts = isset($result->grp_release_failed) ? array_filter(explode(',', $result->grp_release_failed)) : [];
                                $totalFailed = array_sum($failedCounts);
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-start">
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ url('/details/' . $guid) }}"
                                               class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium break-words">
                                                {{ $searchname }}
                                            </a>
                                            @if($totalFailed > 0)
                                                <span class="ml-2 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded"
                                                      title="{{ $totalFailed }} user(s) reported download failure">
                                                    <i class="fa fa-exclamation-triangle mr-1"></i>Failed
                                                </span>
                                            @endif
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                @if(!empty($result->genre))
                                                    <span class="mr-3">
                                                        <i class="fa fa-tag mr-1"></i>
                                                        {!! makeFieldLinks((array) $result, 'genre', 'xxx') !!}
                                                    </span>
                                                @endif
                                                @if(!empty($result->actors))
                                                    <span class="mr-3">
                                                        <i class="fa fa-users mr-1"></i>
                                                        {!! makeFieldLinks((array) $result, 'actors', 'xxx') !!}
                                                    </span>
                                                @endif
                                                @if(!empty($result->director))
                                                    <span>
                                                        <i class="fa fa-user-circle mr-1"></i>
                                                        {!! makeFieldLinks((array) $result, 'director', 'xxx') !!}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hidden md:table-cell">
                                    @php
                                        $categoryName = isset($result->grp_release_catname) ? explode(',', $result->grp_release_catname)[0] : 'XXX';
                                    @endphp
                                    {{ $categoryName }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hidden lg:table-cell whitespace-nowrap">
                                    @if($postdate)
                                        {{ date('M d, Y', strtotime($postdate)) }}
                                        <div class="text-xs text-gray-500">{{ date('H:i', strtotime($postdate)) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 hidden xl:table-cell whitespace-nowrap">
                                    {{ number_format($size / 1073741824, 2) }} GB
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="{{ url('/getnzb?id=' . $guid) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-green-600 dark:bg-green-700 text-white text-xs rounded hover:bg-green-700 dark:hover:bg-green-800"
                                           title="Download NZB">
                                            <i class="fa fa-download mr-1"></i>
                                            <span class="hidden sm:inline">Download</span>
                                        </a>
                                        <a href="{{ url('/details/' . $guid) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-blue-600 dark:bg-blue-700 text-white text-xs rounded hover:bg-blue-700 dark:hover:bg-blue-800"
                                           title="View Details">
                                            <i class="fa fa-info-circle mr-1"></i>
                                            <span class="hidden sm:inline">Details</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex justify-center mt-6">
                {{ $results->links() }}
            </div>
        @else
            <!-- No Results -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                <i class="fa fa-film text-yellow-600 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No content found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your search filters or browse all content.</p>
                <a href="{{ url('/XXX/All') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-film mr-2"></i> Browse All XXX
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

