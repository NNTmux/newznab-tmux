@extends('layouts.main')

@section('content')
@php
    // OMDb stores the Tomatometer as e.g. "78%"; "N/A" or an empty value means no score.
    $rtPercent = preg_match('/^(\d{1,3})%$/', trim((string) ($movie['rtrating'] ?? '')), $rtMatch) === 1 ? (int) $rtMatch[1] : null;
    $rtFresh = $rtPercent !== null && $rtPercent >= 60;
@endphp
<div class="movie-detail-page bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <x-page-header :title="$movie['title'] ?? 'Movie details'" eyebrow="Movie information" description="Explore the movie metadata, synopsis, ratings, and related release information." icon="fas fa-clapperboard">
        <x-slot:stats>
            @if(!empty($movie['year'] ?? null))
                <span class="workspace-hero__stat"><i class="fas fa-calendar" aria-hidden="true"></i>{{ $movie['year'] }}</span>
            @endif
            @if(!empty($movie['rating'] ?? null))
                <span class="workspace-hero__stat"><i class="fas fa-star" aria-hidden="true"></i>{{ $movie['rating'] }}/10</span>
            @endif
            @if($rtPercent !== null)
                <span class="workspace-hero__stat" title="Rotten Tomatoes Tomatometer"><i class="fas {{ $rtFresh ? 'fa-apple-whole' : 'fa-splotch' }}" aria-hidden="true"></i>{{ $rtPercent }}%</span>
            @endif
        </x-slot:stats>
    </x-page-header>

    @if(isset($movie))
        <div class="px-6 py-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-[12rem_minmax(0,1fr)] lg:grid-cols-[15rem_minmax(0,1fr)]">
                <!-- Movie Poster (fixed narrow column) -->
                <div>
                    @if(!empty($movie['cover'] ?? null))
                        <img src="{{ $movie['cover'] }}" alt="{{ $movie['title'] ?? 'Movie' }}" class="movie-detail-poster mx-auto w-full max-w-60 rounded-lg sm:max-w-none" width="320" height="480" loading="lazy" decoding="async">
                    @else
                        <div class="movie-detail-poster mx-auto flex aspect-[2/3] w-full max-w-60 items-center justify-center rounded-lg bg-gray-200 dark:bg-gray-700 sm:max-w-none">
                            <i aria-hidden="true" class="fas fa-film text-gray-400 text-6xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Movie Details -->
                <div class="min-w-0">
                    @if(!empty($movie['tagline'] ?? null))
                        <p class="text-lg text-gray-600 dark:text-gray-400 italic mb-4">"{{ $movie['tagline'] }}"</p>
                    @endif

                    @if(!empty($movie['rating'] ?? null) || $rtPercent !== null)
                        <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-3">
                            @if(!empty($movie['rating'] ?? null))
                                <div class="flex items-center" title="Rating">
                                    <span class="text-warning-500 text-2xl mr-2">
                                        <i aria-hidden="true" class="fas fa-star"></i>
                                    </span>
                                    <span class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $movie['rating'] }}</span>
                                    <span class="text-gray-600 dark:text-gray-400 ml-2">/ 10</span>
                                </div>
                            @endif
                            <x-rotten-tomatoes-score :score="$movie['rtrating'] ?? null" />
                        </div>
                    @endif

                    <div class="space-y-3 mb-6">
                        @if(!empty($movie['year'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Year:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $movie['year'] }}</span>
                            </div>
                        @endif

                        @if(!empty($movie['genre'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Genre:</span>
                                <span class="text-gray-700 dark:text-gray-300">{!! $movie['genre'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['director'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Director:</span>
                                <span class="text-gray-700 dark:text-gray-300">{!! $movie['director'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['actors'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Cast:</span>
                                <span class="text-gray-700 dark:text-gray-300">{!! $movie['actors'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['language'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Language:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $movie['language'] }}</span>
                            </div>
                        @endif
                    </div>

                    @if(!empty($movie['plot'] ?? null))
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Plot Synopsis</h2>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $movie['plot'] }}</p>
                        </div>
                    @endif

                    <!-- External Links -->
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-3">External Links</h2>
                        <div class="flex flex-wrap gap-3">
                            <!-- Trailer -->
                            @if(!empty($movie['trailer_url'] ?? null))
                                <a href="{{ $site->dereferrerLink }}{{ $movie['trailer_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover transition">
                                    <i aria-hidden="true" class="fas fa-circle-play mr-2"></i> Watch Trailer
                                </a>
                            @endif
                            @if(!empty($movie['imdbid'] ?? null))
                                <a href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $movie['imdbid'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-warning-100 dark:bg-warning-900/40 text-warning-800 dark:text-warning-200 rounded-lg hover:bg-warning-200 transition">
                                    <i aria-hidden="true" class="fab fa-imdb mr-2 text-xl"></i> View on IMDb
                                </a>
                            @endif
                            @if(!empty($movie['tmdbid'] ?? null))
                                <a href="{{ $site->dereferrerLink }}https://www.themoviedb.org/movie/{{ $movie['tmdbid'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-primary-100 dark:bg-primary-900/40 text-primary-800 dark:text-primary-200 rounded-lg hover:bg-primary-200 transition">
                                    <i aria-hidden="true" class="fas fa-film mr-2"></i> View on TMDb
                                </a>
                            @endif
                            @if(!empty($movie['traktid'] ?? null))
                                <a href="{{ $site->dereferrerLink }}https://trakt.tv/movies/{{ $movie['traktid'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-danger-100 dark:bg-danger-900/40 text-danger-800 dark:text-danger-200 rounded-lg hover:bg-danger-200 transition">
                                    <i aria-hidden="true" class="fas fa-heart mr-2"></i> View on Trakt
                                </a>
                            @endif
                            @if(!empty($movie['imdbid'] ?? null) && auth()->check())
                                <a href="{{ url('/mymovies?id=add&imdb=' . $movie['imdbid'] . '&from=' . urlencode(request()->fullUrl())) }}" class="inline-flex items-center px-4 py-2 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200 rounded-lg hover:bg-purple-200 transition">
                                    <i aria-hidden="true" class="fas fa-plus-circle mr-2"></i> Add to My Movies
                                </a>
                            @endif
                        </div>
                    </div>

                </div>
            </div>

            <!-- All Available Releases -->
            @if(isset($releases) && count($releases) > 0)
                <div class="mt-8 pt-8 border-t border-gray-200" x-data="qualityFilter">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            Available Releases
                            <span class="text-lg font-normal text-gray-500" id="release-count" x-text="countText()"></span>
                        </h2>

                        <!-- Filter Buttons -->
                        <div class="space-y-3">
                            <!-- Resolution Filters -->
                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-2">Resolution:</span>
                                <button type="button" data-resolution="all"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-accent text-white hover:bg-accent-hover">
                                    <i aria-hidden="true" class="fas fa-list mr-1"></i> All
                                </button>
                                <button type="button" data-resolution="720p"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-tv mr-1"></i> 720p
                                </button>
                                <button type="button" data-resolution="1080p"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-desktop mr-1"></i> 1080p
                                </button>
                                <button type="button" data-resolution="2160p"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-film mr-1"></i> 2160p / 4K
                                </button>
                            </div>

                            <!-- Source Filters -->
                            <div class="flex flex-wrap gap-2 items-center">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-2">Source:</span>
                                <button type="button" data-source="all"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-purple-600 text-white hover:bg-purple-700">
                                    <i aria-hidden="true" class="fas fa-list mr-1"></i> All
                                </button>
                                <button type="button" data-source="bluray"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-compact-disc mr-1"></i> Bluray
                                </button>
                                <button type="button" data-source="web-dl"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-cloud-download-alt mr-1"></i> WEB-DL
                                </button>
                                <button type="button" data-source="webrip"
                                        class="px-4 py-2 rounded-lg text-sm font-medium transition bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                                    <i aria-hidden="true" class="fas fa-globe mr-1"></i> WEBRip
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3" id="releases-container">
                        @foreach($releases as $release)
                            <div class="movie-release-card release-item bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700" data-release-name="{{ strtolower($release->searchname) }}">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <a href="{{ url('/details/' . $release->guid) }}" class="text-base text-gray-800 dark:text-gray-200 hover:text-link dark:text-primary-400 font-medium block truncate" title="{{ $release->searchname }}">
                                            {{ $release->searchname }}
                                        </a>
                                        <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-500">
                                            <span>
                                                <i aria-hidden="true" class="fas fa-hdd mr-1"></i>{{ number_format($release->size / 1073741824, 2) }} GB
                                            </span>
                                            @if($release->postdate)
                                                <span>
                                                    <i aria-hidden="true" class="fas fa-calendar-alt mr-1"></i>Posted: {{ userDate($release->postdate, 'M d, Y H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2 mt-2 text-xs">
                                            @if($release->adddate)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
                                                    <i aria-hidden="true" class="fas fa-plus-circle mr-1"></i>Added: {{ userDateDiffForHumans($release->adddate) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        <x-release-row-actions :result="$release" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-8 border-t border-line pt-8">
                    <x-empty-state icon="fas fa-inbox" title="No releases available" message="Nothing has been posted for this movie yet." />
                </div>
            @endif
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <i aria-hidden="true" class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Movie details not available.</p>
        </div>
    @endif
</div>

@endsection
