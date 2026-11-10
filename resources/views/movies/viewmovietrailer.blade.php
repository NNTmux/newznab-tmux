@extends('layouts.main')

@section('content')
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
    <x-page-header :title="$movie['title'] ?? 'Movie trailer'" current="Trailer" eyebrow="Watch the preview" description="Watch the available trailer and review the key details for this movie." icon="fas fa-circle-play" />

    <div class="px-6 py-6">
        @if(isset($movie))
            @if(!empty($movie['trailer_url'] ?? null))
                <div class="mb-6">
                    <a href="{{ $site->dereferrerLink ?? '' }}{{ $movie['trailer_url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-4 py-2 bg-accent text-white rounded-lg hover:bg-accent-hover transition">
                        <i aria-hidden="true" class="fas fa-circle-play mr-2"></i> Watch Trailer
                    </a>
                </div>
            @else
                <div class="bg-gray-100 dark:bg-gray-800 p-12 rounded-lg text-center mb-6">
                    <i aria-hidden="true" class="fas fa-video-slash text-gray-400 text-6xl mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">No trailer available for this movie.</p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                @if(isset($movie['cover']) && $movie['cover'])
                    <div class="lg:col-span-1">
                        <img width="320" height="480" src="{{ $movie['cover'] }}" alt="{{ $movie['title'] }}" class="w-full rounded-lg shadow-lg" loading="lazy">
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
                                    <i aria-hidden="true" class="fas fa-star text-amber-500"></i> {{ $movie['rating'] }} / 10
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <i aria-hidden="true" class="fas fa-exclamation-circle text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 dark:text-gray-400 text-lg">Movie information not available.</p>
            </div>
        @endif
    </div>
</div>
@endsection
