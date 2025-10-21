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
                        <span class="text-gray-500">Trailer</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    <div class="px-6 py-6">
        @if(isset($movie))
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ $movie['title'] ?? 'Movie Trailer' }}</h1>

            @if(isset($movie['trailer']) && $movie['trailer'])
                <div class="aspect-w-16 aspect-h-9 mb-6">
                    <iframe src="https://www.youtube.com/embed/{{ $movie['trailer'] }}"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            class="w-full h-96 rounded-lg">
                    </iframe>
                </div>
            @else
                <div class="bg-gray-100 dark:bg-gray-800 p-12 rounded-lg text-center mb-6">
                    <i class="fas fa-video-slash text-gray-400 text-6xl mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">No trailer available for this movie.</p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @if(isset($movie['cover']) && $movie['cover'])
                    <div class="lg:col-span-1">
                        <img src="{{ $movie['cover'] }}" alt="{{ $movie['title'] }}" class="w-full rounded-lg shadow-lg">
                    </div>
                @endif

                <div class="lg:col-span-2">
                    @if(isset($movie['plot']) && $movie['plot'])
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Plot</h3>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">{{ $movie['plot'] }}</p>
                        </div>
                    @endif

                    <div class="space-y-2">
                        @if(isset($movie['year']) && $movie['year'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Year:</span>
                                <span class="text-gray-600">{{ $movie['year'] }}</span>
                            </div>
                        @endif

                        @if(isset($movie['rating']) && $movie['rating'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 dark:text-gray-300 w-32">Rating:</span>
                                <span class="text-gray-600">
                                    <i class="fas fa-star text-yellow-500"></i> {{ $movie['rating'] }} / 10
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 dark:text-gray-400 text-lg">Movie information not available.</p>
            </div>
        @endif
    </div>
</div>
@endsection

