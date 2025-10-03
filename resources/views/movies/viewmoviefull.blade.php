@extends('layouts.main')

@section('content')
<div class="bg-white rounded-lg shadow-sm">
    <!-- Breadcrumb -->
    <div class="px-6 py-4 border-b border-gray-200">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url($site->home_link ?? '/') }}" class="text-gray-700 hover:text-blue-600 inline-flex items-center">
                        <i class="fas fa-home mr-2"></i> Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="{{ route('Movies') }}" class="text-gray-700 hover:text-blue-600">Movies</a>
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
                    @if(isset($movie['cover']) && $movie['cover'])
                        <img src="{{ $movie['cover'] }}" alt="{{ $movie['title'] }}" class="w-full rounded-lg shadow-lg">
                    @else
                        <div class="w-full h-96 bg-gray-200 rounded-lg flex items-center justify-center">
                            <i class="fas fa-film text-gray-400 text-6xl"></i>
                        </div>
                    @endif
                </div>

                <!-- Movie Details -->
                <div class="lg:col-span-2">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $movie['title'] }}</h1>

                    @if(isset($movie['rating']) && $movie['rating'])
                        <div class="flex items-center mb-4">
                            <span class="text-yellow-500 text-2xl mr-2">
                                <i class="fas fa-star"></i>
                            </span>
                            <span class="text-2xl font-semibold text-gray-900">{{ $movie['rating'] }}</span>
                            <span class="text-gray-600 ml-2">/ 10</span>
                        </div>
                    @endif

                    <div class="space-y-3 mb-6">
                        @if(isset($movie['year']) && $movie['year'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Year:</span>
                                <span class="text-gray-600">{{ $movie['year'] }}</span>
                            </div>
                        @endif

                        @if(isset($movie['genre']) && $movie['genre'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Genre:</span>
                                <span class="text-gray-600">{!! $movie['genre'] !!}</span>
                            </div>
                        @endif

                        @if(isset($movie['director']) && $movie['director'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Director:</span>
                                <span class="text-gray-600">{!! $movie['director'] !!}</span>
                            </div>
                        @endif

                        @if(isset($movie['actors']) && $movie['actors'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Actors:</span>
                                <span class="text-gray-600">{!! $movie['actors'] !!}</span>
                            </div>
                        @endif

                        @if(isset($movie['language']) && $movie['language'])
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-32">Language:</span>
                                <span class="text-gray-600">{{ $movie['language'] }}</span>
                            </div>
                        @endif
                    </div>

                    @if(isset($movie['plot']) && $movie['plot'])
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-2">Plot</h2>
                            <p class="text-gray-700 leading-relaxed">{{ $movie['plot'] }}</p>
                        </div>
                    @endif

                    <!-- Trailer -->
                    @if(isset($movie['trailer']) && $movie['trailer'])
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-2">Trailer</h2>
                            <div class="aspect-w-16 aspect-h-9">
                                <iframe src="https://www.youtube.com/embed/{{ $movie['trailer'] }}"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                        class="w-full h-96 rounded-lg">
                                </iframe>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <i class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600 text-lg">Movie details not available.</p>
        </div>
    @endif
</div>
@endsection

