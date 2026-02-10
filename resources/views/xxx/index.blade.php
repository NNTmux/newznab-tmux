@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
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

            <!-- XXX Grid - Card Layout with Multiple Releases -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($results as $result)
                    @php
                        // Extract grouped release data
                        $releaseGuids = isset($result->grp_release_guid) ? explode(',', $result->grp_release_guid) : [];
                        $releaseNames = isset($result->grp_release_name) ? explode('#', $result->grp_release_name) : [];
                        $releaseSizes = isset($result->grp_release_size) ? explode(',', $result->grp_release_size) : [];
                        $releasePostDates = isset($result->grp_release_postdate) ? explode(',', $result->grp_release_postdate) : [];
                        $releaseGrabs = isset($result->grp_release_grabs) ? explode(',', $result->grp_release_grabs) : [];
                        $releaseComments = isset($result->grp_release_comments) ? explode(',', $result->grp_release_comments) : [];
                        $releaseNfoIds = isset($result->grp_release_nfoid) ? explode(',', $result->grp_release_nfoid) : [];
                        $releaseHasPreview = isset($result->grp_haspreview) ? explode(',', $result->grp_haspreview) : [];
                        $releaseCategories = isset($result->grp_release_catname) ? explode(',', $result->grp_release_catname) : [];
                        $failedCounts = isset($result->grp_release_failed) ? array_filter(explode(',', $result->grp_release_failed)) : [];
                        $totalFailed = array_sum($failedCounts);

                        // Limit to maximum 2 releases displayed
                        $maxReleases = 2;
                        $totalReleases = count($releaseGuids);
                        $releaseGuids = array_slice($releaseGuids, 0, $maxReleases);
                        $releaseNames = array_slice($releaseNames, 0, $maxReleases);
                        $releaseSizes = array_slice($releaseSizes, 0, $maxReleases);
                        $releasePostDates = array_slice($releasePostDates, 0, $maxReleases);
                        $releaseGrabs = array_slice($releaseGrabs, 0, $maxReleases);
                        $releaseComments = array_slice($releaseComments, 0, $maxReleases);
                        $releaseNfoIds = array_slice($releaseNfoIds, 0, $maxReleases);
                        $releaseHasPreview = array_slice($releaseHasPreview, 0, $maxReleases);
                        $releaseCategories = array_slice($releaseCategories, 0, $maxReleases);

                        $guid = $releaseGuids[0] ?? null;
                    @endphp

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="flex flex-row">
                            <!-- Cover Image -->
                            <div class="flex-shrink-0">
                                @if($guid)
                                    <a href="{{ url('/details/' . $guid) }}" class="block">
                                        @if(isset($result->cover) && $result->cover == 1)
                                            <img src="{{ url('/covers/xxx/' . $result->id . '-cover.jpg') }}"
                                                 alt="{{ $result->title }}"
                                                 class="w-32 h-48 object-cover"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                        @else
                                            <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <i class="fas fa-film text-gray-400 text-2xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <i class="fas fa-film text-gray-400 text-2xl"></i>
                                    </div>
                                @endif
                            </div>

                            <!-- Content Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $result->title }}</h3>

                                        @if($totalFailed > 0)
                                            <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200 mt-1">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                            </div>
                                        @endif

                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            @if(!empty($result->genre))
                                                <div>
                                                    <strong>Genre:</strong> {!! makeFieldLinks((array) $result, 'genre', 'xxx') !!}
                                                </div>
                                            @endif
                                            @if(!empty($result->actors))
                                                <div>
                                                    <strong>Actors:</strong> {!! makeFieldLinks((array) $result, 'actors', 'xxx') !!}
                                                </div>
                                            @endif
                                            @if(!empty($result->director))
                                                <div>
                                                    <strong>Director:</strong> {!! makeFieldLinks((array) $result, 'director', 'xxx') !!}
                                                </div>
                                            @endif
                                        </div>

                                        @if(isset($result->plot) && $result->plot)
                                            <p class="text-gray-700 dark:text-gray-300 text-sm mt-2 line-clamp-2">{{ $result->plot }}</p>
                                        @endif
                                    </div>
                                </div>

                                <!-- Release Information -->
                                @if(!empty($releaseGuids[0]))
                                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                            Available Releases
                                            @if($totalReleases > $maxReleases)
                                                <span class="text-xs font-normal text-gray-500">(Showing {{ $maxReleases }} of {{ $totalReleases }})</span>
                                            @endif
                                        </h4>
                                        <div class="space-y-2">
                                            @foreach($releaseNames as $index => $releaseName)
                                                @if($releaseName && isset($releaseGuids[$index]))
                                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-2 border border-gray-200 dark:border-gray-700">
                                                        <div class="space-y-2">
                                                            <!-- Release Name -->
                                                            <a href="{{ url('/details/' . $releaseGuids[$index]) }}" class="text-sm text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium block break-all" title="{{ $releaseName }}">
                                                                {{ $releaseName }}
                                                            </a>

                                                            <!-- Info Badges -->
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                @if(isset($releaseSizes[$index]))
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                                        <i class="fas fa-hdd mr-1"></i>{{ number_format($releaseSizes[$index] / 1073741824, 2) }} GB
                                                                    </span>
                                                                @endif
                                                                @if(isset($releasePostDates[$index]))
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                                                        <i class="fas fa-calendar-alt mr-1"></i>{{ date('M d, Y H:i', strtotime($releasePostDates[$index])) }}
                                                                    </span>
                                                                @endif
                                                                @if(isset($releaseCategories[$index]) && !empty($releaseCategories[$index]))
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                                        <i class="fas fa-folder mr-1"></i>{{ $releaseCategories[$index] }}
                                                                    </span>
                                                                @endif
                                                                @if(isset($releaseGrabs[$index]) && $releaseGrabs[$index] > 0)
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                                        <i class="fas fa-download mr-1"></i>{{ $releaseGrabs[$index] }} grabs
                                                                    </span>
                                                                @endif
                                                                @if(isset($releaseNfoIds[$index]) && !empty($releaseNfoIds[$index]))
                                                                    <button type="button"
                                                                            class="nfo-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-200 dark:hover:bg-yellow-800 transition cursor-pointer"
                                                                            data-guid="{{ $releaseGuids[$index] }}"
                                                                            title="View NFO file">
                                                                        <i class="fas fa-file-alt mr-1"></i> NFO
                                                                    </button>
                                                                @endif
                                                                @if(isset($releaseHasPreview[$index]) && $releaseHasPreview[$index] == 1)
                                                                    <button type="button"
                                                                            class="preview-badge inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800 transition cursor-pointer"
                                                                            data-guid="{{ $releaseGuids[$index] }}"
                                                                            title="View preview image">
                                                                        <i class="fas fa-image mr-1"></i> Preview
                                                                    </button>
                                                                @endif
                                                            </div>

                                                            <!-- Action Buttons -->
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                <a href="{{ url('/getnzb/' . $releaseGuids[$index]) }}" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-600 dark:bg-green-700 text-white hover:bg-green-700 dark:hover:bg-green-800 transition">
                                                                    <i class="fas fa-download mr-1"></i> Download
                                                                </a>
                                                                <button class="add-to-cart inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800 transition" data-guid="{{ $releaseGuids[$index] }}">
                                                                    <i class="fas fa-shopping-cart mr-1"></i> Cart
                                                                </button>
                                                                <a href="{{ url('/details/' . $releaseGuids[$index]) }}" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-600 dark:bg-gray-700 text-white hover:bg-gray-700 dark:hover:bg-gray-800 transition">
                                                                    <i class="fas fa-info-circle mr-1"></i> Details
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
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

<!-- NFO Modal -->
@include('partials.nfo-modal')

<!-- Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black/75 items-center justify-center p-4 z-50">
    <div class="relative max-w-4xl w-full">
        <button type="button" data-close-preview-modal class="absolute top-4 right-4 text-white hover:text-gray-300 text-3xl font-bold z-10">
            <i class="fas fa-times"></i>
        </button>
        <div class="text-center mb-2">
            <h3 id="previewTitle" class="text-white text-lg font-semibold"></h3>
        </div>
        <img id="previewImage" src="" alt="Preview" class="max-w-full max-h-[90vh] mx-auto rounded-2xl shadow-2xl">
        <div class="text-center mt-4">
            <p id="previewError" class="text-red-400 hidden"></p>
        </div>
    </div>
</div>
@endsection

