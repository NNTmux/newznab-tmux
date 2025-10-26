@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site['home_link'] ?? '/') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:text-blue-400 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="{{ route('Movies') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600">Movies</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">{{ $movie['title'] ?? 'Movie Details' }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    @if(isset($movie))
        <div class="px-6 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Movie Poster -->
                <div class="lg:col-span-1">
                    @if(!empty($movie['cover'] ?? null))
                        <img src="{{ $movie['cover'] }}" alt="{{ $movie['title'] ?? 'Movie' }}" class="w-full rounded-lg shadow-lg">
                    @else
                        <div class="w-full h-96 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <i class="fas fa-film text-gray-400 text-6xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Movie Details -->
                <div class="lg:col-span-2">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">{{ $movie['title'] ?? 'Unknown Title' }}</h1>

                    @if(!empty($movie['tagline'] ?? null))
                        <p class="text-lg text-gray-600 dark:text-gray-400 italic mb-4">"{{ $movie['tagline'] }}"</p>
                    @endif

                    @if(!empty($movie['rating'] ?? null))
                        <div class="flex items-center mb-4">
                            <span class="text-yellow-500 text-2xl mr-2">
                                <i class="fas fa-star"></i>
                            </span>
                            <span class="text-2xl font-semibold text-gray-900">{{ $movie['rating'] }}</span>
                            <span class="text-gray-600 dark:text-gray-400 ml-2">/ 10</span>
                        </div>
                    @endif

                    <div class="space-y-3 mb-6">
                        @if(!empty($movie['year'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Year:</span>
                                <span class="text-gray-600">{{ $movie['year'] }}</span>
                            </div>
                        @endif

                        @if(!empty($movie['genre'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Genre:</span>
                                <span class="text-gray-600">{!! $movie['genre'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['director'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Director:</span>
                                <span class="text-gray-600">{!! $movie['director'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['actors'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Cast:</span>
                                <span class="text-gray-600">{!! $movie['actors'] !!}</span>
                            </div>
                        @endif

                        @if(!empty($movie['language'] ?? null))
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Language:</span>
                                <span class="text-gray-600">{{ $movie['language'] }}</span>
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
                            @if(!empty($movie['imdbid'] ?? null))
                                <a href="{{ $site['dereferrer_link'] }}https://www.imdb.com/title/tt{{ $movie['imdbid'] }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition">
                                    <i class="fab fa-imdb mr-2 text-xl"></i> View on IMDb
                                </a>
                            @endif
                            @if(!empty($movie['tmdbid'] ?? null))
                                <a href="{{ $site['dereferrer_link'] }}https://www.themoviedb.org/movie/{{ $movie['tmdbid'] }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition">
                                    <i class="fas fa-film mr-2"></i> View on TMDb
                                </a>
                            @endif
                            @if(!empty($movie['traktid'] ?? null))
                                <a href="{{ $site['dereferrer_link'] }}https://trakt.tv/movies/{{ $movie['traktid'] }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition">
                                    <i class="fas fa-heart mr-2"></i> View on Trakt
                                </a>
                            @endif
                            @if(!empty($movie['imdbid'] ?? null) && auth()->check())
                                <a href="{{ url('/mymovies?id=add&imdb=' . $movie['imdbid'] . '&from=' . urlencode(request()->fullUrl())) }}" class="inline-flex items-center px-4 py-2 bg-purple-100 text-purple-800 rounded-lg hover:bg-purple-200 transition">
                                    <i class="fas fa-plus-circle mr-2"></i> Add to My Movies
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Trailer -->
                    @if(!empty($movie['trailer'] ?? null))
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Trailer</h2>
                            <div class="aspect-video">
                                {!! $movie['trailer'] !!}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- All Available Releases -->
            @if(isset($releases) && count($releases) > 0)
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                        Available Releases
                        <span class="text-lg font-normal text-gray-500">({{ count($releases) }} total)</span>
                    </h2>

                    <div class="space-y-3">
                        @foreach($releases as $release)
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 border border-gray-200 dark:border-gray-700 hover:shadow-md transition">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <a href="{{ url('/details/' . $release['guid']) }}" class="text-base text-gray-800 dark:text-gray-200 hover:text-blue-600 dark:text-blue-400 font-medium block truncate" title="{{ $release['name'] }}">
                                            {{ $release['name'] }}
                                        </a>
                                        <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-500">
                                            <span>
                                                <i class="fas fa-hdd mr-1"></i>{{ number_format($release['size'] / 1073741824, 2) }} GB
                                            </span>
                                            @if($release['postdate'])
                                                <span>
                                                    <i class="fas fa-calendar-alt mr-1"></i>Posted: {{ userDate($release['postdate'], 'M d, Y H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2 mt-2 text-xs">
                                            @if(!empty($release['group_name']))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                    <i class="fas fa-users mr-1"></i> {{ $release['group_name'] }}
                                                </span>
                                            @endif
                                            @if($release['postdate'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                    <i class="fas fa-calendar-alt mr-1"></i>Posted: {{ \Carbon\Carbon::parse($release['postdate'])->format('M d, Y H:i') }}
                                                </span>
                                            @endif
                                            @if(!empty($release['fromname']))
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200 font-mono">
                                                    <i class="fas fa-user mr-1"></i>{{ $release['fromname'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex gap-2 flex-shrink-0">
                                        <a href="{{ url('/getnzb/' . $release['guid']) }}" class="inline-flex items-center px-4 py-2 bg-green-600 dark:bg-green-700 text-white text-sm font-medium rounded-lg hover:bg-green-700 dark:hover:bg-green-800 transition">
                                            <i class="fas fa-download mr-2"></i> Download
                                        </a>
                                        <button class="add-to-cart inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white text-sm font-medium rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition" data-guid="{{ $release['guid'] }}">
                                            <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                                        </button>
                                        <a href="{{ url('/details/' . $release['guid']) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition">
                                            <i class="fas fa-info-circle mr-2"></i> Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="text-center py-8">
                        <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-600 dark:text-gray-400 text-lg">No releases available for this movie.</p>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Movie details not available.</p>
        </div>
    @endif
</div>
@endsection

