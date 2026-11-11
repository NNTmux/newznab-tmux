@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<x-page-header title="Music" :current="!empty($categorytitle) ? $categorytitle : 'All audio'" eyebrow="Listen and discover" description="Browse indexed music and refine the catalog by artist, title, genre, or year." icon="fas fa-headphones">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-compact-disc" aria-hidden="true"></i>{{ number_format($results->total()) }} releases</span>
        <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $categorytitle ?: 'All audio' }}</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm">

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Artist Filter -->
                    <div>
                        <label for="artist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Artist</label>
                        <x-input type="text"
                               id="artist"
                               name="artist"
                               value="{{ $artist ?? '' }}"
                               placeholder="Search by artist" />
                    </div>

                    <!-- Title Filter -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <x-input type="text"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Search by title" />
                    </div>

                    <!-- Genre Filter -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                        <x-select id="genre" name="genre">
                            <option value="">All Genres</option>
                            @foreach($genres ?? [] as $g)
                                <option value="{{ $g->id }}" {{ ($genre ?? '') == $g->id ? 'selected' : '' }}>
                                    {{ $g->title }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    <!-- Year Filter -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                        <x-select id="year" name="year">
                            <option value="">All Years</option>
                            @foreach($years ?? [] as $y)
                                <option value="{{ $y }}" {{ ($year ?? '') == $y ? 'selected' : '' }}>
                                    {{ $y }}
                                </option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover dark:hover:bg-primary-800">
                        <i aria-hidden="true" class="fa-solid fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/browse/Audio/' . ($categorytitle ?: 'All')) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        <i aria-hidden="true" class="fa-solid fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>


        <!-- Results -->
        @if(count($results) > 0)
            <x-cover-results-toolbar
                :results="$results"
                icon="fa-solid fa-music"
                :title="($catname ?? 'All') . ' Albums'"
                covgroup="music"
                :category="$categorytitle ?? 'All'"
                parentcat="Audio"
                search-placeholder="Search in Audio..."
                :search-category="$category ?? null"
                :category-items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, route('Audio'), 'All audio', true)"
                :ordering="$ordering ?? null"
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
                                            <img width="128" height="192" src="{{ url('/covers/music/' . $result->cover) }}"
                                                 alt="{{ $result->artist ?? '' }} - {{ $result->title ?? '' }}"
                                                 class="w-32 h-48 object-cover"
                                                 loading="lazy"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                        @else
                                            <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <i aria-hidden="true" class="fas fa-music text-gray-400 text-2xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <i aria-hidden="true" class="fas fa-music text-gray-400 text-2xl"></i>
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
                                            <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-danger-100 dark:bg-danger-900/40 text-danger-800 dark:text-danger-200 border border-danger-200 mt-1">
                                                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-1"></i>
                                                <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            @if(!empty($result->year))
                                                <span><i aria-hidden="true" class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                                            @endif
                                            @if(!empty($result->genre))
                                                <span><i aria-hidden="true" class="fas fa-tag mr-1"></i> {{ $result->genre }}</span>
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
            <x-empty-state
                icon="fa-solid fa-music"
                title="No albums found"
                message="Try adjusting your search filters or browse all music."
                :action-url="url('/browse/Audio/All')"
                action-label="Browse All Audio"
                action-icon="fa-solid fa-music"
            />
        @endif
    </div>
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection
