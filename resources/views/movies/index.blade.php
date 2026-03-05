@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
<div class="surface-panel rounded-xl shadow-sm" x-data="moviesPage" data-movie-layout="{{ $movie_layout ?? 2 }}">
    @php
        $movieCrumbs = [
            ['label' => 'Home', 'url' => url($site['home_link'] ?? '/'), 'icon' => 'fas fa-home'],
            ['label' => 'Movies', 'url' => !empty($categorytitle) ? route('Movies') : null],
        ];
        if (!empty($categorytitle)) {
            $movieCrumbs[] = ['label' => $categorytitle];
        }
    @endphp
    <x-breadcrumb :items="$movieCrumbs" />

    {{-- Movies Filter Section --}}
    <div class="px-6 py-5 surface-panel-alt border-b">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-5">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-film mr-2 text-primary-600 dark:text-primary-400"></i>Filter Movies
                </h2>
                <x-view-toggle
                    current-view="covers"
                    covgroup="movies"
                    :category="$categorytitle ?: 'All'"
                    parentcat="Movies"
                    :shows="false"
                />
            </div>

            <div class="flex flex-wrap gap-2">
                {{-- Layout Toggle Button --}}
                <button type="button"
                        @click="toggleLayout()"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 dark:bg-gray-700 text-white rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600 transition shadow-md focus:outline-none focus:ring-2 focus:ring-gray-500"
                        title="Toggle layout">
                    <i class="mr-2" :class="layoutIcon"></i>
                    <span x-text="layoutLabel"></span>
                </button>

                {{-- Trending Movies Button --}}
                <a href="{{ route('trending-movies') }}"
                   class="inline-flex items-center px-4 py-2 bg-linear-to-r from-orange-500 to-red-600 text-white rounded-lg hover:from-orange-600 hover:to-red-700 transition shadow-md">
                    <i class="fas fa-fire mr-2"></i> View Trending Movies
                </a>
            </div>
        </div>

        {{-- Search Form --}}
        <form method="get" action="{{ route('Movies') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input type="text"
                           id="title"
                           name="title"
                           value="{{ $title ?? '' }}"
                           placeholder="Search by title..."
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition">
                </div>

                <div>
                    <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                    <select id="genre"
                            name="genre"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition">
                        <option value="">All Genres</option>
                        @if(isset($genres))
                            @foreach($genres as $gen)
                                <option value="{{ $gen }}" {{ (isset($genre) && $genre == $gen) ? 'selected' : '' }}>{{ $gen }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year</label>
                    <select id="year"
                            name="year"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition">
                        <option value="">All Years</option>
                        @if(isset($years))
                            @foreach($years as $yr)
                                <option value="{{ $yr }}" {{ (isset($year) && $year == $yr) ? 'selected' : '' }}>{{ $yr }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rating</label>
                    <select id="rating"
                            name="rating"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 transition">
                        <option value="">All Ratings</option>
                        @if(isset($ratings))
                            @foreach($ratings as $rate)
                                <option value="{{ $rate }}" {{ (isset($rating) && $rating == $rate) ? 'selected' : '' }}>{{ $rate }}+</option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center px-6 py-2 bg-primary-600 dark:bg-primary-700 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition shadow-md">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
        </form>
    </div>

    {{-- Movies List --}}
    @if(isset($results) && $results->count() > 0)
        <div class="px-6 py-6">
            {{-- Results Summary and Pagination --}}
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 pb-4 border-b border-gray-200 dark:border-gray-700 gap-4">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <span class="font-medium">{{ $results->total() }}</span> movies found
                    <span class="text-gray-500 dark:text-gray-400">
                        (showing {{ $results->firstItem() }}-{{ $results->lastItem() }})
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <x-inline-search placeholder="Search in Movies..." :category="$category ?? null" />
                    {{ $results->links() }}
                </div>
            </div>

            {{-- Movies Grid --}}
            <div id="moviesGrid"
                 class="movies-grid"
                 :data-layout="layout">
                @foreach($results as $result)
                    @include('movies.partials.movie-card', ['result' => $result, 'site' => $site])
                @endforeach
            </div>

            {{-- Bottom Pagination --}}
            <div class="mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                {{ $results->links() }}
            </div>
        </div>
    @else
        <x-empty-state
            icon="fas fa-film"
            title="No Movies Found"
            message="Try adjusting your search filters or check back later for new releases."
        />
    @endif
</div>

{{-- NFO, preview, and other modals are included globally via layouts.main --}}
@endsection

