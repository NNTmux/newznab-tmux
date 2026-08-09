@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-primary-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/PC') }}" class="hover:text-primary-600">PC</a></li>
                @if(!empty($catname) && $catname !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500">{{ $catname }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/Games') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Category Filter -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select id="category"
                                name="t"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Categories</option>
                            @foreach($catlist ?? [] as $cat)
                                <option value="{{ $cat['id'] }}" {{ ($category ?? '') == $cat['id'] ? 'selected' : '' }}>
                                    {{ $cat['title'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    </div>

                    <!-- Genre Filter -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                        <select id="genre"
                                name="genre"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Genres</option>
                            @foreach($genres ?? [] as $g)
                                <option value="{{ $g->id }}" {{ ($genre ?? '') == $g->id ? 'selected' : '' }}>
                                    {{ $g->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                        <select id="year"
                                name="year"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                            <option value="">All Years</option>
                            @foreach($years ?? [] as $y)
                                <option value="{{ $y }}" {{ ($year ?? '') == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-700 text-white rounded-md hover:bg-primary-700">
                        <i class="fa fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/Games') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300">
                        <i class="fa fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <x-cover-results-toolbar
                :results="$results"
                icon="fa fa-gamepad"
                :title="($catname ?? 'All') . ' Games'"
                covgroup="games"
                :category="$catname ?? 'All'"
                parentcat="PC"
                search-placeholder="Search in Games..."
                :search-category="$category ?? null"
            />

            <!-- Games Grid - Card Layout with Multiple Releases -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                @foreach($results as $result)
                    @php
                        $releases = $result->releases ?? [];
                        $totalReleases = $result->total_releases ?? count($releases);
                        $guid = !empty($releases) ? $releases[0]->guid : null;
                        $totalFailed = collect($releases)->sum(fn($r) => (int)($r->failed_count ?? 0));
                    @endphp

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="flex flex-row">
                            <!-- Game Cover -->
                            <div class="shrink-0">
                                @if($guid)
                                    <a href="{{ url('/details/' . $guid) }}" class="block">
                                        @if(!empty($result->cover))
                                            <img src="{{ url('/covers/games/' . $result->cover) }}"
                                                 alt="{{ $result->title ?? $result->searchname }}"
                                                 class="w-32 h-48 object-cover"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                        @else
                                            <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <i class="fas fa-gamepad text-gray-400 text-2xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <i class="fas fa-gamepad text-gray-400 text-2xl"></i>
                                    </div>
                                @endif
                            </div>

                            <!-- Game Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $result->title ?? $result->searchname }}</h3>

                                        @if($totalFailed > 0)
                                            <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200 mt-1">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            @if(!empty($result->releasedate))
                                                <span><i class="fas fa-calendar mr-1"></i> {{ date('Y', strtotime($result->releasedate)) }}</span>
                                            @elseif(!empty($result->year))
                                                <span><i class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                                            @endif
                                            @if(!empty($result->platform))
                                                <span><i class="fas fa-desktop mr-1"></i> {{ $result->platform }}</span>
                                            @endif
                                        </div>

                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            @if(!empty($result->publisher))
                                                <div><strong>Publisher:</strong> {{ $result->publisher }}</div>
                                            @endif
                                            @if(!empty($result->genre))
                                                <div><strong>Genre:</strong> {{ $result->genre }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <x-cover-release-list :releases="$releases" :total-releases="$totalReleases" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $results->links() }}
            </div>
        @else
            <!-- No Results -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-8 text-center">
                <i class="fa fa-gamepad text-yellow-600 dark:text-yellow-500 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No games found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your search filters or browse all games.</p>
                <a href="{{ url('/Games') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700">
                    <i class="fa fa-gamepad mr-2"></i> Browse All Games
                </a>
            </div>
        @endif
    </div>
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection
