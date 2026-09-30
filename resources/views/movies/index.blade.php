@extends('layouts.main')

@push('modals')
    @include('partials.release-modals')
@endpush

@section('content')
@php
    $currentMovieCategory = !empty($categorytitle) ? $categorytitle : 'All movies';
    $activeMovieFilters = collect([
        'Title' => $title ?? '',
        'Genre' => $genre ?? '',
        'Year' => $year ?? '',
        'Rating' => !empty($rating) ? $rating . '+ stars' : '',
    ])->filter(static fn ($value): bool => $value !== '' && $value !== null);
@endphp

<div class="space-y-5" x-data="moviesPage" data-movie-layout="{{ $movie_layout ?? 2 }}">
    <section class="movies-hero relative overflow-hidden rounded-2xl px-5 py-4 text-white shadow-lg sm:px-7 sm:py-5" aria-labelledby="movies-heading">
        <div class="movies-hero__glow movies-hero__glow--one" aria-hidden="true"></div>
        <div class="movies-hero__glow movies-hero__glow--two" aria-hidden="true"></div>

        <div class="relative z-10 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0 max-w-3xl xl:flex-1">
                <nav aria-label="Breadcrumb" class="mb-2 text-sm text-white/70">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><a href="{{ url($site->homeLink ?? '/') }}" class="rounded hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70">Home</a></li>
                        <li aria-hidden="true"><i class="fas fa-chevron-right text-[0.65rem]"></i></li>
                        <li aria-current="page" class="font-medium text-white">{{ $currentMovieCategory }}</li>
                    </ol>
                </nav>

                <div class="flex items-center gap-3">
                    <span class="movies-hero__icon" aria-hidden="true"><i class="fas fa-clapperboard"></i></span>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-[0.18em] text-white/65">Discover and download</p>
                        <h1 id="movies-heading" class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Movies</h1>
                    </div>
                </div>

                <p class="mt-2 max-w-2xl text-sm leading-5 text-white/75 sm:text-base">
                    Browse available movies, compare recent releases, and narrow the catalog by title, genre, year, or rating.
                </p>

            </div>

            <div class="movies-hero__meta flex min-w-0 flex-wrap items-center gap-3 xl:max-w-[48%] xl:justify-end">
                <div class="flex flex-wrap gap-2 text-sm xl:justify-end">
                    <span class="movies-hero__stat">
                        <i class="fas fa-film" aria-hidden="true"></i>
                        {{ isset($results) ? number_format($results->total()) : 0 }} movies
                    </span>
                    <span class="movies-hero__stat">
                        <i class="fas fa-folder-open" aria-hidden="true"></i>
                        {{ $currentMovieCategory }}
                    </span>
                    @if($activeMovieFilters->isNotEmpty())
                        <span class="movies-hero__stat">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            {{ $activeMovieFilters->count() }} active {{ Str::plural('filter', $activeMovieFilters->count()) }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button"
                            @click="toggleLayout()"
                            class="movies-hero__action"
                            :aria-label="layout === 1 ? 'Switch to two-column movie grid' : 'Switch to one-column movie list'"
                            title="Change movie layout">
                        <i :class="layoutIcon" aria-hidden="true"></i>
                        <span x-text="layoutLabel"></span>
                    </button>

                    <a href="{{ route('trending-movies') }}" class="movies-hero__action movies-hero__action--accent">
                        <i class="fas fa-fire" aria-hidden="true"></i>
                        Trending now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="surface-panel overflow-hidden rounded-2xl border shadow-sm" aria-labelledby="movie-filters-heading">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 id="movie-filters-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-sliders mr-2 text-primary-500" aria-hidden="true"></i>Find your next movie
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Combine filters to quickly narrow the catalog.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-view-toggle
                    current-view="covers"
                    covgroup="movies"
                    :category="$categorytitle ?: 'All'"
                    parentcat="Movies"
                    :shows="false"
                />
                @if($activeMovieFilters->isNotEmpty())
                    <a href="{{ route('Movies') }}" class="text-sm font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
                        <i class="fas fa-rotate-left mr-1" aria-hidden="true"></i>Clear filters
                    </a>
                @endif
            </div>
        </div>

        <form method="get" action="{{ route('Movies') }}" class="p-5 sm:p-6" aria-label="Movie filters">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label for="title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-gray-400" aria-hidden="true">
                            <i class="fas fa-magnifying-glass text-sm"></i>
                        </span>
                        <input type="search"
                               id="title"
                               name="title"
                               value="{{ $title ?? '' }}"
                               placeholder="Movie title"
                               autocomplete="off"
                               class="movie-filter-control pl-10">
                    </div>
                </div>

                <div>
                    <label for="genre" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Genre</label>
                    <select id="genre" name="genre" class="movie-filter-control">
                        <option value="">All genres</option>
                        @foreach($genres ?? [] as $gen)
                            <option value="{{ $gen }}" {{ (isset($genre) && $genre === $gen) ? 'selected' : '' }}>{{ $gen }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="year" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Release year</label>
                    <select id="year" name="year" class="movie-filter-control">
                        <option value="">Any year</option>
                        @foreach($years ?? [] as $yr)
                            <option value="{{ $yr }}" {{ (isset($year) && (int) $year === (int) $yr) ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="rating" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Minimum rating</label>
                    <select id="rating" name="rating" class="movie-filter-control">
                        <option value="">Any rating</option>
                        @foreach($ratings ?? [] as $rate)
                            <option value="{{ $rate }}" {{ (isset($rating) && (int) $rating === (int) $rate) ? 'selected' : '' }}>{{ $rate }}+ stars</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-h-7 flex-wrap items-center gap-2" aria-live="polite">
                    @forelse($activeMovieFilters as $label => $value)
                        <span class="movie-filter-chip"><span class="font-semibold">{{ $label }}:</span> {{ $value }}</span>
                    @empty
                        <span class="text-sm text-gray-500 dark:text-gray-400">Showing the full movie catalog.</span>
                    @endforelse
                </div>

                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-primary-700 dark:hover:bg-primary-600">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    Search movies
                </button>
            </div>
        </form>
    </section>

    @if(isset($results) && $results->count() > 0)
        <section aria-labelledby="movie-results-heading">
            <div class="mb-4 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 id="movie-results-heading" class="font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($results->total()) }} {{ Str::plural('movie', $results->total()) }} found
                    </h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ number_format($results->firstItem()) }}–{{ number_format($results->lastItem()) }} on this page
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-inline-search placeholder="Quick release search..." :category="$category ?? null" />
                    <div class="overflow-x-auto">{{ $results->links() }}</div>
                </div>
            </div>

            <div id="moviesGrid" class="movies-grid" :data-layout="layout">
                @foreach($results as $result)
                    @include('movies.partials.movie-card', ['result' => $result, 'site' => $site])
                @endforeach
            </div>

            <div class="mt-6 flex justify-center rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                {{ $results->links() }}
            </div>
        </section>
    @else
        <section class="surface-panel rounded-2xl border shadow-sm" aria-labelledby="movie-results-heading">
            <h2 id="movie-results-heading" class="sr-only">Movie results</h2>
            <x-empty-state
                icon="fas fa-film"
                title="No movies found"
                message="Try broadening your filters or clear them to return to the full catalog."
                action-url="{{ $activeMovieFilters->isNotEmpty() ? route('Movies') : null }}"
                action-label="{{ $activeMovieFilters->isNotEmpty() ? 'Clear filters' : null }}"
                action-icon="fas fa-rotate-left"
            />
        </section>
    @endif
</div>
@endsection
