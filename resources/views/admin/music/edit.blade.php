@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                <i class="fa fa-music mr-2"></i>{{ $title }}
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

        <!-- Edit Music Form -->
        <form method="POST" action="{{ url('admin/music-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <input type="hidden" name="id" value="{{ $mus['id'] }}">
            <input type="hidden" name="action" value="submit">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Album Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $mus['title'] ?? '' }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Artist -->
                    <div>
                        <label for="artist" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Artist
                        </label>
                        <input type="text"
                               id="artist"
                               name="artist"
                               value="{{ $mus['artist'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Publisher -->
                    <div>
                        <label for="publisher" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Publisher/Label
                        </label>
                        <input type="text"
                               id="publisher"
                               name="publisher"
                               value="{{ $mus['publisher'] ?? '' }}"
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
                               value="{{ $mus['asin'] ?? '' }}"
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
                               value="{{ $mus['url'] ?? '' }}"
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
                                            {{ (isset($mus['genre_id']) && $mus['genre_id'] == $genreItem['id']) ? 'selected' : '' }}>
                                        {{ $genreItem['title'] }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- Year -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Year
                        </label>
                        <input type="number"
                               id="year"
                               name="year"
                               min="1900"
                               max="2100"
                               value="{{ $mus['year'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Release Date -->
                    <div>
                        <label for="releasedate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Release Date
                        </label>
                        <input type="date"
                               id="releasedate"
                               name="releasedate"
                               value="{{ !empty($mus['releasedate']) ? date('Y-m-d', $mus['releasedate']) : '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Tracks -->
                    <div>
                        <label for="tracks" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Number of Tracks
                        </label>
                        <input type="number"
                               id="tracks"
                               name="tracks"
                               min="0"
                               value="{{ $mus['tracks'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Sales Rank -->
                    <div>
                        <label for="salesrank" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Sales Rank
                        </label>
                        <input type="number"
                               id="salesrank"
                               name="salesrank"
                               min="0"
                               value="{{ $mus['salesrank'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <label for="cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cover Image
                        </label>
                        @if(!empty($mus['cover']) && $mus['cover'] == 1)
                            <div class="mb-3">
                                <img src="{{ asset('storage/covers/music/' . $mus['id'] . '.jpg') }}"
                                     alt="Album Cover"
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
                        @if(!empty($mus['cover']) && $mus['cover'] == 1)
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
                <a href="{{ route('admin.music-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fa fa-arrow-left mr-2"></i>Back to Music List
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

