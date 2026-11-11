@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-music" />
    <x-admin.card>




        <!-- Edit Music Form -->
        <form method="POST" action="{{ url('admin/music-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <x-form-errors class="mb-6" />
            <input type="hidden" name="id" value="{{ $mus['id'] }}">
            <input type="hidden" name="action" value="submit">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Album Title <span class="text-danger-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $mus['title'] ?? '' }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Release Date -->
                    <div>
                        <label for="releasedate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Release Date
                        </label>
                        <input type="date"
                               id="releasedate"
                               name="releasedate"
                               value="{{ !empty($mus['releasedate']) ? date('Y-m-d', strtotime($mus['releasedate'])) : '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <label for="cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cover Image
                        </label>
                        @if(!empty($mus['cover']) && $mus['cover'] == 1)
                            <div class="mb-3">
                                <img src="{{ asset('storage/covers/music/' . $mus['id'] . (file_exists(storage_path('covers/music/' . $mus['id'] . '.webp')) ? '.webp' : '.jpg')) }}"
                                     alt="Album Cover"
                                     class="max-w-xs rounded-lg shadow-md border border-gray-300 dark:border-gray-600">
                            </div>
                        @endif
                        <input type="file"
                               id="cover"
                               name="cover"
                               accept="image/jpeg,image/jpg"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                            <x-admin.badge tone="success" icon="fas fa-check">Cover Available</x-admin.badge>
                        @else
                            <x-admin.badge tone="danger" icon="fas fa-times">No Cover</x-admin.badge>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-8 flex gap-3 border-t border-gray-200 pt-6">
                <button type="submit" class="px-6 py-2 bg-accent dark:bg-accent-hover text-white rounded-lg hover:bg-accent-hover">
                    <i aria-hidden="true" class="fas fa-save mr-2"></i>Save Changes
                </button>
                <a href="{{ route('admin.music-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i>Back to Music List
                </a>
            </div>
        </form>
    </x-admin.card>
</div>
@endsection
