@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    <i class="fas fa-edit mr-2"></i>{{ $title }}
                </h1>
                <a href="{{ route('admin.anidb-list') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="p-6">
            <form method="POST" action="{{ url('admin/anidb-edit/' . $anime['anidbid']) }}">
                @csrf
                <input type="hidden" name="action" value="submit">
                <input type="hidden" name="anidbid" value="{{ $anime['anidbid'] }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="title"
                                   name="title"
                                   value="{{ $anime['title'] ?? '' }}"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Type -->
                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Type
                            </label>
                            <input type="text"
                                   id="type"
                                   name="type"
                                   value="{{ $anime['type'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Start Date -->
                        <div>
                            <label for="startdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Start Date
                            </label>
                            <input type="text"
                                   id="startdate"
                                   name="startdate"
                                   value="{{ $anime['startdate'] ?? '' }}"
                                   placeholder="YYYY-MM-DD"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- End Date -->
                        <div>
                            <label for="enddate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                End Date
                            </label>
                            <input type="text"
                                   id="enddate"
                                   name="enddate"
                                   value="{{ $anime['enddate'] ?? '' }}"
                                   placeholder="YYYY-MM-DD"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Rating -->
                        <div>
                            <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Rating
                            </label>
                            <input type="text"
                                   id="rating"
                                   name="rating"
                                   value="{{ $anime['rating'] ?? '' }}"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            @if(!empty($anime['rating']))
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Display: {{ number_format($anime['rating'] / 100, 1) }} / 10
                                </p>
                            @endif
                        </div>

                        <!-- Related -->
                        <div>
                            <label for="related" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Related
                            </label>
                            <textarea id="related"
                                      name="related"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['related'] ?? '' }}</textarea>
                        </div>

                        <!-- Similar -->
                        <div>
                            <label for="similar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Similar
                            </label>
                            <textarea id="similar"
                                      name="similar"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['similar'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Cover Image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Cover Image
                            </label>
                            <div class="border border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                                @php
                                    $hasCover = $anime['anidbid'] > 0 && file_exists(storage_path('covers/anime/' . $anime['anidbid'] . '-cover.jpg'));
                                @endphp
                                @if($hasCover)
                                    <img src="{{ url('/covers/anime/' . $anime['anidbid'] . '-cover.jpg') }}"
                                         alt="{{ $anime['title'] }}"
                                         class="max-w-full h-auto mx-auto rounded shadow-lg"
                                         style="max-height: 400px;">
                                @else
                                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                                        <i class="fas fa-dragon text-6xl mb-3"></i>
                                        <p>No cover image available</p>
                                        <p class="text-xs mt-2">Cover will be downloaded from AniList when data is populated</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- External Links -->
                        @php
                            $anilistId = $anime['anilist_id'] ?? null;
                            $malId = $anime['mal_id'] ?? null;
                        @endphp
                        @if(!empty($anilistId) || !empty($malId))
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    External Links
                                </label>
                                <div class="flex flex-wrap gap-2">
                                    @if(!empty($anilistId))
                                        <a href="{{ $site['dereferrer_link'] ?? '' }}https://anilist.co/anime/{{ $anilistId }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-blue-100 text-blue-800 rounded-lg hover:bg-blue-200 transition text-sm">
                                            <i class="fas fa-external-link-alt mr-2"></i> AniList
                                        </a>
                                    @endif
                                    @if(!empty($malId))
                                        <a href="{{ $site['dereferrer_link'] ?? '' }}https://myanimelist.net/anime/{{ $malId }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200 transition text-sm">
                                            <i class="fas fa-external-link-alt mr-2"></i> MyAnimeList
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Description
                            </label>
                            <textarea id="description"
                                      name="description"
                                      rows="8"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['description'] ?? '' }}</textarea>
                        </div>

                        <!-- Creators -->
                        <div>
                            <label for="creators" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Creators
                            </label>
                            <textarea id="creators"
                                      name="creators"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['creators'] ?? '' }}</textarea>
                        </div>

                        <!-- Categories -->
                        <div>
                            <label for="categories" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Categories
                            </label>
                            <textarea id="categories"
                                      name="categories"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['categories'] ?? '' }}</textarea>
                        </div>

                        <!-- Characters -->
                        <div>
                            <label for="characters" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Characters
                            </label>
                            <textarea id="characters"
                                      name="characters"
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['characters'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Episode Information -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="epnos" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Episode Numbers
                        </label>
                        <textarea id="epnos"
                                  name="epnos"
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['epnos'] ?? '' }}</textarea>
                    </div>

                    <div>
                        <label for="airdates" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Air Dates
                        </label>
                        <textarea id="airdates"
                                  name="airdates"
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['airdates'] ?? '' }}</textarea>
                    </div>

                    <div>
                        <label for="episodetitles" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Episode Titles
                        </label>
                        <textarea id="episodetitles"
                                  name="episodetitles"
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md focus:ring-blue-500 focus:border-blue-500">{{ $anime['episodetitles'] ?? '' }}</textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
                    <a href="{{ route('admin.anidb-list') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

