@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<x-page-header title="Games" :current="!empty($catname) ? $catname : 'All games'" eyebrow="Play and discover" description="Browse PC games and refine the catalog by title, genre, year, or category." icon="fas fa-dice-d20">
    <x-slot:stats>
        <span class="workspace-hero__stat"><i class="fas fa-gamepad" aria-hidden="true"></i>{{ number_format($results->total()) }} games</span>
        <span class="workspace-hero__stat"><i class="fas fa-folder-open" aria-hidden="true"></i>{{ $catname ?: 'All games' }}</span>
    </x-slot:stats>
</x-page-header>

<div class="surface-panel rounded-xl shadow-sm">

    <div class="px-6 py-4">
        <!-- Search Filters -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
            <form method="get" action="{{ url('/Games') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Category is chosen with the Category dropdown in the results toolbar. --}}
                    <input type="hidden" name="t" value="{{ $category ?? '' }}">

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
                    <button type="submit" class="px-4 py-2 bg-accent dark:bg-accent-hover text-white rounded-md hover:bg-accent-hover">
                        <i aria-hidden="true" class="fa-solid fa-search mr-2"></i>Search
                    </button>
                    <a href="{{ url('/Games') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300">
                        <i aria-hidden="true" class="fa-solid fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if(count($results) > 0)
            <x-cover-results-toolbar
                :results="$results"
                icon="fa-solid fa-gamepad"
                :title="($catname ?? 'All') . ' Games'"
                covgroup="games"
                :category="$catname ?? 'All'"
                parentcat="PC"
                search-placeholder="Search in Games..."
                :search-category="$category ?? null"
                :category-items="\App\Support\Navigation\ListFilters::categoryItems($catlist ?? [], $category ?? null, url('/Games'), 'All games', false)"
                :ordering="$ordering ?? null"
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
                                            <img width="128" height="192" src="{{ url('/covers/games/' . $result->cover) }}"
                                                 alt="{{ $result->title ?? $result->searchname }}"
                                                 class="w-32 h-48 object-cover"
                                                 loading="lazy"
                                                 data-fallback-src="{{ url('/images/no-cover.png') }}">
                                        @else
                                            <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                                <i aria-hidden="true" class="fas fa-gamepad text-gray-400 text-2xl"></i>
                                            </div>
                                        @endif
                                    </a>
                                @else
                                    <div class="w-32 h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <i aria-hidden="true" class="fas fa-gamepad text-gray-400 text-2xl"></i>
                                    </div>
                                @endif
                            </div>

                            <!-- Game Details -->
                            <div class="flex-1 p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $result->title ?? $result->searchname }}</h3>

                                        @if($totalFailed > 0)
                                            <div class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-danger-100 dark:bg-danger-900/40 text-danger-800 dark:text-danger-200 border border-danger-200 mt-1">
                                                <i aria-hidden="true" class="fas fa-exclamation-triangle mr-1"></i>
                                                <span>{{ $totalFailed }} failed report{{ $totalFailed > 1 ? 's' : '' }}</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-4 mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            @if(!empty($result->releasedate))
                                                <span><i aria-hidden="true" class="fas fa-calendar mr-1"></i> {{ date('Y', strtotime($result->releasedate)) }}</span>
                                            @elseif(!empty($result->year))
                                                <span><i aria-hidden="true" class="fas fa-calendar mr-1"></i> {{ $result->year }}</span>
                                            @endif
                                            @if(!empty($result->platform))
                                                <span><i aria-hidden="true" class="fas fa-desktop mr-1"></i> {{ $result->platform }}</span>
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
            <x-empty-state
                icon="fa-solid fa-gamepad"
                title="No games found"
                message="Try adjusting your search filters or browse all games."
                :action-url="url('/Games')"
                action-label="Browse All Games"
                action-icon="fa-solid fa-gamepad"
            />
        @endif
    </div>
</div>

{{-- NFO modal is included globally via layouts.main --}}
@endsection
