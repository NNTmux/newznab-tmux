@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-edit mr-2"></i>{{ $title }}
            </h1>
        </div>

        <!-- Success/Error/Warning Messages -->
        @if(session('success'))
            <div class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-lg">
                <p class="text-green-800 dark:text-green-200">
                    <i class="fa fa-check-circle mr-2"></i>{{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900 border border-red-200 dark:border-red-700 rounded-lg">
                <p class="text-red-800 dark:text-red-200">
                    <i class="fa fa-exclamation-circle mr-2"></i>{{ session('error') }}
                </p>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-6 mt-4 p-4 bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                <p class="text-yellow-800 dark:text-yellow-200">
                    <i class="fa fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
                </p>
            </div>
        @endif

        <!-- Edit Movie Form -->
        <form method="POST" action="{{ url('admin/movie-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
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
                                $coverPath = public_path('covers/movies/' . $imdbid . '-cover.jpg');
                                $hasCover = file_exists($coverPath);
                            @endphp
                            @if($hasCover)
                                <img src="{{ asset('covers/movies/' . $imdbid . '-cover.jpg') }}"
                                     alt="Movie Cover"
                                     class="w-full h-auto rounded-lg mb-3">
                            @else
                                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                                    <i class="fa fa-image text-5xl mb-2"></i>
                                    <p class="text-sm">No cover image</p>
                                </div>
                            @endif
                            <input type="file"
                                   name="cover"
                                   accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
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
                                $backdropPath = public_path('covers/movies/' . $imdbid . '-backdrop.jpg');
                                $hasBackdrop = file_exists($backdropPath);
                            @endphp
                            @if($hasBackdrop)
                                <img src="{{ asset('covers/movies/' . $imdbid . '-backdrop.jpg') }}"
                                     alt="Movie Backdrop"
                                     class="w-full h-auto rounded-lg mb-3">
                            @else
                                <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                                    <i class="fa fa-image text-5xl mb-2"></i>
                                    <p class="text-sm">No backdrop image</p>
                                </div>
                            @endif
                            <input type="file"
                                   name="backdrop"
                                   accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
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
                            <a href="{{ $site['dereferrer_link'] }}https://www.imdb.com/title/tt{{ $movie['imdbid'] ?? $movie->imdbid ?? '' }}"
                               target="_blank"
                               class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600">
                                <i class="fa fa-external-link"></i> View on IMDB
                            </a>
                        </div>
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $movie['title'] ?? $movie->title ?? '' }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Tagline -->
                    <div>
                        <label for="tagline" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tagline
                        </label>
                        <input type="text"
                               id="tagline"
                               name="tagline"
                               value="{{ $movie['tagline'] ?? $movie->tagline ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
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
                                   value="{{ $movie['year'] ?? $movie->year ?? '' }}"
                                   min="1800"
                                   max="2100"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Rating
                            </label>
                            <input type="text"
                                   id="rating"
                                   name="rating"
                                   value="{{ $movie['rating'] ?? $movie->rating ?? '' }}"
                                   placeholder="e.g., 8.5"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
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
                               value="{{ $movie['genre'] ?? $movie->genre ?? '' }}"
                               placeholder="e.g., Action, Drama, Comedy"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Director -->
                    <div>
                        <label for="director" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Director
                        </label>
                        <input type="text"
                               id="director"
                               name="director"
                               value="{{ $movie['director'] ?? $movie->director ?? '' }}"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Actors -->
                    <div>
                        <label for="actors" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Actors
                        </label>
                        <input type="text"
                               id="actors"
                               name="actors"
                               value="{{ $movie['actors'] ?? $movie->actors ?? '' }}"
                               placeholder="Comma-separated list of actors"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Language -->
                    <div>
                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Language
                        </label>
                        <input type="text"
                               id="language"
                               name="language"
                               value="{{ $movie['language'] ?? $movie->language ?? '' }}"
                               placeholder="e.g., English"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Plot -->
                    <div>
                        <label for="plot" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Plot
                        </label>
                        <textarea id="plot"
                                  name="plot"
                                  rows="6"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">{{ $movie['plot'] ?? $movie->plot ?? '' }}</textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                            <i class="fa fa-save mr-2"></i>Save Changes
                        </button>
                        <a href="{{ url('admin/movie-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i class="fa fa-times mr-2"></i>Cancel
                        </a>
                        <a href="{{ url('admin/movie-edit?id=' . ($movie['imdbid'] ?? $movie->imdbid ?? '') . '&update=1') }}"
                           class="ml-auto px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                           data-confirm="This will fetch and update movie data from TMDB. Continue?">
                            <i class="fa fa-refresh mr-2"></i>Update from TMDB
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fa fa-film mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ url('admin/movie-add') }}" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add Movie
                </a>
            </div>
        </div>

        <!-- Search Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200">
            <form method="GET" action="{{ url('admin/movie-list') }}">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa fa-search text-gray-400"></i>
                        </div>
                        <input type="text"
                               name="moviesearch"
                               value="{{ $lastSearch ?? '' }}"
                               placeholder="Search by IMDB ID or movie title..."
                               class="pl-10 w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                        Search
                    </button>
                    @if(!empty($lastSearch))
                        <a href="{{ url('admin/movie-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Movie List Table -->
        @if(!empty($movielist) && count($movielist) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IMDB ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Year</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Genre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cover</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Backdrop</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                        @foreach($movielist as $movie)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                    <a href="{{ $site['dereferrer_link'] }}https://www.imdb.com/title/tt{{ $movie->imdbid }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $movie->imdbid }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ $movie->title ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $movie->year ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $movie->rating ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Illuminate\Support\Str::limit($movie->genre ?? 'N/A', 30) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($movie->cover == 1)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fa fa-check"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fa fa-times"></i> No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($movie->backdrop == 1)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fa fa-check"></i> Yes
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fa fa-times"></i> No
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ url('admin/movie-edit?id=' . $movie->imdbid) }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 mr-3">
                                        <i class="fa fa-edit"></i> Edit
                                    </a>
                                    <a href="{{ url('admin/movie-edit?id=' . $movie->imdbid . '&update=1') }}" class="text-green-600 dark:text-green-400 hover:text-green-900" title="Update from TMDB">
                                        <i class="fa fa-refresh"></i> Update
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <i class="fa fa-film text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400 text-lg">
                    @if(!empty($lastSearch))
                        No movies found matching "{{ $lastSearch }}".
                    @else
                        No movies found in the database.
                    @endif
                </p>
                <a href="{{ url('admin/movie-add') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus mr-2"></i>Add Your First Movie
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

