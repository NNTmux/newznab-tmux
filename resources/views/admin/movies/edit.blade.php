@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-edit" />
    <x-admin.card>




        <!-- Edit Movie Form -->
        <form method="POST" action="{{ url('admin/movie-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <x-form-errors class="mb-6" />
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="id" value="{{ $movie['imdbid'] ?? $movie->imdbid ?? '' }}">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Movie Images -->
                <div class="space-y-6">
                    <!-- Cover Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cover Image
                        </label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                            @php
                                $imdbid = $movie['imdbid'] ?? $movie->imdbid ?? '';
                                $coverPath = public_path('covers/movies/' . $imdbid . '-cover.webp');
                                $legacyCoverPath = public_path('covers/movies/' . $imdbid . '-cover.jpg');
                                $hasCover = file_exists($coverPath) || file_exists($legacyCoverPath);
                            @endphp
                            @if($hasCover)
                                <img src="{{ asset('covers/movies/' . $imdbid . (file_exists($coverPath) ? '-cover.webp' : '-cover.jpg')) }}"
                                     alt="Movie Cover"
                                     class="w-full h-auto rounded-lg mb-3">
                            @else
                                <div class="flex flex-col items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                                    <i aria-hidden="true" class="fas fa-image text-5xl mb-2"></i>
                                    <p class="text-sm">No cover image</p>
                                </div>
                            @endif
                            <input type="file"
                                   name="cover"
                                   accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-link hover:file:bg-primary-100">
                            <p class="mt-2 text-xs text-gray-500">Upload a new cover image (JPG/PNG)</p>
                        </div>
                    </div>

                    <!-- Backdrop Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Backdrop Image
                        </label>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4">
                            @php
                                $backdropPath = public_path('covers/movies/' . $imdbid . '-backdrop.webp');
                                $legacyBackdropPath = public_path('covers/movies/' . $imdbid . '-backdrop.jpg');
                                $hasBackdrop = file_exists($backdropPath) || file_exists($legacyBackdropPath);
                            @endphp
                            @if($hasBackdrop)
                                <img src="{{ asset('covers/movies/' . $imdbid . (file_exists($backdropPath) ? '-backdrop.webp' : '-backdrop.jpg')) }}"
                                     alt="Movie Backdrop"
                                     class="w-full h-auto rounded-lg mb-3">
                            @else
                                <div class="flex flex-col items-center justify-center py-8 text-gray-500 dark:text-gray-400">
                                    <i aria-hidden="true" class="fas fa-image text-5xl mb-2"></i>
                                    <p class="text-sm">No backdrop image</p>
                                </div>
                            @endif
                            <input type="file"
                                   name="backdrop"
                                   accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-link hover:file:bg-primary-100">
                            <p class="mt-2 text-xs text-gray-500">Upload a new backdrop image (JPG/PNG)</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Movie Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- IMDB ID (Read-only) -->
                    <div>
                        <label for="imdbid" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            IMDB ID
                        </label>
                        <div class="flex gap-2">
                            <input type="text"
                                   id="imdbid"
                                   value="{{ $movie['imdbid'] ?? $movie->imdbid ?? '' }}"
                                   readonly
                                   class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                            <a href="{{ $site->dereferrerLink }}https://www.imdb.com/title/tt{{ $movie['imdbid'] ?? $movie->imdbid ?? '' }}"
                               target="_blank" rel="noopener noreferrer"
                               class="px-4 py-2 bg-warning-500 text-white rounded-md hover:bg-warning-600">
                                <i aria-hidden="true" class="fas fa-external-link"></i> View on IMDB
                            </a>
                        </div>
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Title <span class="text-danger-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ old('title', $movie['title'] ?? $movie->title ?? '') }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Tagline -->
                    <div>
                        <label for="tagline" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tagline
                        </label>
                        <input type="text"
                               id="tagline"
                               name="tagline"
                               value="{{ old('tagline', $movie['tagline'] ?? $movie->tagline ?? '') }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Year and Rating -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Year
                            </label>
                            <input type="number"
                                   id="year"
                                   name="year"
                                   value="{{ old('year', $movie['year'] ?? $movie->year ?? '') }}"
                                   min="1800"
                                   max="2100"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Rating
                            </label>
                            <input type="text"
                                   id="rating"
                                   name="rating"
                                   value="{{ old('rating', $movie['rating'] ?? $movie->rating ?? '') }}"
                                   placeholder="e.g., 8.5"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>

                    <!-- Genre -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Genre
                        </label>
                        <input type="text"
                               id="genre"
                               name="genre"
                               value="{{ old('genre', $movie['genre'] ?? $movie->genre ?? '') }}"
                               placeholder="e.g., Action, Drama, Comedy"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Director -->
                    <div>
                        <label for="director" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Director
                        </label>
                        <input type="text"
                               id="director"
                               name="director"
                               value="{{ old('director', $movie['director'] ?? $movie->director ?? '') }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Actors -->
                    <div>
                        <label for="actors" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Actors
                        </label>
                        <input type="text"
                               id="actors"
                               name="actors"
                               value="{{ old('actors', $movie['actors'] ?? $movie->actors ?? '') }}"
                               placeholder="Comma-separated list of actors"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Language -->
                    <div>
                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Language
                        </label>
                        <input type="text"
                               id="language"
                               name="language"
                               value="{{ old('language', $movie['language'] ?? $movie->language ?? '') }}"
                               placeholder="e.g., English"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Plot -->
                    <div>
                        <label for="plot" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Plot
                        </label>
                        <textarea id="plot"
                                  name="plot"
                                  rows="6"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">{{ old('plot', $movie['plot'] ?? $movie->plot ?? '') }}</textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                            <i aria-hidden="true" class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <a href="{{ url('admin/movie-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i aria-hidden="true" class="fas fa-times mr-2"></i>Cancel
                        </a>
                        <button type="submit"
                           name="update"
                           value="1"
                           class="ml-auto px-6 py-2 bg-success-600 text-white rounded-lg hover:bg-success-700"
                           data-confirm="This will fetch and update movie data from TMDB. Continue?" data-confirm-type="info" data-confirm-text="Update">
                            <i aria-hidden="true" class="fas fa-refresh mr-2"></i>Update from TMDB
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-admin.card>
</div>
@endsection
