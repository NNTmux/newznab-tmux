@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site['home_link'] ?? '/') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="{{ route('series') }}" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">TV Shows</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">Trending</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <!-- Header -->
    <div class="px-6 py-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">
                    <i class="fas fa-fire mr-2"></i>Trending TV Shows
                </h1>
                <p class="text-indigo-100">Most downloaded TV shows in the last 48 hours - Updated every hour</p>
            </div>
            <div class="text-right flex gap-2">
                @if(auth()->check())
                    <a href="{{ url('/rss/trending-shows?dl=1&api_token=' . auth()->user()->api_token) }}" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition shadow-md" title="RSS Feed">
                        <i class="fas fa-rss mr-2"></i> RSS Feed
                    </a>
                @endif
                <a href="{{ route('series') }}" class="inline-flex items-center px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition">
                    <i class="fas fa-tv mr-2"></i> Browse All TV Shows
                </a>
            </div>
        </div>
    </div>

    <!-- Trending TV Shows List -->
    @if(isset($trendingShows) && $trendingShows->count() > 0)
        <div class="px-6 py-6">
            <div class="grid grid-cols-1 gap-6">
                @foreach($trendingShows as $index => $show)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="flex flex-col md:flex-row">
                            <!-- Rank Badge -->
                            <div class="absolute top-4 left-4 z-10">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-lg
                                    {{ $index < 3 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : 'bg-gradient-to-br from-gray-400 to-gray-600' }}">
                                    #{{ $index + 1 }}
                                </div>
                            </div>

                            <!-- TV Show Poster -->
                            <div class="flex-shrink-0 relative">
                                <a href="{{ route('series', ['id' => $show->id]) }}" class="block">
                                    @if($show->image)
                                        <img src="{{ url('/covers/tvshows/' . $show->id . '.jpg') }}" alt="{{ $show->title }}" class="w-full md:w-64 h-96 object-cover" onerror="this.onerror=null; this.src='{{ url('/covers/tvshows/no-cover.jpg') }}'">
                                    @else
                                        <div class="w-full md:w-64 h-96 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                            <i class="fas fa-tv text-gray-400 text-5xl"></i>
                                        </div>
                                    @endif
                                </a>
                                <!-- Download Badge -->
                                <div class="absolute bottom-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-semibold shadow-lg">
                                    <i class="fas fa-download mr-1"></i>{{ number_format($show->total_downloads) }}
                                </div>
                            </div>

                            <!-- TV Show Details -->
                            <div class="flex-1 p-6 ml-0 md:ml-4">
                                <div class="flex flex-col h-full">
                                    <!-- Title and Info -->
                                    <div class="flex-1">
                                        <a href="{{ route('series', ['id' => $show->id]) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                                {{ $show->title }}
                                            </h2>
                                        </a>

                                        <div class="flex flex-wrap items-center gap-4 mb-4 text-sm text-gray-600 dark:text-gray-400">
                                            @if($show->started && $show->started != '0000-00-00')
                                                <span class="inline-flex items-center">
                                                    <i class="fas fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($show->started)->format('Y') }}
                                                </span>
                                            @endif
                                            @if($show->countries_id)
                                                <span class="inline-flex items-center">
                                                    <i class="fas fa-globe mr-1"></i> {{ strtoupper($show->countries_id) }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center text-blue-600 dark:text-blue-400">
                                                <i class="fas fa-film mr-1"></i> {{ $show->release_count }} Release{{ $show->release_count > 1 ? 's' : '' }}
                                            </span>
                                        </div>

                                        <!-- External Links -->
                                        <div class="flex items-center gap-2 mb-4 text-xs flex-wrap">
                                            @if($show->tvdb)
                                                <a href="{{ $site['dereferrer_link'] }}https://thetvdb.com/?tab=series&id={{ $show->tvdb }}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-800 rounded-lg hover:bg-green-200 transition">
                                                    <i class="fas fa-tv mr-1"></i> TVDB
                                                </a>
                                            @endif
                                            @if($show->tvmaze)
                                                <a href="{{ $site['dereferrer_link'] }}https://www.tvmaze.com/shows/{{ $show->tvmaze }}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-800 rounded-lg hover:bg-purple-200 transition">
                                                    <i class="fas fa-tv mr-1"></i> TVMaze
                                                </a>
                                            @endif
                                            @if($show->trakt)
                                                <a href="{{ $site['dereferrer_link'] }}https://trakt.tv/shows/{{ $show->trakt }}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-800 rounded-lg hover:bg-red-200 transition">
                                                    <i class="fas fa-heart mr-1"></i> Trakt
                                                </a>
                                            @endif
                                            @if($show->tmdb)
                                                <a href="{{ $site['dereferrer_link'] }}https://www.themoviedb.org/tv/{{ $show->tmdb }}" target="_blank" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition">
                                                    <i class="fas fa-film mr-1"></i> TMDb
                                                </a>
                                            @endif
                                        </div>

                                        <!-- Summary/Description -->
                                        @if($show->summary)
                                            <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed line-clamp-4 mb-4">
                                                {{ $show->summary }}
                                            </p>
                                        @endif
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-3 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <a href="{{ route('series', ['id' => $show->id]) }}" class="inline-flex items-center px-6 py-2.5 bg-indigo-600 dark:bg-indigo-700 text-white font-medium rounded-lg hover:bg-indigo-700 dark:hover:bg-indigo-800 transition shadow-md">
                                            <i class="fas fa-eye mr-2"></i> View All Episodes
                                        </a>
                                        <a href="{{ route('series') }}?title={{ urlencode($show->title) }}" class="inline-flex items-center px-6 py-2.5 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition shadow-md">
                                            <i class="fas fa-search mr-2"></i> Search Similar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-chart-line text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400 text-lg mb-2">No trending TV shows found.</p>
            <p class="text-gray-500 dark:text-gray-500 text-sm">Check back soon for the most popular downloads!</p>
        </div>
    @endif

    <!-- Info Box -->
    <div class="px-6 py-4 bg-indigo-50 dark:bg-gray-900 border-t border-gray-200">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-indigo-500 mt-1"></i>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <strong>About Trending TV Shows:</strong> This list shows the top 15 most downloaded TV shows on our platform <strong>in the last 48 hours</strong>.
                The rankings are updated automatically every hour based on recent download activity.
                Click on any show to see all available episodes and detailed information.
            </div>
        </div>
    </div>
</div>
@endsection

