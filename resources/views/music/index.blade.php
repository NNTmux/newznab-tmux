@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <nav aria-label="breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
                <li><a href="{{ url($site['home_link'] ?? '/') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                <li><a href="{{ url('/browse/Audio') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Audio</a></li>
                @if(!empty($categorytitle) && $categorytitle !== 'All')
                    <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
                    <li class="text-gray-500 dark:text-gray-400">{{ $categorytitle }}</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Artist Filter -->
                    <div>
                        <label for="artist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
                        <input type="text"
                               id="artist"
                               name="artist"
                               value="{{ $artist ?? '' }}"
                               placeholder="Search by artist"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
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
                    <x-button type="submit" icon="fa fa-search">Search</x-button>
                    <x-button-link variant="muted" icon="fa fa-times" href="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}">Clear</x-button-link>
                </div>
            </form>
        </div>


        <!-- Results -->
        @if(count($results) > 0)
            <x-cover-results-toolbar
                :results="$results"
                icon="fa fa-music"
                :title="($catname ?? 'All') . ' Albums'"
                covgroup="music"
                :category="$categorytitle ?? 'All'"
                parentcat="Audio"
                search-placeholder="Search in Audio..."
                :search-category="$category ?? null"
            />

            <!-- Album Grid - Card Layout with Multiple Releases -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                @foreach($resultsadd as $result)
                    @php
                        $releases = $result->releases ?? [];
                        $totalReleases = $result->total_releases ?? count($releases);
                        $guid = !empty($releases) ? $releases[0]->guid : null;
                        $totalFailed = collect($releases)->sum(fn($r) => (int)($r->failed_count ?? 0));
                    @endphp

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="flex flex-row">
                            <!-- Album Cover -->
                            <div class="shrink-0">
                                @if($guid)
                                    <a href="{{ url('/details/' . $guid) }}" class="block">
                                        @if(!empty($result->cover))
                                            <img src="{{ url('/covers/music/' . $result->cover) }}"
                                                 alt="{{ $result->artist ?? '' }} - {{ $result->title ?? '' }}"
                                                 class="w-32 h-48 object-cover"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                        @else
                                            <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <i class="fas fa-music text-gray-400 text-2xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <i class="fas fa-music text-gray-400 text-2xl"></i>
                                    </div>
                                @endif
                            </div>

                            <!-- Album Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $result->title ?? 'Unknown Album' }}</h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $result->artist ?? 'Unknown Artist' }}</p>

                                        @if($totalFailed > 0)
                                            <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200 mt-1">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            @if(!empty($result->year))
                                                <span><i class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                                            @endif
                                            @if(!empty($result->genre))
                                                <span><i class="fas fa-tag mr-1"></i> {{ $result->genre }}</span>
                                            @endif
                                        </div>

                                        @if(!empty($result->label))
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <strong>Label:</strong> {{ $result->label }}
                                            </div>
                                        @endif
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
                <i class="fa fa-music text-yellow-600 dark:text-yellow-500 text-5xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">No albums found</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your search filters or browse all music.</p>
                <x-button-link href="{{ url('/browse/Audio/All') }}" icon="fa fa-music">Browse All Audio</x-button-link>
            </div>
        @endif
    </div>
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection
