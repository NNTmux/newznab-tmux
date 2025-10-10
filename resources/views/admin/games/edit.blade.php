@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-gamepad mr-2"></i>{{ $title }}
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

        <!-- Edit Game Form -->
        <form method="POST" action="{{ url('admin/game-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <input type="hidden" name="id" value="{{ $game['id'] }}">
            <input type="hidden" name="action" value="submit">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $game['title'] ?? '' }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Publisher -->
                    <div>
                        <label for="publisher" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Publisher
                        </label>
                        <input type="text"
                               id="publisher"
                               name="publisher"
                               value="{{ $game['publisher'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- ASIN -->
                    <div>
                        <label for="asin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ASIN
                        </label>
                        <input type="text"
                               id="asin"
                               name="asin"
                               value="{{ $game['asin'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- URL -->
                    <div>
                        <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            URL
                        </label>
                        <input type="url"
                               id="url"
                               name="url"
                               value="{{ $game['url'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Trailer URL -->
                    <div>
                        <label for="trailerurl" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Trailer URL
                        </label>
                        <input type="url"
                               id="trailerurl"
                               name="trailerurl"
                               value="{{ $game['trailerurl'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Genre -->
                    <div>
                        <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Genre
                        </label>
                        <select id="genre"
                                name="genre"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Genre --</option>
                            @if(!empty($genres))
                                @foreach($genres as $genreItem)
                                    <option value="{{ $genreItem['id'] }}"
                                            {{ (isset($game['genre_id']) && $game['genre_id'] == $genreItem['id']) ? 'selected' : '' }}>
                                        {{ $genreItem['title'] }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- ESRB Rating -->
                    <div>
                        <label for="esrb" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            ESRB Rating
                        </label>
                        <select id="esrb"
                                name="esrb"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">-- Select Rating --</option>
                            <option value="eC - Early Childhood" {{ (isset($game['esrb']) && $game['esrb'] == 'eC - Early Childhood') ? 'selected' : '' }}>eC - Early Childhood</option>
                            <option value="E - Everyone" {{ (isset($game['esrb']) && $game['esrb'] == 'E - Everyone') ? 'selected' : '' }}>E - Everyone</option>
                            <option value="E10+ - Everyone 10+" {{ (isset($game['esrb']) && $game['esrb'] == 'E10+ - Everyone 10+') ? 'selected' : '' }}>E10+ - Everyone 10+</option>
                            <option value="T - Teen" {{ (isset($game['esrb']) && $game['esrb'] == 'T - Teen') ? 'selected' : '' }}>T - Teen</option>
                            <option value="M - Mature" {{ (isset($game['esrb']) && $game['esrb'] == 'M - Mature') ? 'selected' : '' }}>M - Mature</option>
                            <option value="AO - Adults Only" {{ (isset($game['esrb']) && $game['esrb'] == 'AO - Adults Only') ? 'selected' : '' }}>AO - Adults Only</option>
                            <option value="RP - Rating Pending" {{ (isset($game['esrb']) && $game['esrb'] == 'RP - Rating Pending') ? 'selected' : '' }}>RP - Rating Pending</option>
                        </select>
                    </div>

                    <!-- Release Date -->
                    <div>
                        <label for="releasedate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Release Date
                        </label>
                        <input type="date"
                               id="releasedate"
                               name="releasedate"
                               value="{{ !empty($game['releasedate']) ? date('Y-m-d', $game['releasedate']) : '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <label for="cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cover Image
                        </label>
                        @if(!empty($game['cover']) && $game['cover'] == 1)
                            <div class="mb-3">
                                <img src="{{ asset('storage/covers/games/' . $game['id'] . '.jpg') }}"
                                     alt="Game Cover"
                                     class="max-w-xs rounded-lg shadow-md border border-gray-300 dark:border-gray-600">
                            </div>
                        @endif
                        <input type="file"
                               id="cover"
                               name="cover"
                               accept="image/jpeg,image/jpg"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Upload a new cover image (JPG format)
                        </p>
                    </div>

                    <!-- Current Cover Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Current Cover Status
                        </label>
                        @if(!empty($game['cover']) && $game['cover'] == 1)
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                <i class="fa fa-check mr-2"></i> Cover Available
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                <i class="fa fa-times mr-2"></i> No Cover
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex gap-3 border-t border-gray-200 pt-6">
                <button type="submit" class="px-6 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700">
                    <i class="fa fa-save mr-2"></i>Save Changes
                </button>
                <a href="{{ route('admin.game-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Game List
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

