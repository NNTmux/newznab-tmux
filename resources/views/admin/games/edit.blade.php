@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.page-header :title="$title" icon="fas fa-gamepad" />
    <x-admin.card>




        <!-- Edit Game Form -->
        <form method="POST" action="{{ url('admin/game-edit') }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <x-form-errors class="mb-6" />
            <input type="hidden" name="id" value="{{ $game['id'] }}">
            <input type="hidden" name="action" value="submit">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Title <span class="text-danger-500">*</span>
                        </label>
                        <input type="text"
                               id="title"
                               name="title"
                               value="{{ $game['title'] ?? '' }}"
                               required
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               value="{{ $game['asin'] ?? '' }}"
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
                               value="{{ $game['url'] ?? '' }}"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
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
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <label for="cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cover Image
                        </label>
                        @if(!empty($game['cover']) && $game['cover'] == 1)
                            <div class="mb-3">
                                <img src="{{ asset('storage/covers/games/' . $game['id'] . (file_exists(storage_path('covers/games/' . $game['id'] . '.webp')) ? '.webp' : '.jpg')) }}"
                                     alt="Game Cover"
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
                        @if(!empty($game['cover']) && $game['cover'] == 1)
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
                <a href="{{ route('admin.game-list') }}" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i aria-hidden="true" class="fas fa-arrow-left mr-2"></i>Back to Game List
                </a>
            </div>
        </form>
    </x-admin.card>
</div>
@endsection
